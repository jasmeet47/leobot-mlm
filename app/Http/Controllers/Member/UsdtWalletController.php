<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MemberPaymentSecurity;
use App\Services\UsdtWalletChallengeService;
use App\Services\UsdtWalletEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class UsdtWalletController extends Controller
{
    public function challenge(
        Request $request,
        UsdtWalletChallengeService $service
    ): JsonResponse {
        if (! MemberPaymentSecurity::changesEnabled()) {
            return response()->json(['message' => 'Profile wallet changes are disabled by the administrator.'], 422);
        }
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
        return response()->json(['message' => 'Wallet address changes now require account email OTP. Please use the profile address page.'], 410);
    }

    public function index(Request $request): JsonResponse
    {
        $wallets = MemberPaymentSecurity::wallets($request->user()->id);

        return response()->json(['wallets' => $wallets]);
    }
}
