<?php

namespace App\Http\Controllers;

use App\Services\MonthlyRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __invoke(MonthlyRankingService $monthlyRanking): View
    {
        return view('peringkat', $monthlyRanking->build());
    }

    public function data(Request $request, MonthlyRankingService $monthlyRanking): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Silakan masuk ke akun EcoBank terlebih dahulu.'], 401);
        }

        if (! in_array($request->user()->role, ['admin', 'user'], true)) {
            return response()->json(['error' => 'Akses tidak diizinkan.'], 403);
        }

        return response()->json([
            'data' => $monthlyRanking->build($request->user()->id),
        ]);
    }
}
