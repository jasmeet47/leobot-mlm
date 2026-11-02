<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Staging-only service. Routes must not be enabled before isolated tests and approval. */
final class FlexibleStakingService
{
    public function request(int $userId, int $positionId, string $amount, string $key): int
    {
        $this->checkAmount($amount);
        $this->checkKey($key);
        return DB::transaction(function () use ($userId, $positionId, $amount, $key) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($user->is_blocked || $user->status !== 'active' || $user->role !== 'member') {
                throw new RuntimeException('Member ineligible');
            }
            $position = DB::table('staking_positions')->where('id', $positionId)->lockForUpdate()->first();
            if (!$position || (int)$position->user_id !== $userId || $position->status !== 'active') {
                throw new RuntimeException('Position unavailable');
            }
            $existing = DB::table('staking_unstake_requests')->where('request_key', $key)->first();
            if ($existing) {
                if ((int)$existing->user_id !== $userId || (int)$existing->position_id !== $positionId || bccomp((string)$existing->amount, $amount, 8) !== 0) {
                    throw new RuntimeException('Request key conflict');
                }
                return (int)$existing->id;
            }
            $pending = (string) DB::table('staking_unstake_requests')
                ->where('position_id', $positionId)->where('status', 'pending')
                ->selectRaw('COALESCE(SUM(amount),0) AS total')->value('total');
            if (bccomp(bcadd($pending, $amount, 8), (string)$position->remaining_amount, 8) > 0) {
                throw new RuntimeException('Amount exceeds unstakeable principal');
            }
            return (int)DB::table('staking_unstake_requests')->insertGetId([
                'user_id'=>$userId, 'position_id'=>$positionId, 'amount'=>$amount,
                'request_key'=>$key, 'status'=>'pending', 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }, 3);
    }

    public function approve(User $admin, int $requestId): void
    {
        $this->checkAdmin($admin);
        DB::transaction(function () use ($admin, $requestId) {
            $req = DB::table('staking_unstake_requests')->where('id', $requestId)->first();
            if (!$req) throw new RuntimeException('Request missing');
            $position = DB::table('staking_positions')->where('id', $req->position_id)->lockForUpdate()->first();
            if (!$position) throw new RuntimeException('Position missing');
            $req = DB::table('staking_unstake_requests')->where('id', $requestId)->lockForUpdate()->first();
            if ($req->status !== 'pending') throw new RuntimeException('Request already processed');
            if ($position->status !== 'active' || bccomp((string)$position->remaining_amount, (string)$req->amount, 8) < 0) {
                throw new RuntimeException('Principal insufficient');
            }
            // Crediting wallet, reducing locked principal and approval record share one DB transaction.
            WalletService::credit((int)$req->user_id, 'available_balance', (string)$req->amount,
                'staking_principal_return', 'staking:unstake:'.$requestId,
                ['reference_type'=>'staking_unstake_request', 'reference_id'=>$requestId]);
            $remaining = bcsub((string)$position->remaining_amount, (string)$req->amount, 8);
            DB::table('staking_positions')->where('id', $position->id)->update([
                'remaining_amount'=>$remaining,
                'status'=>bccomp($remaining, '0', 8) === 0 ? 'closed' : 'active', 'updated_at'=>now(),
            ]);
            DB::table('staking_unstake_requests')->where('id', $requestId)->update([
                'status'=>'approved', 'reviewed_by'=>$admin->id, 'reviewed_at'=>now(), 'updated_at'=>now(),
            ]);
        }, 3);
    }

    public function reject(User $admin, int $requestId): void
    {
        $this->checkAdmin($admin);
        DB::transaction(function () use ($admin, $requestId) {
            $req = DB::table('staking_unstake_requests')->where('id', $requestId)->first();
            if (!$req) throw new RuntimeException('Request missing');
            DB::table('staking_positions')->where('id', $req->position_id)->lockForUpdate()->first();
            $req = DB::table('staking_unstake_requests')->where('id', $requestId)->lockForUpdate()->first();
            if ($req->status !== 'pending') throw new RuntimeException('Request already processed');
            DB::table('staking_unstake_requests')->where('id', $requestId)->update([
                'status'=>'rejected', 'reviewed_by'=>$admin->id, 'reviewed_at'=>now(), 'updated_at'=>now(),
            ]);
        }, 3);
    }

    private function checkAmount(string $amount): void
    {
        if (!preg_match('/^\d+(\.\d{1,8})?$/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Invalid amount');
        }
    }
    private function checkKey(string $key): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) throw new RuntimeException('Invalid request key');
    }
    private function checkAdmin(User $admin): void
    {
        if ($admin->is_blocked || $admin->status !== 'active' || !in_array($admin->role, ['admin','super_admin'], true)) {
            throw new RuntimeException('Admin required');
        }
    }
}
