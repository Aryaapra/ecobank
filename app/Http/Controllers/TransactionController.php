<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WasteType;
use App\Services\AvailableBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function index(Request $request, AvailableBalanceService $availableBalance): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }

        $query = Transaction::with(['wasteType', 'user'])->latest();
        $summaryQuery = Transaction::query();
        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
            $summaryQuery->where('user_id', $request->user()->id);
        }

        $transactions = $query->get()
            ->map(fn(Transaction $transaction): array => $this->present($transaction));
        $approvedTransactions = (clone $summaryQuery)->where('status', 'approved');
        $grossBalance = (int) (clone $approvedTransactions)->sum('total_value');
        $summary = [
            'balance' => $request->user()->role === 'user'
                ? $availableBalance->forUser((int) $request->user()->id)
                : $grossBalance,
            'approvedWeight' => (float) (clone $approvedTransactions)->sum('weight_kg'),
            'pendingCount' => (clone $summaryQuery)
                ->whereIn('status', ['pending', 'pickup_confirmed', 'weighing'])
                ->count(),
        ];

        return response()->json([
            'data' => $transactions,
            'summary' => $summary,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function dashboard(Request $request): JsonResponse
    {
        if ($response = $this->requireRole($request, 'admin')) {
            return $response;
        }

        $validator = Validator::make($request->query(), [
            'period' => ['sometimes', 'in:7d,30d,12m'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('period'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $period = $request->query('period', '7d');
        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();
        $chartStart = match ($period) {
            '30d' => $today->copy()->subDays(29)->startOfDay(),
            '12m' => $today->copy()->startOfMonth()->subMonths(11),
            default => $today->copy()->subDays(6)->startOfDay(),
        };
        $chartEnd = $period === '12m'
            ? $monthStart->copy()->addMonth()
            : $today->copy()->addDay()->startOfDay();
        $approved = Transaction::where('status', 'approved');
        $weightsByDate = Transaction::query()
            ->where('status', 'approved')
            ->where('created_at', '>=', $chartStart)
            ->where('created_at', '<', $chartEnd)
            ->selectRaw('DATE(created_at) AS day, SUM(weight_kg) AS weight')
            ->groupBy('day')
            ->pluck('weight', 'day');
        $chartWeights = collect();
        if ($period === '12m') {
            $monthlyWeights = $weightsByDate->reduce(
                function (array $totals, $weight, string $date): array {
                    $month = \Illuminate\Support\Carbon::parse($date)->format('Y-m');
                    $totals[$month] = ($totals[$month] ?? 0) + (float) $weight;

                    return $totals;
                },
                [],
            );
            $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            for ($offset = 0; $offset < 12; $offset++) {
                $month = $chartStart->copy()->addMonths($offset);
                $chartWeights->push([
                    'label' => $monthLabels[$month->month - 1],
                    'weight' => (float) ($monthlyWeights[$month->format('Y-m')] ?? 0),
                ]);
            }
        } else {
            $dayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            $dayCount = $period === '30d' ? 30 : 7;
            for ($offset = 0; $offset < $dayCount; $offset++) {
                $day = $chartStart->copy()->addDays($offset);
                $chartWeights->push([
                    'label' => $period === '30d'
                        ? $day->format('j')
                        : $dayLabels[$day->dayOfWeek],
                    'date' => $day->translatedFormat('j M Y'),
                    'weight' => (float) ($weightsByDate[$day->toDateString()] ?? 0),
                ]);
            }
        }

        return response()->json(['data' => [
            'totalTransactions' => Transaction::count(),
            'totalMembers' => User::where('role', 'user')->count(),
            'pendingTransactions' => Transaction::whereIn('status', ['pending', 'pickup_confirmed', 'weighing'])->count(),
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
            'chartWeights' => $chartWeights,
            'chartPeriod' => $period,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requireRole($request, 'user')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'wasteTypeIds' => ['required_without:wasteTypeId', 'array', 'min:1'],
            'wasteTypeIds.*' => ['required', 'integer', 'min:1', 'distinct'],
            'wasteTypeId' => ['required_without:wasteTypeIds', 'integer', 'min:1'],
            'deliveryMethod' => ['required', 'in:pickup,self_delivery'],
            'pickupAddress' => ['required_if:deliveryMethod,pickup', 'nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $groupCode = 'EBG-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        $transactions = DB::transaction(function () use ($request, $data, $groupCode): array|JsonResponse {
            $wasteTypeIds = $data['wasteTypeIds'] ?? [$data['wasteTypeId']];
            $wasteTypes = WasteType::where('is_active', true)
                ->whereIn('id', $wasteTypeIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($wasteTypes->count() !== count($wasteTypeIds)) {
                return response()->json(['error' => 'Jenis sampah tidak ditemukan atau sudah tidak aktif.'], 422);
            }

            return collect($wasteTypeIds)
                ->map(function (int $wasteTypeId) use ($request, $data, $wasteTypes, $groupCode): Transaction {
                    $wasteType = $wasteTypes->get($wasteTypeId);

                    return Transaction::create([
                        'transaction_code' => 'EB-' . now()->format('ymd') . '-' . strtoupper(Str::random(6)),
                        'request_group_code' => $groupCode,
                        'customer_name' => $request->user()->name,
                        'user_id' => $request->user()->id,
                        'waste_type_id' => $wasteType->id,
                        'weight_kg' => 0,
                        'total_value' => 0,
                        'delivery_method' => $data['deliveryMethod'],
                        'pickup_address' => $data['deliveryMethod'] === 'pickup' ? $data['pickupAddress'] : null,
                        'unit_price' => $wasteType->price,
                        'process_stage' => 'requested',
                        'status' => 'pending',
                    ])->load(['wasteType', 'user']);
                })
                ->all();
        });

        if ($transactions instanceof JsonResponse) {
            return $transactions;
        }

        $presentedTransactions = array_map($this->present(...), $transactions);

        return response()->json([
            'data' => $presentedTransactions[0],
            'items' => $presentedTransactions,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }

        $isUser = $request->user()->role === 'user';
        $isAdmin = $request->user()->role === 'admin';
        if (! $isUser && ! $isAdmin) {
            return response()->json(['error' => 'Akses tidak diizinkan.'], 403);
        }

        $rules = $isUser
            ? [
                'action' => ['required', 'in:edit,cancel'],
                'wasteTypeId' => ['required_if:action,edit', 'integer', 'min:1'],
                'deliveryMethod' => ['required_if:action,edit', 'in:pickup,self_delivery'],
                'pickupAddress' => ['required_if:deliveryMethod,pickup', 'nullable', 'string', 'max:255'],
            ]
            : [
                'action' => ['required', 'in:confirm_pickup,start_weighing,finalize_weighing,cancel'],
                'actualWeight' => ['nullable', 'numeric', 'gt:0', 'max:10000', 'regex:/^\d+(\.\d{1,2})?$/'],
                'actualWeights' => ['nullable', 'array'],
                'actualWeights.*' => ['numeric', 'gt:0', 'max:10000', 'regex:/^\d+(\.\d{1,2})?$/'],
            ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($id, $validator, $request, $isUser): JsonResponse {
            $transaction = Transaction::with('wasteType')->lockForUpdate()->find($id);
            if (! ($transaction instanceof Transaction)) {
                return response()->json(['error' => 'Transaksi tidak ditemukan.'], 404);
            }
            if ($isUser && $transaction->user_id !== $request->user()->id) {
                return response()->json(['error' => 'Transaksi tidak ditemukan.'], 404);
            }

            $data = $validator->validated();
            $action = $data['action'];
            $groupTransactions = collect([$transaction]);
            if ($transaction->request_group_code && ($request->user()->role === 'admin' || $action === 'cancel')) {
                $groupTransactions = Transaction::with('wasteType')
                    ->where('request_group_code', $transaction->request_group_code)
                    ->lockForUpdate()
                    ->get();

                if ($groupTransactions->contains(
                    fn(Transaction $item): bool =>
                    $item->status !== $transaction->status
                        || $item->process_stage !== $transaction->process_stage
                        || $item->delivery_method !== $transaction->delivery_method
                )) {
                    return response()->json(['error' => 'Item dalam transaksi memiliki tahap berbeda dan tidak dapat diproses bersama.'], 409);
                }
            }

            if ($isUser && $action === 'edit') {
                if ($transaction->status !== 'pending' || $transaction->process_stage !== 'requested') {
                    return response()->json(['error' => 'Setoran hanya dapat diedit sebelum diproses pengurus.'], 409);
                }

                $wasteType = WasteType::where('is_active', true)->lockForUpdate()->find($data['wasteTypeId']);
                if (! $wasteType) {
                    return response()->json(['error' => 'Jenis sampah tidak ditemukan atau sudah tidak aktif.'], 422);
                }

                $transaction->update([
                    'waste_type_id' => $wasteType->id,
                    'weight_kg' => 0,
                    'total_value' => 0,
                    'unit_price' => $wasteType->price,
                    'delivery_method' => $data['deliveryMethod'],
                    'pickup_address' => $data['deliveryMethod'] === 'pickup' ? $data['pickupAddress'] : null,
                ]);
                if ($transaction->request_group_code) {
                    Transaction::where('request_group_code', $transaction->request_group_code)
                        ->where('id', '!=', $transaction->id)
                        ->update([
                            'delivery_method' => $data['deliveryMethod'],
                            'pickup_address' => $data['deliveryMethod'] === 'pickup' ? $data['pickupAddress'] : null,
                        ]);
                }

                return response()->json(['data' => [
                    'id' => $id,
                    'status' => $transaction->status,
                    'stage' => $transaction->process_stage,
                    'weight' => (float) $transaction->weight_kg,
                    'value' => (int) $transaction->total_value,
                ]]);
            }

            $canUserCancel = $transaction->status === 'pending'
                && ($transaction->process_stage === 'requested'
                    || ($transaction->delivery_method === 'self_delivery' && $transaction->process_stage === 'weighing'));
            if ($isUser && ($action !== 'cancel' || ! $canUserCancel)) {
                return response()->json(['error' => 'Setoran hanya dapat dibatalkan sebelum disetujui. Permintaan jemput tidak dapat dibatalkan setelah dikonfirmasi.'], 409);
            }

            $nextStage = match ($action) {
                'confirm_pickup' => $transaction->status === 'pending'
                    && $transaction->process_stage === 'requested'
                    && $transaction->delivery_method === 'pickup'
                    ? 'pickup_confirmed'
                    : null,
                'start_weighing' => $transaction->status === 'pending'
                    && (($transaction->delivery_method === 'self_delivery' && $transaction->process_stage === 'requested')
                        || ($transaction->delivery_method === 'pickup' && $transaction->process_stage === 'pickup_confirmed'))
                    ? 'weighing'
                    : null,
                'finalize_weighing' => $transaction->status === 'pending' && $transaction->process_stage === 'weighing'
                    ? 'completed'
                    : null,
                'cancel' => $transaction->status === 'pending'
                    ? 'cancelled'
                    : null,
            };

            if ($nextStage === null) {
                return response()->json([
                    'error' => 'Aksi tidak sesuai dengan tahap setoran saat ini.',
                ], 409);
            }

            if ($action === 'finalize_weighing') {
                $actualWeights = $data['actualWeights'] ?? [];
                if ($groupTransactions->count() === 1 && isset($data['actualWeight'])) {
                    $actualWeights[$transaction->id] = $data['actualWeight'];
                }

                $expectedIds = $groupTransactions->pluck('id')->map(fn(int $itemId): string => (string) $itemId)->all();
                $providedIds = array_map('strval', array_keys($actualWeights));
                if (count($actualWeights) !== $groupTransactions->count() || array_diff($expectedIds, $providedIds)) {
                    return response()->json(['error' => 'Masukkan berat aktual untuk setiap jenis sampah dalam transaksi.'], 422);
                }

                foreach ($groupTransactions as $item) {
                    $weight = (float) $actualWeights[$item->id];
                    $unitPrice = $item->unit_price ?? $item->wasteType->price;
                    $item->update([
                        'process_stage' => 'completed',
                        'status' => 'approved',
                        'weight_kg' => $weight,
                        'total_value' => (int) round($weight * $unitPrice),
                    ]);
                }
            } else {
                $updates = ['process_stage' => $nextStage];
                if ($action === 'cancel') {
                    $updates['status'] = 'cancelled';
                }
                foreach ($groupTransactions as $item) {
                    $item->update($updates);
                }
            }

            $items = $groupTransactions->map(fn(Transaction $item): array => [
                'id' => (int) $item->id,
                'status' => $item->status,
                'stage' => $item->process_stage,
                'weight' => (float) $item->weight_kg,
                'value' => (int) $item->total_value,
            ])->values();

            return response()->json(['data' => $items->first(), 'items' => $items]);
        });
    }

    private function present(Transaction $transaction): array
    {
        return [
            'id' => $transaction->transaction_code,
            'dbId' => (int) $transaction->id,
            'code' => $transaction->transaction_code,
            'groupId' => $transaction->request_group_code ?? $transaction->transaction_code,
            'name' => $transaction->customer_name,
            'waste' => $transaction->wasteType->category,
            'wasteTypeId' => (int) $transaction->waste_type_id,
            'detail' => $transaction->wasteType->name,
            'weight' => (float) $transaction->weight_kg,
            'value' => (int) $transaction->total_value,
            'deliveryMethod' => $transaction->delivery_method ?? 'self_delivery',
            'pickupAddress' => $transaction->pickup_address,
            'unitPrice' => (int) ($transaction->unit_price ?? $transaction->wasteType->price),
            'userPhone' => $transaction->user?->phone,
            'userDistrict' => $transaction->user?->district,
            'date' => $transaction->created_at->format('d M Y'),
            'month' => $transaction->created_at->format('Y-m'),
            'status' => $transaction->status,
            'stage' => $transaction->process_stage ?? ($transaction->status === 'approved' ? 'completed' : 'requested'),
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
