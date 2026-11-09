<?php

namespace App\Services;

use App\Contracts\IncomingUsdtChainVerifier;
use App\Contracts\UsdtChainVerificationDiagnostics;
use App\Contracts\UsdtChainVerifier;
use App\Exceptions\UsdtVerificationFailed;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only verification; never broadcasts transactions. Fail closed on uncertainty. */
final class ReadOnlyUsdtChainVerifier implements IncomingUsdtChainVerifier, UsdtChainVerificationDiagnostics, UsdtChainVerifier
{
    private const BSC_USDT = '0x55d398326f99059ff775485246999027b3197955';

    private const TRON_USDT = '41a614f803b6fd780986a42c78ec9c7f77e6ded13c';

    private const TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    private ?string $failureReason = null;

    private ?array $incomingEvidence = null;

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    private function reject(string $reason): bool
    {
        $this->failureReason = $reason;

        return false;
    }

    public function verified(string $network, string $txHash, string $recipient, string $amount): bool
    {
        return $this->verify($network, $txHash, $recipient, $amount, false);
    }

    public function incomingEvidence(string $network, string $txHash, string $recipient, string $requestedAmount): array
    {
        UsdtPaymentEvidence::assertAddress($network, $recipient);
        $txHash = UsdtPaymentEvidence::normalizeHash($network, $txHash);
        if (! $this->verify($network, $txHash, $recipient, $requestedAmount, true) || $this->incomingEvidence === null) {
            throw new UsdtVerificationFailed($this->failureReason ?? 'Independent incoming USDT verification failed.');
        }

        return $this->incomingEvidence;
    }

    private function verify(string $network, string $txHash, string $recipient, string $amount, bool $incoming): bool
    {
        $this->failureReason = null;
        $this->incomingEvidence = null;
        if (! config('leobot_usdt_rpc.enabled', false)) {
            throw new RuntimeException('USDT RPC verification is disabled.');
        }
        if (! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0) {
            return false;
        }
        $units = $this->units($amount);

        return match ($network) {
            'BEP20' => $this->bep20($txHash, $recipient, $units, $incoming),
            'TRC20' => $this->trc20($txHash, $recipient, $units, $incoming),
            default => false,
        };
    }

    private function units(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return bcadd(bcmul($whole, '1000000000000000000', 0), str_pad($fraction, 18, '0'), 0);
    }

    private function rpc(string $url, string $method, array $params): mixed
    {
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('HTTPS RPC endpoint required.');
        }
        $response = Http::timeout(12)->retry(1, 200)->post($url, [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params,
        ]);
        if (! $response->successful() || $response->json('error') !== null) {
            throw new UsdtVerificationFailed('RPC failure. The trusted chain verifier is unavailable; keep the request pending and retry later.');
        }

