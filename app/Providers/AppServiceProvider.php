<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'admin_riwayat',
            'dashboard_bank_sampah',
            'listing_limbah_sampah',
            'pengguna',
            'peringkat',
            'profil',
            'transaksi',
        ], function (ViewInstance $view): void {
            $user = auth()->user();
            $pendingTransactionCount = 0;

            if ($user?->role === 'admin') {
                $pendingTransactionCount = (int) DB::table('transactions')
                    ->whereIn('status', ['pending', 'pickup_confirmed', 'weighing'])
                    ->selectRaw('COUNT(DISTINCT COALESCE(request_group_code, transaction_code)) AS aggregate')
                    ->value('aggregate');
            }

            $view->with('pendingTransactionCount', $pendingTransactionCount);
        });
    }
}
