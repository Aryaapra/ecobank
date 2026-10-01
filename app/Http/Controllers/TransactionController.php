<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\WasteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }

        $query = Transaction::with('wasteType')->latest();
        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        $transactions = $query->get()
            ->map(fn(Transaction $transaction): array => $this->present($transaction));

        return response()->json(['data' => $transactions]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        if ($response = $this->requireRole($request, 'admin')) {
            return $response;
        }

        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();
        $approved = Transaction::where('status', 'approved');
        $weeklyWeightsByDate = Transaction::query()
            ->where('status', 'approved')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('DATE(created_at) AS day, SUM(weight_kg) AS weight')
            ->groupBy('day')
            ->pluck('weight', 'day');
        $dayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $weeklyWeights = collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $weeklyWeightsByDate, $dayLabels): array {
                $day = $weekStart->copy()->addDays($offset);
                $date = $day->toDateString();

                return [
                    'label' => $dayLabels[$day->dayOfWeek],
                    'weight' => (float) ($weeklyWeightsByDate[$date] ?? 0),
                ];
            });

        return response()->json(['data' => [
            'totalTransactions' => Transaction::count(),
            'pendingTransactions' => Transaction::where('status', 'pending')->count(),
            'totalWeight' => (float) (clone $approved)->sum('weight_kg'),
            'totalValue' => (int) (clone $approved)->sum('total_value'),
            'monthlyWeight' => (float) (clone $approved)
                ->where('created_at', '>=', $monthStart)
                ->where('created_at', '<', $nextMonth)
                ->sum('weight_kg'),
            'monthlyTransactions' => (clone $approved)
                ->where('created_at', '>=', $monthStart)
                ->where('created_at', '<', $nextMonth)
                ->count(),
            'weeklyWeights' => $weeklyWeights,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requireRole($request, 'user')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'wasteTypeId' => ['required', 'integer', 'min:1'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:10000', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $transaction = DB::transaction(function () use ($request, $data): Transaction|JsonResponse {
            $wasteType = WasteType::where('is_active', true)->lockForUpdate()->find($data['wasteTypeId']);
            if (! $wasteType) {
                return response()->json(['error' => 'Jenis sampah tidak ditemukan atau sudah tidak aktif.'], 422);
            }

            $weight = (float) $data['weight'];

            return Transaction::create([
                'transaction_code' => 'EB-' . now()->format('ymd') . '-' . strtoupper(Str::random(6)),
                'customer_name' => $request->user()->name,
                'user_id' => $request->user()->id,
                'waste_type_id' => $wasteType->id,
                'weight_kg' => $weight,
                'total_value' => (int) round($weight * $wasteType->price),
                'status' => 'pending',
            ])->load('wasteType');
        });

        if ($transaction instanceof JsonResponse) {
            return $transaction;
        }

        return response()->json(['data' => $this->present($transaction)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireRole($request, 'admin')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:approved,cancelled'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($id, $validator): JsonResponse {
            $transaction = Transaction::lockForUpdate()->find($id);
            if (! $transaction) {
                return response()->json(['error' => 'Transaksi tidak ditemukan.'], 404);
            }
            if ($transaction->status !== 'pending') {
                return response()->json(['error' => 'Transaksi ini sudah diproses. Muat ulang daftar transaksi.'], 409);
            }

            $transaction->update(['status' => $validator->validated()['status']]);

            return response()->json(['data' => ['id' => $id, 'status' => $transaction->status]]);
        });
    }

    private function present(Transaction $transaction): array
    {
        return [
            'id' => $transaction->transaction_code,
            'dbId' => (int) $transaction->id,
            'code' => $transaction->transaction_code,
            'name' => $transaction->customer_name,
            'waste' => $transaction->wasteType->category,
            'detail' => $transaction->wasteType->name,
            'weight' => (float) $transaction->weight_kg,
            'value' => (int) $transaction->total_value,
            'date' => $transaction->created_at->format('d M Y'),
            'month' => $transaction->created_at->format('Y-m'),
            'status' => $transaction->status,
            'avatar' => '♻',
        ];
    }

    private function requireRole(Request $request, string $role): ?JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }

        return $request->user()->role === $role
            ? null
            : response()->json(['error' => 'Akses tidak diizinkan.'], 403);
    }
}
