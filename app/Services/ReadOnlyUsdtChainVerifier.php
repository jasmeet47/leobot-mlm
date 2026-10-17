<?php
namespace App\Services;

use App\Contracts\UsdtChainVerifier;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only verification; never broadcasts transactions. Fail closed on uncertainty. */
final class ReadOnlyUsdtChainVerifier implements UsdtChainVerifier
{
    private const BSC_USDT = '0x55d398326f99059ff775485246999027b3197955';
    private const TRON_USDT = '41a614f803b6fd780986a42c78ec9c7f77e6ded13c';
    private const TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    public function verified(string $network, string $txHash, string $recipient, string $amount): bool
    {
        if (!config('leobot_usdt_rpc.enabled', false)) {
            throw new RuntimeException('USDT RPC verification is disabled.');
        }
        if (!preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0) {
            return false;
        }
        $units = $this->units($amount);
        return match ($network) {
            'BEP20' => $this->bep20($txHash, $recipient, $units),
            'TRC20' => $this->trc20($txHash, $recipient, $units),
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
        if (!str_starts_with($url, 'https://')) throw new RuntimeException('HTTPS RPC endpoint required.');
        $response = Http::timeout(12)->retry(1, 200)->post($url, [
            'jsonrpc'=>'2.0', 'id'=>1, 'method'=>$method, 'params'=>$params,
        ]);
        if (!$response->successful() || $response->json('error') !== null) throw new RuntimeException('RPC failure.');
        return $response->json('result');
    }

    private function bep20(string $hash, string $recipient, string $units): bool
    {
        if (!preg_match('/\A0x[a-fA-F0-9]{64}\z/D', $hash) || !preg_match('/\A0x[a-fA-F0-9]{40}\z/D', $recipient)) return false;
        $url = (string) config('leobot_usdt_rpc.bsc_rpc_url', '');
        if (strtolower((string)$this->rpc($url, 'eth_chainId', [])) !== '0x38') return false;
        $receipt = $this->rpc($url, 'eth_getTransactionReceipt', [$hash]);
        $tx = $this->rpc($url, 'eth_getTransactionByHash', [$hash]);
        $height = $this->rpc($url, 'eth_blockNumber', []);
        if (!is_array($receipt) || !is_array($tx) || !is_string($height)
            || strtolower((string)($receipt['status'] ?? '')) !== '0x1'
            || empty($receipt['blockHash']) || empty($receipt['blockNumber'])
            || !isset($tx['blockHash'], $tx['blockNumber'])
            || strtolower((string)$tx['blockHash']) !== strtolower((string)$receipt['blockHash'])
            || strtolower((string)$tx['blockNumber']) !== strtolower((string)$receipt['blockNumber'])
            || !preg_match('/\A0x[0-9a-fA-F]+\z/D', $height)
            || !preg_match('/\A0x[0-9a-fA-F]+\z/D', (string)$receipt['blockNumber'])) return false;
        $tip = $this->hexDecimal(substr($height, 2));
        $block = $this->hexDecimal(substr($receipt['blockNumber'], 2));
        if (bccomp(bcsub($tip, $block, 0), (string)(max(1, (int)config('leobot_usdt_rpc.bsc_confirmations', 30))-1), 0) < 0) return false;
        foreach (($receipt['logs'] ?? []) as $log) {
            if (!is_array($log) || strtolower((string)($log['address'] ?? '')) !== self::BSC_USDT || !empty($log['removed'])) continue;
            $topics = $log['topics'] ?? [];
            if (count($topics) !== 3 || strtolower((string)$topics[0]) !== self::TRANSFER) continue;
            if (!preg_match('/\A0x[0-9a-fA-F]{64}\z/D', (string)$topics[2]) || strtolower(substr($topics[2], -40)) !== strtolower(substr($recipient, 2))) continue;
            $data = (string)($log['data'] ?? '');
            if (!preg_match('/\A0x[0-9a-fA-F]{64}\z/D', $data)) continue;
            if (bccomp($this->hexDecimal(substr($data, 2)), $units, 0) === 0) return true;
        }
        return false;
    }

    private function trc20(string $hash, string $recipient, string $units): bool
    {
        // TRON uses 6 decimals; BEP20 uses 18. Convert exactly, reject sub-sun amounts.
        if (!preg_match('/\A[0-9a-fA-F]{64}\z/D', $hash)) return false;
        $hexRecipient = $this->tronAddress($recipient);
        if ($hexRecipient === null || bcmod($units, '1000000000000') !== '0') return false;
        $tronUnits = bcdiv($units, '1000000000000', 0);
        $full = (string)config('leobot_usdt_rpc.tron_full_node_url', '');
        $solid = (string)config('leobot_usdt_rpc.tron_solidity_node_url', '');
        if (!str_starts_with($full, 'https://') || !str_starts_with($solid, 'https://')) throw new RuntimeException('HTTPS TRON endpoints required.');
        $tx = Http::timeout(12)->post(rtrim($full, '/').'/wallet/gettransactionbyid', ['value'=>$hash]);
        $info = Http::timeout(12)->post(rtrim($solid, '/').'/walletsolidity/gettransactioninfobyid', ['value'=>$hash]);
        $confirmedTx = Http::timeout(12)->post(rtrim($solid, '/').'/walletsolidity/gettransactionbyid', ['value'=>$hash]);
        if (!$tx->successful() || !$info->successful() || !$confirmedTx->successful()) throw new RuntimeException('TRON node failure.');
        $t=$tx->json(); $i=$info->json(); $ct=$confirmedTx->json();
        if (!is_array($t) || !is_array($i) || !is_array($ct)
            || strtolower((string)($t['txID'] ?? '')) !== strtolower($hash)
            || strtolower((string)($ct['txID'] ?? '')) !== strtolower($hash)
            || strtolower((string)($i['id'] ?? '')) !== strtolower($hash)
            || ($i['receipt']['result'] ?? '') !== 'SUCCESS'
            || ($t['ret'][0]['contractRet'] ?? '') !== 'SUCCESS'
            || empty($i['blockNumber']) || empty($ct['raw_data']['expiration'])) return false;
        foreach (($i['log'] ?? []) as $log) {
            if (!is_array($log) || strtolower((string)($log['address'] ?? '')) !== substr(self::TRON_USDT, 2)) continue;
            $topics=$log['topics'] ?? [];
            if (count($topics)!==3 || strtolower((string)$topics[0])!==substr(self::TRANSFER, 2)) continue;
            if (!preg_match('/\A[0-9a-fA-F]{64}\z/D', (string)$topics[2]) || strtolower(substr($topics[2], -40))!==strtolower(substr($hexRecipient, 2))) continue;
            $data=(string)($log['data'] ?? '');
            if (!preg_match('/\A[0-9a-fA-F]{64}\z/D', $data)) continue;
            if (bccomp($this->hexDecimal($data), $tronUnits, 0)===0) return true;
        }
        return false;
    }

    private function hexDecimal(string $hex): string
    {
        $result='0';
        foreach (str_split(strtolower($hex)) as $char) {
            $digit=strpos('0123456789abcdef', $char);
            if ($digit===false) throw new RuntimeException('Invalid hexadecimal value.');
            $result=bcadd(bcmul($result, '16', 0), (string)$digit, 0);
        }
        return $result;
    }

    private function tronAddress(string $address): ?string
    {
        if (!preg_match('/\AT[1-9A-HJ-NP-Za-km-z]{33}\z/D', $address)) return null;
        $n='0'; $alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        foreach (str_split($address) as $c) {
            $v=strpos($alphabet, $c);
            if ($v===false) return null;
            $n=bcadd(bcmul($n,'58',0),(string)$v,0);
        }
        $hex='';
        while (bccomp($n,'0',0)>0) { $r=bcmod($n,'256'); $hex=str_pad(dechex((int)$r),2,'0',STR_PAD_LEFT).$hex; $n=bcdiv($n,'256',0); }
        $hex=str_pad($hex,50,'0',STR_PAD_LEFT);
        if (strlen($hex)!==50 || substr($hex,0,2)!=='41') return null;
        $payload=hex2bin(substr($hex,0,42));
        $check=substr(hash('sha256',hex2bin(hash('sha256',$payload))),0,8);
        if (!hash_equals($check,substr($hex,42,8))) return null;
        return substr($hex,0,42);
    }
}
