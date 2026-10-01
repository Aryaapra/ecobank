<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\WasteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WasteTypeController extends Controller
{
    private const CATEGORIES = ['Plastik', 'Kertas', 'Logam', 'Kaca'];

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => WasteType::query()->where('is_active', true)
                ->orderBy('category')->orderBy('name')->get(['id', 'name', 'category', 'price']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        $data = $this->validatedItem($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        if (WasteType::where('category', $data['category'])->where('name', $data['name'])->exists()) {
            return response()->json(['error' => 'Jenis sampah tersebut sudah terdaftar.'], 409);
        }

        $item = WasteType::create($data);

        return response()->json(['data' => $item->only(['id', 'name', 'category', 'price'])], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        $item = WasteType::where('is_active', true)->find($id);
        if (! $item) {
            return response()->json(['error' => 'Jenis sampah tidak ditemukan.'], 404);
        }

        $data = $this->validatedItem($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $duplicate = WasteType::where('category', $data['category'])
            ->where('name', $data['name'])->whereKeyNot($item->id)->exists();
        if ($duplicate) {
            return response()->json(['error' => 'Jenis sampah tersebut sudah terdaftar.'], 409);
        }

        $item->update($data);

        return response()->json(['data' => $item->only(['id', 'name', 'category', 'price'])]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireAdmin($request)) {
            return $response;
        }

        return DB::transaction(function () use ($id): JsonResponse {
            $item = WasteType::where('is_active', true)->lockForUpdate()->find($id);
            if (! $item) {
                return response()->json(['error' => 'Jenis sampah tidak ditemukan.'], 404);
            }

            $count = Transaction::where('waste_type_id', $id)->count();
            if ($count > 0) {
                return response()->json([
                    'error' => "{$item->name} tidak dapat dihapus karena sudah dipakai {$count} transaksi. Riwayat transaksi harus dipertahankan.",
                ], 409);
            }

            $item->delete();

            return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
        });
    }

    private function validatedItem(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'price' => ['required', 'integer', 'min:1'],
        ]);

        return $validator->fails()
            ? response()->json(['error' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
            : $validator->validated();
    }

    private function requireAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }

        return $request->user()->role === 'admin'
            ? null
            : response()->json(['error' => 'Akses khusus admin.'], 403);
    }
}
