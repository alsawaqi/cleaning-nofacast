<?php

namespace App\Services\Operations;

use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Support\Carbon;

class VisitExecutionService
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function start(Visit $visit, ?Carbon $checkedInAt = null, array $evidence = []): Visit
    {
        $visit->forceFill([
            'status' => 'in_progress',
            'checked_in_at' => $checkedInAt ?? now(),
            'check_in_latitude' => $evidence['check_in_latitude'] ?? null,
            'check_in_longitude' => $evidence['check_in_longitude'] ?? null,
            'check_in_accuracy_meters' => $evidence['check_in_accuracy_meters'] ?? null,
            'planned_minutes' => $this->durationMinutes($visit->starts_at, $visit->ends_at),
            'overtime_status' => 'not_required',
        ])->save();

        return $visit;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function finish(Visit $visit, array $data): Visit
    {
        $visit->loadMissing(['contract', 'service', 'worker']);

        $checkedInAt = isset($data['checked_in_at'])
            ? Carbon::parse($data['checked_in_at'])
            : ($visit->checked_in_at ?? now());
        $checkedOutAt = isset($data['checked_out_at'])
            ? Carbon::parse($data['checked_out_at'])
            : now();
        $plannedMinutes = $this->durationMinutes($visit->starts_at, $visit->ends_at);
        $actualMinutes = (int) ($data['actual_minutes'] ?? $this->actualMinutes($checkedInAt, $checkedOutAt));
        $varianceMinutes = $actualMinutes - $plannedMinutes;
        $overtimeMinutes = max(0, $varianceMinutes);
        $requestedOvertimeStatus = (string) ($data['overtime_status'] ?? 'pending_approval');
        $overtimeStatus = $overtimeMinutes > 0
            ? ($requestedOvertimeStatus === 'not_required' ? 'pending_approval' : $requestedOvertimeStatus)
            : 'not_required';
        $billableOvertimeMinutes = $overtimeStatus === 'approved' ? $overtimeMinutes : 0;
        $materialsUsed = $this->normalizeMaterialsUsed($data['materials_used'] ?? []);
        $materialCostHalalas = $this->materialRowsTotal($materialsUsed);
        $plannedRevenueHalalas = $this->plannedVisitRevenue($visit);
        $laborCostHalalas = $this->laborCost($visit->worker, $actualMinutes);
        $billableOvertimeHalalas = $this->billableOvertime($visit, $billableOvertimeMinutes);
        $grossProfitHalalas = $plannedRevenueHalalas + $billableOvertimeHalalas - $laborCostHalalas - $materialCostHalalas;

        $visit->forceFill([
            'status' => 'completed',
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
            'check_out_latitude' => $data['check_out_latitude'] ?? null,
            'check_out_longitude' => $data['check_out_longitude'] ?? null,
            'check_out_accuracy_meters' => $data['check_out_accuracy_meters'] ?? null,
            'completion_review_status' => 'pending_review',
            'completion_reviewed_at' => null,
            'completion_reviewed_by' => null,
            'completion_review_note' => null,
            'planned_minutes' => $plannedMinutes,
            'actual_minutes' => $actualMinutes,
            'variance_minutes' => $varianceMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'billable_overtime_minutes' => $billableOvertimeMinutes,
            'overtime_status' => $overtimeStatus,
            'materials_used' => $materialsUsed,
            'planned_revenue_halalas' => $plannedRevenueHalalas,
            'labor_cost_halalas' => $laborCostHalalas,
            'material_cost_halalas' => $materialCostHalalas,
            'billable_overtime_halalas' => $billableOvertimeHalalas,
            'gross_profit_halalas' => $grossProfitHalalas,
            'execution_notes' => $data['execution_notes'] ?? null,
            'photos' => collect($data['photos'] ?? [])->filter()->values()->all(),
        ])->save();

        return $visit;
    }

    public function durationMinutes(?string $startsAt, ?string $endsAt): int
    {
        if (! $startsAt || ! $endsAt) {
            return 0;
        }

        $start = Carbon::createFromFormat('H:i:s', strlen($startsAt) === 5 ? $startsAt.':00' : $startsAt);
        $end = Carbon::createFromFormat('H:i:s', strlen($endsAt) === 5 ? $endsAt.':00' : $endsAt);

        return max(0, (int) $start->diffInMinutes($end));
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     * @return list<array{name: string, quantity: string|null, cost_halalas: int, cost_sar: string}>
     */
    public function normalizeMaterialsUsed(array $materials): array
    {
        return collect($materials)
            ->map(function (array $item): array {
                $costHalalas = isset($item['cost_halalas'])
                    ? (int) $item['cost_halalas']
                    : $this->sarToHalalas($item['cost_sar'] ?? '0.00');

                return [
                    'name' => trim((string) ($item['name'] ?? '')),
                    'quantity' => filled($item['quantity'] ?? null) ? trim((string) $item['quantity']) : null,
                    'cost_halalas' => $costHalalas,
                    'cost_sar' => $this->halalasToSarString($costHalalas),
                ];
            })
            ->filter(fn (array $item): bool => $item['name'] !== '')
            ->values()
            ->all();
    }

    private function actualMinutes(Carbon $checkedInAt, Carbon $checkedOutAt): int
    {
        return max(0, (int) $checkedInAt->diffInMinutes($checkedOutAt));
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     */
    private function materialRowsTotal(array $materials): int
    {
        return collect($materials)
            ->sum(fn (array $item): int => (int) ($item['cost_halalas'] ?? 0));
    }

    private function plannedVisitRevenue(Visit $visit): int
    {
        if ($visit->contract) {
            $visitsPerWeek = max(1, (int) ($visit->contract->visits_per_week ?: 1));

            return (int) round($visit->contract->monthly_fee_halalas / max(1, $visitsPerWeek * 4));
        }

        return (int) ($visit->service?->base_price_halalas ?? 0);
    }

    private function laborCost(?Worker $worker, int $actualMinutes): int
    {
        if (! $worker || $actualMinutes === 0) {
            return 0;
        }

        $monthlyWorkingMinutes = 26 * 8 * 60;

        return (int) round(($worker->cost_rate_halalas / $monthlyWorkingMinutes) * $actualMinutes);
    }

    private function billableOvertime(Visit $visit, int $billableOvertimeMinutes): int
    {
        if ($billableOvertimeMinutes === 0) {
            return 0;
        }

        $rate = (int) ($visit->contract?->extra_hour_rate_halalas ?: $visit->service?->extra_hour_rate_halalas ?: 0);

        return (int) round(($rate / 60) * $billableOvertimeMinutes);
    }

    private function sarToHalalas(string|int|float $value): int
    {
        $normalized = trim((string) $value);
        [$riyals, $halalas] = array_pad(explode('.', $normalized, 2), 2, '0');

        return (((int) $riyals) * 100) + (int) str_pad(substr($halalas, 0, 2), 2, '0');
    }

    private function halalasToSarString(int $halalas): string
    {
        return intdiv($halalas, 100).'.'.str_pad((string) ($halalas % 100), 2, '0', STR_PAD_LEFT);
    }
}