        return $response->json('result');
    }

    private function bep20(string $hash, string $recipient, string $units, bool $incoming): bool
    {
        if (! preg_match('/\A0x[a-fA-F0-9]{64}\z/D', $hash) || ! preg_match('/\A0x[a-fA-F0-9]{40}\z/D', $recipient)) {
            return false;
        }
        $url = (string) config('leobot_usdt_rpc.bsc_rpc_url', '');
        if (strtolower((string) $this->rpc($url, 'eth_chainId', [])) !== '0x38') {
            return $this->reject('The configured verifier did not return BNB Smart Chain mainnet. Keep this request pending and contact support.');
        }
        $receipt = $this->rpc($url, 'eth_getTransactionReceipt', [$hash]);
        $tx = $this->rpc($url, 'eth_getTransactionByHash', [$hash]);
        $height = $this->rpc($url, 'eth_blockNumber', []);
        if ($receipt === null && $tx === null) {
            return $this->reject('Transaction not found on BNB Smart Chain. Check the BEP20 transaction hash and network, then retry after broadcast.');
        }
        if ($receipt === null || (is_array($tx) && empty($tx['blockNumber']))) {
            return $this->reject('Transaction is not yet confirmed on BNB Smart Chain. Keep the request pending and retry after confirmation.');
        }
        if (is_array($receipt) && strtolower((string) ($receipt['status'] ?? '')) === '0x0') {
            return $this->reject('This on-chain transaction failed. A successful USDT transfer is required for approval.');
        }

        if (! is_array($receipt) || ! is_array($tx) || ! is_string($height)
            || strtolower((string) ($receipt['status'] ?? '')) !== '0x1'
            || empty($receipt['blockHash']) || empty($receipt['blockNumber'])
            || ! isset($tx['blockHash'], $tx['blockNumber'])
            || strtolower((string) $tx['blockHash']) !== strtolower((string) $receipt['blockHash'])
            || strtolower((string) $tx['blockNumber']) !== strtolower((string) $receipt['blockNumber'])
            || ! preg_match('/\A0x[0-9a-fA-F]+\z/D', $height)
            || ! preg_match('/\A0x[0-9a-fA-F]+\z/D', (string) $receipt['blockNumber'])) {
            return $this->reject('The node returned incomplete or inconsistent transaction evidence. Keep the request pending and retry.');
        }
        if ($incoming && (strtolower((string) ($receipt['transactionHash'] ?? '')) !== strtolower($hash)
            || strtolower((string) ($tx['hash'] ?? '')) !== strtolower($hash))) {
            return $this->reject('The incoming receipt does not match the requested transaction hash. Keep the request pending.');
        }
        $tip = $this->hexDecimal(substr($height, 2));
        $block = $this->hexDecimal(substr($receipt['blockNumber'], 2));
        if (bccomp(bcsub($tip, $block, 0), (string) (max(1, (int) config('leobot_usdt_rpc.bsc_confirmations', 30)) - 1), 0) < 0) {
            return $this->reject('The transaction has too few confirmations. Keep the request pending and retry after more blocks.');
        }
        $tokenTransfer = false;
        $recipientTransfer = false;
        foreach (($receipt['logs'] ?? []) as $log) {
            if (! is_array($log) || strtolower((string) ($log['address'] ?? '')) !== self::BSC_USDT || ! empty($log['removed'])) {
                continue;
            }
            $topics = $log['topics'] ?? [];
            if (count($topics) !== 3 || strtolower((string) $topics[0]) !== self::TRANSFER) {
                continue;
            }
            $tokenTransfer = true;
            if (! preg_match('/\A0x[0-9a-fA-F]{64}\z/D', (string) $topics[2]) || strtolower(substr($topics[2], -40)) !== strtolower(substr($recipient, 2))) {
                continue;
            }
            $recipientTransfer = true;
            $data = (string) ($log['data'] ?? '');
            if (! preg_match('/\A0x[0-9a-fA-F]{64}\z/D', $data)) {
                continue;
            }
            $observedUnits = $this->hexDecimal(substr($data, 2));
            if ($incoming ? bccomp($observedUnits, $units, 0) >= 0 : bccomp($observedUnits, $units, 0) === 0) {
                if ($incoming) {
                    $this->recordIncomingEvidence('BEP20', $hash, $recipient, $units, $observedUnits, 18, self::BSC_USDT, (string) ($log['logIndex'] ?? ''), (string) $receipt['blockHash']);
                }

                return true;
            }
        }

        return $this->reject(! $tokenTransfer
            ? 'No canonical BEP20 USDT transfer was found in this transaction. Check the token and network.'
            : (! $recipientTransfer
                ? 'The USDT transfer does not match this deposit receiving address. Check the transaction destination.'
                : 'The USDT transfer amount does not match the requested transfer amount. Check the transferred amount and any exchange fee.'));
    }

    private function trc20(string $hash, string $recipient, string $units, bool $incoming): bool
    {
        // TRON uses 6 decimals; BEP20 uses 18. Convert exactly, reject sub-sun amounts.
        if (! preg_match('/\A[0-9a-fA-F]{64}\z/D', $hash)) {
            return false;
        }
        $hexRecipient = $this->tronAddress($recipient);
        if ($hexRecipient === null || bcmod($units, '1000000000000') !== '0') {
            return false;
        }
        $tronUnits = bcdiv($units, '1000000000000', 0);
        $full = (string) config('leobot_usdt_rpc.tron_full_node_url', '');
        $solid = (string) config('leobot_usdt_rpc.tron_solidity_node_url', '');
        if (! str_starts_with($full, 'https://') || ! str_starts_with($solid, 'https://')) {
            throw new RuntimeException('HTTPS TRON endpoints required.');
        }
        $tx = Http::timeout(12)->post(rtrim($full, '/').'/wallet/gettransactionbyid', ['value' => $hash]);
        $info = Http::timeout(12)->post(rtrim($solid, '/').'/walletsolidity/gettransactioninfobyid', ['value' => $hash]);
        $confirmedTx = Http::timeout(12)->post(rtrim($solid, '/').'/walletsolidity/gettransactionbyid', ['value' => $hash]);
        if (! $tx->successful() || ! $info->successful() || ! $confirmedTx->successful()) {
            throw new RuntimeException('TRON node failure.');
        }
        $t = $tx->json();
        $i = $info->json();
        $ct = $confirmedTx->json();
        if (! is_array($t) || ! is_array($i) || ! is_array($ct)
            || strtolower((string) ($t['txID'] ?? '')) !== strtolower($hash)
            || strtolower((string) ($ct['txID'] ?? '')) !== strtolower($hash)
            || strtolower((string) ($i['id'] ?? '')) !== strtolower($hash)
            || ($i['receipt']['result'] ?? '') !== 'SUCCESS'
            || ($t['ret'][0]['contractRet'] ?? '') !== 'SUCCESS'
            || empty($i['blockNumber']) || empty($ct['raw_data']['expiration'])
            || ! isset($t['raw_data'], $ct['raw_data'])
            || ! is_array($t['raw_data']) || ! is_array($ct['raw_data'])
            || $t['raw_data'] !== $ct['raw_data']
            || ($ct['ret'][0]['contractRet'] ?? '') !== 'SUCCESS') {
            return false;
        }
        foreach (($i['log'] ?? []) as $log) {
            if (! is_array($log) || strtolower((string) ($log['address'] ?? '')) !== substr(self::TRON_USDT, 2)) {
                continue;
            }
            $topics = $log['topics'] ?? [];
            if (count($topics) !== 3 || strtolower((string) $topics[0]) !== substr(self::TRANSFER, 2)) {
                continue;
            }
            if (! preg_match('/\A[0-9a-fA-F]{64}\z/D', (string) $topics[2]) || strtolower(substr($topics[2], -40)) !== strtolower(substr($hexRecipient, 2))) {
                continue;
            }
            $data = (string) ($log['data'] ?? '');
            if (! preg_match('/\A[0-9a-fA-F]{64}\z/D', $data)) {
                continue;
            }
            $observedUnits = $this->hexDecimal($data);
            if ($incoming ? bccomp($observedUnits, $tronUnits, 0) >= 0 : bccomp($observedUnits, $tronUnits, 0) === 0) {
                if ($incoming) {
                    $this->recordIncomingEvidence('TRC20', $hash, $recipient, $tronUnits, $observedUnits, 6, self::TRON_USDT, '', (string) $i['blockNumber']);
                }

                return true;
            }
        }

        return false;
    }

    private function recordIncomingEvidence(string $network, string $hash, string $recipient, string $requestedUnits, string $receivedUnits, int $decimals, string $contract, string $logIndex, string $blockReference): void
    {
        $factor = bcpow('10', (string) $decimals, 0);
        $surplusUnits = bcsub($receivedUnits, $requestedUnits, 0);
        $this->incomingEvidence = [
            'network' => $network, 'tx_hash' => $hash, 'recipient' => $recipient,
            'token_contract' => $contract, 'token_decimals' => $decimals,
            'received_units' => $receivedUnits, 'received_amount' => bcdiv($receivedUnits, $factor, $decimals),
            'credited_amount' => bcdiv($requestedUnits, $factor, 8),
            'uncredited_surplus_units' => $surplusUnits, 'uncredited_surplus' => bcdiv($surplusUnits, $factor, $decimals),
            'log_index' => $logIndex, 'block_reference' => $blockReference,
        ];
    }

    private function hexDecimal(string $hex): string
    {
        $result = '0';
        foreach (str_split(strtolower($hex)) as $char) {
            $digit = strpos('0123456789abcdef', $char);
            if ($digit === false) {
                throw new RuntimeException('Invalid hexadecimal value.');
            }
            $result = bcadd(bcmul($result, '16', 0), (string) $digit, 0);
        }

        return $result;
    }

    private function tronAddress(string $address): ?string
    {
        if (! preg_match('/\AT[1-9A-HJ-NP-Za-km-z]{33}\z/D', $address)) {
            return null;
        }
        $n = '0';
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        foreach (str_split($address) as $c) {
            $v = strpos($alphabet, $c);
            if ($v === false) {
                return null;
            }
            $n = bcadd(bcmul($n, '58', 0), (string) $v, 0);
        }
        $hex = '';
        while (bccomp($n, '0', 0) > 0) {
            $r = bcmod($n, '256');
            $hex = str_pad(dechex((int) $r), 2, '0', STR_PAD_LEFT).$hex;
            $n = bcdiv($n, '256', 0);
        }
        $hex = str_pad($hex, 50, '0', STR_PAD_LEFT);
        if (strlen($hex) !== 50 || substr($hex, 0, 2) !== '41') {
            return null;
        }
        $payload = hex2bin(substr($hex, 0, 42));
        $check = substr(hash('sha256', hex2bin(hash('sha256', $payload))), 0, 8);
        if (! hash_equals($check, substr($hex, 42, 8))) {
            return null;
        }

        return substr($hex, 0, 42);
    }
}
