<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnifiedProfitSharingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/** Admin review is read-only by default; actual reserve funding requires separate approval gates. */
final class UnifiedFundingController extends Controller
{
    public function index(): View
    {
        $fundings = DB::table('profit_sharing_fundings')
            ->orderByDesc('id')
            ->paginate(20);

        $settings = DB::table('profit_sharing_settings')->where('id', 1)->first();
        $generation = DB::table('generation_pool_settings')->where('id', 1)->first();
        $magic = DB::table('magic_income_settings')->where('id', 1)->first();

        return view('admin.unified-funding', [
            'fundings' => $fundings,
            'settings' => $settings,
            'generation' => $generation,
            'magic' => $magic,
            'entryEnabled' => $this->entryEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Must reject before validation or any DB access when not approved.
        abort_unless($this->entryEnabled(), 404);

        $data = $request->validate([
            'profit_reference' => ['required', 'string', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,99}\z/D'],
            'company_profit' => ['required', 'string', 'regex:/\A\d+(?:\.\d{1,8})?\z/D', 'max:30'],
            'member_breakdown' => ['required', 'string', 'max:200000'],
            'source_verified' => ['accepted'],
        ]);

        $breakdown = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($data['member_breakdown']));
        if (!$lines || count($lines) > 10000) {
            return back()->withErrors(['member_breakdown' => 'A maximum of 10,000 member lines is permitted.'])->withInput();
        }
        foreach ($lines as $line) {
            if (!preg_match('/\A([1-9]\d*),\s*(\d+(?:\.\d{1,8})?)\z/D', trim($line), $match)) {
                return back()->withErrors(['member_breakdown' => 'Use one user_id,amount pair per line.'])->withInput();
            }
            $id = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || isset($breakdown[$id])) {
                return back()->withErrors(['member_breakdown' => 'Invalid or repeated user ID.'])->withInput();
            }
            $breakdown[$id] = $match[2];
        }

        try {
            $fundingId = UnifiedProfitSharingService::fund(
                $request->user(),
                $data['company_profit'],
                $data['profit_reference'],
                $breakdown
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['funding' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.unified-funding.index')
            ->with('status', 'Reserve funding recorded with ID ' . $fundingId . '. No member payout was made.');
    }

    private function entryEnabled(): bool
    {
        if (config('leobot.admin_unified_funding_ui_enabled', false) !== true) {
            return false;
        }
        // Production requires an independent, explicit approval. Both gates default OFF.
        return !app()->environment('production')
            || config('leobot.production_financial_approval', false) === true;
    }
}
