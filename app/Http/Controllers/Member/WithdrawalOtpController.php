<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalEmailOtpService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WithdrawalOtpController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:30', 'regex:/\A\d+(?:\.\d{1,8})?\z/D'], 'request_key' => ['required', 'string', 'max:100'], 'usdt_wallet_id' => ['required', 'integer', 'min:1'], 'position_id' => ['nullable', 'integer', 'min:1'], 'expected_fee_percent' => ['nullable', 'string', 'max:20']]);
        try {
            $id = WithdrawalEmailOtpService::issue($request->user(), $data);

            return response()->json(['challenge_id' => $id, 'message' => 'Email OTP sent successfully. Enter the code below.']);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['message' => 'Unable to prepare withdrawal verification. Please try again.'], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
