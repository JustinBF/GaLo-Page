<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Top 3 de jugadores (por CE) y de organizadores (por CO).
 */
class PodiumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['players', 'organizers'])],
            'period' => ['nullable', Rule::in(['all', 'month', 'year'])],
        ]);

        $scope = $data['scope'];
        $period = $data['period'] ?? 'all';
        $currency = $scope === 'players' ? 'CE' : 'CO';

        $members = $period === 'all'
            ? $this->allTime($scope, $currency)
            : $this->forPeriod($scope, $currency, $period);

        return response()->json([
            'scope' => $scope,
            'period' => $period,
            'currency' => $currency,
            'podium' => $members,
        ]);
    }

    /**
     * Ranking histórico: usa el saldo actual.
     *
     * @return array<int, array<string, mixed>>
     */
    private function allTime(string $scope, string $currency): array
    {
        $column = $currency === 'CE' ? 'ce_balance' : 'co_balance';
        $flag = $scope === 'players' ? 'is_player' : 'is_organizer';

        $members = Member::query()
            ->with(['rank', 'organizerRank'])
            ->where($flag, true)
            ->where('is_active', true)
            ->where($column, '>', 0)
            ->orderByDesc($column)
            ->orderBy('nick')
            ->limit(3)
            ->get();

        return $members
            ->values()
            ->map(fn (Member $member, int $index) => $this->payload(
                $member,
                $index + 1,
                $member->{$column},
                $scope,
            ))
            ->all();
    }

    /**
     * Ranking del mes o del año: suma solo lo ganado en la ventana.
     *
     * Los gastos en la tienda no restan aquí: el podio premia lo conseguido,
     * no lo que queda sin gastar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function forPeriod(string $scope, string $currency, string $period): array
    {
        $since = $period === 'month'
            ? Carbon::now()->startOfMonth()
            : Carbon::now()->startOfYear();

        $flag = $scope === 'players' ? 'is_player' : 'is_organizer';

        $totals = CreditTransaction::query()
            ->where('currency', $currency)
            ->where('amount', '>', 0)
            ->where('created_at', '>=', $since)
            ->whereIn('reason', ['event_win', 'event_organized', 'manual_adjust'])
            ->selectRaw('member_id, SUM(amount) as total')
            ->groupBy('member_id')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'member_id');

        if ($totals->isEmpty()) {
            return [];
        }

        $members = Member::query()
            ->with(['rank', 'organizerRank'])
            ->whereIn('id', $totals->keys())
            ->where($flag, true)
            ->where('is_active', true)
            ->get()
            ->sortByDesc(fn (Member $member) => (int) $totals[$member->id])
            ->take(3);

        return $members
            ->values()
            ->map(fn (Member $member, int $index) => $this->payload(
                $member,
                $index + 1,
                (int) $totals[$member->id],
                $scope,
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Member $member, int $position, int $score, string $scope): array
    {
        $rank = $scope === 'players' ? $member->rank : $member->organizerRank;

        return [
            'position' => $position,
            'id' => $member->id,
            'nick' => $member->nick,
            'score' => $score,
            'has_avatar' => $member->hasAvatar(),
            'avatar_version' => $member->updated_at?->timestamp,
            'rank' => $rank ? [
                'id' => $rank->id,
                'name' => $rank->name,
                'color_hex' => $rank->color_hex,
                'has_icon' => $rank->icon_mime !== null,
            ] : null,
        ];
    }
}
