<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DepositDestinationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Zxing\QrReader;

final class DepositControlController extends Controller
{
    public function index(): View
    {
        $destinations = collect(['BEP20', 'TRC20'])->mapWithKeys(fn ($network) => [$network => DepositDestinationService::current($network)])->all();
        $ready = Schema::hasTable('deposit_destinations');
        $history = $ready ? DB::table('deposit_destinations')->orderByDesc('id')->limit(20)->get() : collect();

        return view('admin.deposit-control', compact('destinations', 'ready', 'history'));
    }

    public function update(Request $request, string $network): RedirectResponse
    {
        abort_unless(Schema::hasTable('deposit_destinations'), 503);
        $data = $request->validate(['address' => ['required', 'string', 'max:100'], 'expected_revision' => ['required', 'integer', 'min:0'],
            'qr' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=1024,max_height=1024']]);
        $address = trim($data['address']);
        try {
            DepositDestinationService::validateAddress($network, $address);
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['address' => $error->getMessage()]);
        }
        $bytes = null;
        $mime = null;
        if ($file = $request->file('qr')) {
            try {
                $payload = trim((string) (new QrReader($file->getRealPath(), QrReader::SOURCE_TYPE_FILE, false))->text());
            } catch (\Throwable) {
                $payload = '';
            }
            if ($network === 'BEP20' && preg_match('/^ethereum:(0x[a-f0-9]{40})(?:@56)?$/iD', $payload, $match)) {
                $payload = $match[1];
            } elseif ($network === 'TRC20' && str_starts_with($payload, 'tron:')) {
                $payload = substr($payload, 5);
            }
            $matches = $network === 'BEP20' ? strcasecmp($payload, $address) === 0 : hash_equals($address, $payload);
            if (! $matches) {
                throw ValidationException::withMessages(['qr' => 'The QR could not be read or does not match this receiving address.']);
            }
            $bytes = $file->get();
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 422);
        }
        DB::transaction(function () use ($request, $data, $network, $address, $bytes, $mime): void {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['lumiflowon-deposit-destination:'.$network]);
            $admin = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($admin && $admin->deleted_at === null && ! $admin->is_blocked && $admin->status === 'active'
                && in_array($admin->role, ['admin', 'super_admin'], true), 403);
            $current = DB::table('deposit_destinations')->where('network', $network)->orderByDesc('id')->first();
            if ((int) ($current?->id ?? 0) !== (int) $data['expected_revision']) {
                throw ValidationException::withMessages(['address' => 'Another administrator changed this network. Refresh before saving.']);
            }
            $retainQr = $bytes === null && $current?->address === $address;
            DB::table('deposit_destinations')->insert(['network' => $network, 'address' => $address,
                'qr_base64' => $bytes !== null ? base64_encode($bytes) : ($retainQr ? $current->qr_base64 : null),
                'qr_mime' => $bytes !== null ? $mime : ($retainQr ? $current->qr_mime : null),
                'changed_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', $network.' receiving address saved successfully.');
    }

    public function qr(Request $request, int $revision): Response
    {
        abort_unless(Schema::hasTable('deposit_destinations'), 404);
        $row = DB::table('deposit_destinations')->where('id', $revision)->first();
        abort_unless($row && $row->qr_base64, 404);
        if (! in_array($request->user()->role, ['admin', 'super_admin'], true)) {
            abort_unless(config('leobot.deposits_enabled') === true && config('leobot.production_financial_approval') === true
                && config('leobot_usdt_rpc.enabled') === true, 404);
            abort_unless(DepositDestinationService::current($row->network)['id'] === $row->id, 404);
        }

        return response(base64_decode($row->qr_base64, true), 200, ['Content-Type' => $row->qr_mime,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
