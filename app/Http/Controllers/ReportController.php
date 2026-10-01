<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $monthValidator = Validator::make(
            ['month' => $request->query('month')],
            ['month' => ['required', 'date_format:Y-m']],
        );
        $month = $monthValidator->passes()
            ? $monthValidator->validated()['month']
            : now()->format('Y-m');

        return redirect()->route('admin.transactions', ['month' => $month]);
    }

    public function transactions(Request $request): View
    {
        [$month, $periodStart, $periodEnd] = $this->period($request);
        $transactions = $this->transactionsForPeriod($periodStart, $periodEnd);

        return view('transaksi', [
            'reportMonth' => $month,
            'monthSummary' => [
                'transactionCount' => (clone $transactions)->count(),
                'pendingCount' => (clone $transactions)->where('transactions.status', 'pending')->count(),
                'approvedWeight' => (float) (clone $transactions)
                    ->where('transactions.status', 'approved')->sum('transactions.weight_kg'),
                'approvedValue' => (int) (clone $transactions)
                    ->where('transactions.status', 'approved')->sum('transactions.total_value'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$month, $periodStart, $periodEnd] = $this->period($request);
        $transactions = $this->transactionsForPeriod($periodStart, $periodEnd)
            ->select([
                'transactions.id',
                'transactions.id AS transaction_id',
                'transactions.transaction_code',
                'transactions.customer_name',
                'waste_types.name AS waste_name',
                'waste_types.category AS waste_category',
                'transactions.weight_kg',
                'transactions.total_value',
                'transactions.status',
                'transactions.created_at',
            ])
            ->orderBy('transactions.id');

        return response()->streamDownload(function () use ($transactions): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Kode transaksi',
                'Nama anggota',
                'Jenis sampah',
                'Kategori',
                'Berat (kg)',
                'Nilai (Rp)',
                'Status',
                'Tanggal',
            ]);

            $transactions->chunkById(500, function ($rows) use ($stream): void {
                foreach ($rows as $transaction) {
                    fputcsv($stream, [
                        $transaction->transaction_code,
                        $transaction->customer_name,
                        $transaction->waste_name,
                        $transaction->waste_category,
                        $transaction->weight_kg,
                        $transaction->total_value,
                        $transaction->status,
                        $transaction->created_at,
                    ]);
                }
            }, 'transactions.id', 'transaction_id');

            fclose($stream);
        }, "laporan-ecobank-{$month}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function period(Request $request): array
    {
        $month = $request->query('month', now()->format('Y-m'));
        $validatedMonth = Validator::make(
            ['month' => $month],
            ['month' => ['required', 'date_format:Y-m']],
        )->validate()['month'];
        $periodStart = Carbon::createFromFormat('!Y-m', $validatedMonth);

        return [
            $validatedMonth,
            $periodStart,
            $periodStart->copy()->addMonth(),
        ];
    }

    private function transactionsForPeriod(Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return DB::table('transactions')
            ->leftJoin('waste_types', 'waste_types.id', '=', 'transactions.waste_type_id')
            ->where('transactions.created_at', '>=', $periodStart)
            ->where('transactions.created_at', '<', $periodEnd);
    }
}
