<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\UsdtWalletChallengeService;
use App\Services\UsdtWalletEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

final class UsdtWalletController extends Controller
{
    public function challenge(
        Request $request,
        UsdtWalletChallengeService $service
    ): JsonResponse {
        $data = $request->validate([
            'network' => ['required', Rule::in(['TRC20', 'BEP20'])],
            'address' => ['required', 'string', 'max:64'],
        ]);

        try {
            $challenge = $service->issue(
                (int) $request->user()->id,
                $data['network'],
                $data['address']
            );

            return response()->json($challenge);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => 'Invalid wallet challenge request.',
            ], 422);
        }
    }

    public function verify(
        Request $request,
        UsdtWalletEnrollmentService $service
    ): JsonResponse {
        $data = $request->validate([
            'challenge_id' => ['required', 'integer', 'min:1'],
            'message' => ['required', 'string', 'max:2048'],
            'signature' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $walletId = $service->enroll(
                (int) $request->user()->id,
                (int) $data['challenge_id'],
                $data['message'],
                $data['signature']
            );

            return response()->json([
                'verified' => true,
                'wallet_id' => $walletId,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => 'Wallet verification failed.',
            ], 422);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $wallets = DB::table('usdt_member_wallets')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('verified_at')
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->get(['id', 'network', 'address', 'verified_at']);

        return response()->json(['wallets' => $wallets]);
    }
}