<?php

namespace App\Services\Operations;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScheduleGenerator
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createAssignment(array $attributes): Assignment
    {
        $availabilityErrors = $this->availabilityErrors(
            (int) $attributes['worker_id'],
            (int) $attributes['weekday'],
            (string) $attributes['starts_at'],
            (string) $attributes['ends_at'],
        );

        if ($availabilityErrors !== []) {
            throw ValidationException::withMessages($availabilityErrors);
        }

        return Assignment::create($attributes);
    }

    /**
     * @return array<string, string>
     */
    public function availabilityErrors(int $workerId, int $weekday, string $startsAt, string $endsAt): array
    {
        if (! $this->workerHasOverlap($workerId, $weekday, $startsAt, $endsAt)) {
            return [];
        }

        return [
            'worker_id' => __('This worker is already assigned during the selected time.'),
        ];
    }

    public function generateVisits(Contract $contract, Carbon $from, Carbon $to): Collection
    {
        $created = collect();
        $assignments = $contract->assignments()
            ->with('service')
            ->where('status', 'active')
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->orderByRaw("CASE WHEN team_role = 'main' THEN 0 ELSE 1 END")
            ->get();

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dailySlots = $assignments
                ->where('weekday', $date->dayOfWeekIso)
                ->groupBy(fn (Assignment $assignment): string => $this->assignmentSlotKey($assignment));

            foreach ($dailySlots as $slotAssignments) {
                $mainAssignment = $this->mainAssignment($slotAssignments);

                $visit = Visit::firstOrCreate([
                    'assignment_id' => $mainAssignment->id,
                    'scheduled_for' => $date->toDateString(),
                ], [
                    'contract_id' => $contract->id,
                    'worker_id' => $mainAssignment->worker_id,
                    'customer_site_id' => $mainAssignment->customer_site_id,
                    'service_id' => $mainAssignment->service_id,
                    'starts_at' => $mainAssignment->starts_at,
                    'ends_at' => $mainAssignment->ends_at,
                    'status' => 'scheduled',
                ]);

                if ($visit->checklist_items_count === null) {
                    $visit->loadCount('checklistItems');
                }

                if ($visit->checklist_items_count === 0) {
                    foreach ($this->teamChecklistTemplate($slotAssignments, $mainAssignment) as $item) {
                        $visit->checklistItems()->create([
                            'label' => $item['label'],
                            'is_required' => $item['is_required'] ?? true,
                        ]);
                    }
                }

                $created->push($visit->load('checklistItems'));
            }
        }

        return $created;
    }

    /**
     * @return list<array{label: string, is_required?: bool}>
     */
    private function teamChecklistTemplate(Collection $assignments, Assignment $mainAssignment): array
    {
        $assignmentTasks = $assignments
            ->flatMap(fn (Assignment $assignment): array => $this->assignmentTaskTemplate($assignment))
            ->values()
            ->all();

        if ($assignmentTasks !== []) {
            return $assignmentTasks;
        }

        return $mainAssignment->service?->checklist_template ?? [];
    }

    /**
     * @return list<array{label: string, is_required?: bool}>
     */
    private function assignmentTaskTemplate(Assignment $assignment): array
    {
        return collect($assignment->task_instructions ?? [])
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    $label = trim($item);

                    return $label === '' ? null : ['label' => $label, 'is_required' => true];
                }

                if (! is_array($item)) {
                    return null;
                }

                $label = trim((string) ($item['label'] ?? ''));

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'is_required' => (bool) ($item['is_required'] ?? true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function assignmentSlotKey(Assignment $assignment): string
    {
        return implode('|', [
            $assignment->weekday,
            $this->timeKey($assignment->starts_at),
            $this->timeKey($assignment->ends_at),
            $assignment->service_id ?? 'none',
        ]);
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    private function mainAssignment(Collection $assignments): Assignment
    {
        return $assignments->firstWhere('team_role', 'main') ?? $assignments->first();
    }

    private function timeKey(mixed $time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        return substr((string) $time, 0, 5);
    }

    private function workerHasOverlap(int $workerId, int $weekday, string $startsAt, string $endsAt): bool
    {
        return Assignment::query()
            ->where('worker_id', $workerId)
            ->where('weekday', $weekday)
            ->where('status', 'active')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }
}
