<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AvailableBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WithdrawalController extends Controller
{
    private const MINIMUM_AMOUNT = 5000;

    private const PAYOUT_METHODS = [
        'BCA',
        'BRI',
        'BNI',
        'Mandiri',
        'BTN',
        'DANA',
        'GoPay',
        'OVO',
        'ShopeePay',
        'LinkAja',
    ];

    public function index(Request $request, AvailableBalanceService $availableBalanceService): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }
        if (! in_array($request->user()->role, ['admin', 'user'], true)) {
            return response()->json(['error' => 'Akses tidak diizinkan.'], 403);
        }

        $query = Withdrawal::query()->latest();
        if ($request->user()->role === 'admin') {
            $query->with('user');
        } else {
            $query->where('user_id', $request->user()->id);
        }

        $withdrawals = $query->limit(200)->get()->map(
            fn(Withdrawal $withdrawal): array => $this->present($withdrawal, $request->user()->role === 'admin'),
        );
        $availableBalance = $request->user()->role === 'admin'
            ? null
            : $availableBalanceService->forUser((int) $request->user()->id);

        return response()->json([
            'data' => $withdrawals,
            'summary' => [
                'availableBalance' => $availableBalance,
                'minimumAmount' => self::MINIMUM_AMOUNT,
            ],
            'savedAccount' => $request->user()->role === 'user' ? [
                'bank' => $request->user()->payout_bank,
                'accountNumber' => $request->user()->payout_account_number,
                'accountName' => $request->user()->payout_account_name,
            ] : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AvailableBalanceService $availableBalanceService): JsonResponse
    {
        if ($response = $this->requireRole($request, 'user')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'integer', 'min:' . self::MINIMUM_AMOUNT],
            'payoutBank' => ['required', Rule::in(self::PAYOUT_METHODS)],
            'accountNumber' => ['required', 'string', 'regex:/^[0-9]{8,20}$/'],
            'accountName' => ['required', 'string', 'min:2', 'max:100'],
            'saveAccount' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $result = DB::transaction(function () use ($request, $data, $availableBalanceService): Withdrawal|JsonResponse {
            $user = User::query()->lockForUpdate()->find($request->user()->id);
            if (! $user) {
                return response()->json(['error' => 'Akun tidak ditemukan.'], 404);
            }

            $availableBalance = $availableBalanceService->forUser((int) $user->id);
            if ($data['amount'] > $availableBalance) {
                return response()->json([
                    'error' => 'Nominal penarikan melebihi saldo yang tersedia.',
                    'availableBalance' => $availableBalance,
                ], 422);
            }

            if ($data['saveAccount'] ?? false) {
                $user->update([
                    'payout_bank' => $data['payoutBank'],
                    'payout_account_number' => $data['accountNumber'],
                    'payout_account_name' => $data['accountName'],
                ]);
            }

            return Withdrawal::create([
                'withdrawal_code' => 'WD-' . now()->format('ymd') . '-' . strtoupper(Str::random(6)),
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'payout_bank' => $data['payoutBank'],
                'payout_account_number' => $data['accountNumber'],
                'payout_account_name' => $data['accountName'],
                'status' => 'pending',
            ]);
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'data' => $this->present($result, false),
            'summary' => [
                'availableBalance' => $availableBalanceService->forUser((int) $request->user()->id),
                'minimumAmount' => self::MINIMUM_AMOUNT,
            ],
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireRole($request, 'admin')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'action' => ['required', Rule::in(['approve', 'reject', 'mark_paid'])],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($id, $validator): JsonResponse {
            $withdrawal = Withdrawal::query()->lockForUpdate()->find($id);
            if (! $withdrawal) {
                return response()->json(['error' => 'Permintaan penarikan tidak ditemukan.'], 404);
            }

            $data = $validator->validated();
            $now = now();
            $updates = match ($data['action']) {
                'approve' => $withdrawal->status === 'pending'
                    ? ['status' => 'approved', 'reviewed_at' => $now, 'rejection_reason' => null]
                    : null,
                'reject' => $withdrawal->status === 'pending'
                    ? ['status' => 'rejected', 'reviewed_at' => $now, 'rejection_reason' => $data['reason']]
                    : null,
                'mark_paid' => $withdrawal->status === 'approved'
                    ? [
                        'status' => 'paid',
                        'paid_at' => $now,
                        'transfer_reference' => $data['reference'] ?? null,
                    ]
                    : null,
            };

            if ($updates === null) {
                return response()->json([
                    'error' => 'Aksi tidak sesuai dengan status penarikan saat ini.',
                ], 409);
            }

            $withdrawal->update($updates);
            $withdrawal->load('user');

            return response()->json(['data' => $this->present($withdrawal, true)]);
        });
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireRole($request, 'user')) {
            return $response;
        }

        return DB::transaction(function () use ($request, $id): JsonResponse {
            $withdrawal = Withdrawal::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->find($id);
            if (! $withdrawal) {
                return response()->json(['error' => 'Permintaan penarikan tidak ditemukan.'], 404);
            }
            if ($withdrawal->status !== 'pending') {
                return response()->json([
                    'error' => 'Penarikan hanya dapat dibatalkan sebelum diperiksa admin.',
                ], 409);
            }

            $withdrawal->update(['status' => 'cancelled']);

            return response()->json(['data' => $this->present($withdrawal, false)]);
        });
    }

    private function present(Withdrawal $withdrawal, bool $includeAccountNumber): array
    {
        return [
            'id' => (int) $withdrawal->id,
            'code' => $withdrawal->withdrawal_code,
            'userName' => $withdrawal->user?->name,
            'amount' => (int) $withdrawal->amount,
            'payoutBank' => $withdrawal->payout_bank,
            'accountNumber' => $includeAccountNumber ? $withdrawal->payout_account_number : $this->maskAccountNumber($withdrawal->payout_account_number),
            'accountName' => $withdrawal->payout_account_name,
            'status' => $withdrawal->status,
            'rejectionReason' => $withdrawal->rejection_reason,
            'transferReference' => $withdrawal->transfer_reference,
            'createdAt' => $withdrawal->created_at?->toIso8601String(),
            'reviewedAt' => $withdrawal->reviewed_at?->toIso8601String(),
            'paidAt' => $withdrawal->paid_at?->toIso8601String(),
        ];
    }

    private function maskAccountNumber(string $accountNumber): string
    {
        return str_repeat('•', max(0, strlen($accountNumber) - 4)) . substr($accountNumber, -4);
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
