<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->query('q', ''));
        $transactionSummary = DB::table('transactions')
            ->select('user_id')
            ->selectRaw('COUNT(*) AS transaction_count')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN weight_kg ELSE 0 END) AS approved_weight")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN total_value ELSE 0 END) AS approved_value")
            ->groupBy('user_id');

        $members = DB::table('users')
            ->leftJoinSub($transactionSummary, 'transaction_summary', function ($join): void {
                $join->on('transaction_summary.user_id', '=', 'users.id');
            })
            ->where('users.role', 'user')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('users.phone', 'like', "%{$search}%")
                        ->orWhere('users.district', 'like', "%{$search}%");
                });
            })
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.district',
                'users.created_at',
                DB::raw('COALESCE(transaction_summary.transaction_count, 0) AS transaction_count'),
                DB::raw('COALESCE(transaction_summary.pending_count, 0) AS pending_count'),
                DB::raw('COALESCE(transaction_summary.approved_weight, 0) AS approved_weight'),
                DB::raw('COALESCE(transaction_summary.approved_value, 0) AS approved_value'),
            ])
            ->orderBy('users.name')
            ->paginate(25)
            ->withQueryString();

        return view('pengguna', [
            'members' => $members,
            'search' => $search,
        ]);
    }
}
