<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MonthlyRankingService
{
    public function build(?int $userId = null): array
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonth();
        $activeStatuses = ['pending', 'pickup_confirmed', 'weighing'];
        $eligibleTransactions = static function () use ($periodStart, $periodEnd, $activeStatuses) {
            return DB::table('transactions')
                ->join('users', 'users.id', '=', 'transactions.user_id')
                ->where('users.role', 'user')
                ->whereIn('transactions.status', [...$activeStatuses, 'approved'])
                ->where('transactions.created_at', '>=', $periodStart)
                ->where('transactions.created_at', '<', $periodEnd);
        };

        $rankings = $eligibleTransactions()
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(transactions.weight_kg) AS weight_kg'),
                DB::raw("SUM(CASE WHEN transactions.status <> 'approved' THEN transactions.weight_kg ELSE 0 END) AS pending_weight_kg"),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('weight_kg')
            ->orderBy('users.name')
            ->limit(100)
            ->get()
            ->values()
            ->map(function (object $entry, int $index): array {
                $name = trim($entry->name) ?: 'Kontributor';
                $weight = (float) $entry->weight_kg;
                $pendingWeight = (float) $entry->pending_weight_kg;

                return [
                    'id' => (int) $entry->id,
                    'rank' => $index + 1,
                    'name' => $name,
                    'initials' => mb_strtoupper(mb_substr($name, 0, 1)),
                    'weight' => $weight,
                    'weight_label' => $this->formatWeight($weight),
                    'has_pending' => $pendingWeight > 0,
                    'pending_weight' => $pendingWeight,
                    'pending_weight_label' => $this->formatWeight($pendingWeight),
                ];
            });

        $wasteRankings = $eligibleTransactions()
            ->join('waste_types', 'waste_types.id', '=', 'transactions.waste_type_id')
            ->select('waste_types.name', DB::raw('SUM(transactions.weight_kg) AS weight_kg'))
            ->groupBy('waste_types.name')
            ->orderByDesc('weight_kg')
            ->orderBy('waste_types.name')
            ->limit(4)
            ->get()
            ->map(function (object $entry): array {
                $weight = (float) $entry->weight_kg;

                return [
                    'name' => $entry->name,
                    'weight' => $weight,
                    'weight_label' => $this->formatWeight($weight),
                ];
            });

        $totalContributors = $eligibleTransactions()->distinct()->count('users.id');
        $totalWeight = (float) $eligibleTransactions()->sum('transactions.weight_kg');
        $materialCount = $eligibleTransactions()->distinct()->count('transactions.waste_type_id');
        $highestWeight = (float) ($rankings->first()['weight'] ?? 0);
        $currentUserWeight = $userId === null
            ? 0.0
            : (float) $eligibleTransactions()->where('transactions.user_id', $userId)->sum('transactions.weight_kg');
        $currentUserPendingWeight = $userId === null
            ? 0.0
            : (float) $eligibleTransactions()
                ->where('transactions.user_id', $userId)
                ->whereIn('transactions.status', $activeStatuses)
                ->sum('transactions.weight_kg');
        $currentUserRanking = $rankings->firstWhere('id', $userId);

        return [
            'rankings' => $rankings,
            'topRankings' => $rankings->keyBy('rank'),
            'totalContributors' => $totalContributors,
            'totalWeightLabel' => $this->formatWeight($totalWeight),
            'highestWeightLabel' => $this->formatWeight($highestWeight),
            'materialCount' => $materialCount,
            'wasteRankings' => $wasteRankings,
            'currentUser' => [
                'hasContribution' => $currentUserWeight > 0,
                'rank' => $currentUserRanking['rank'] ?? null,
                'weight' => $currentUserWeight,
                'weight_label' => $this->formatWeight($currentUserWeight),
                'hasPending' => $currentUserPendingWeight > 0,
                'pendingWeightLabel' => $this->formatWeight($currentUserPendingWeight),
            ],
        ];
    }

    private function formatWeight(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, ',', '.'), '0'), ',');
    }
}
