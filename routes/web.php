<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WasteTypeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'index')->name('home');
Route::redirect('/index.html', '/');
Route::view('/auth.html', 'auth')->name('login');

Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::view('/dashboard_bank_sampah.html', 'dashboard_bank_sampah')->name('admin.dashboard');
    Route::get('/transaksi.html', [ReportController::class, 'transactions'])->name('admin.transactions');
    Route::get('/peringkat.html', RankingController::class)->name('admin.ranking');
    Route::view('/listing_limbah_sampah.html', 'listing_limbah_sampah')->name('admin.prices');
    Route::get('/pengguna.html', [MemberController::class, 'index'])->name('admin.members');
    Route::get('/laporan.html', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('/laporan.csv', [ReportController::class, 'export'])->name('admin.reports.export');
});

Route::view('/beranda_user.html', 'beranda_user')
    ->middleware(['auth', 'role:user'])
    ->name('user.home');

Route::get('/api/auth/me', [AuthController::class, 'me']);
Route::post('/api/auth', [AuthController::class, 'handle']);
Route::get('/api/rankings', [RankingController::class, 'data'])->name('api.rankings');

Route::get('/api/prices', [WasteTypeController::class, 'index']);
Route::post('/api/prices', [WasteTypeController::class, 'store']);
Route::put('/api/prices/{id}', [WasteTypeController::class, 'update'])->whereNumber('id');
Route::delete('/api/prices/{id}', [WasteTypeController::class, 'destroy'])->whereNumber('id');

Route::get('/api/transactions', [TransactionController::class, 'index']);
Route::get('/api/dashboard', [TransactionController::class, 'dashboard']);
Route::post('/api/transactions', [TransactionController::class, 'store']);
Route::put('/api/transactions/{id}', [TransactionController::class, 'update'])->whereNumber('id');
