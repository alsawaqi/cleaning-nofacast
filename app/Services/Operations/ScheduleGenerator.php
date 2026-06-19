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
        $assignments = $contract->assignments()->with('service')->where('status', 'active')->get();

        foreach ($assignments as $assignment) {
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                if ($date->dayOfWeekIso !== $assignment->weekday) {
                    continue;
                }

                $visit = Visit::firstOrCreate([
                    'assignment_id' => $assignment->id,
                    'scheduled_for' => $date->toDateString(),
                ], [
                    'contract_id' => $contract->id,
                    'worker_id' => $assignment->worker_id,
                    'customer_site_id' => $assignment->customer_site_id,
                    'service_id' => $assignment->service_id,
                    'starts_at' => $assignment->starts_at,
                    'ends_at' => $assignment->ends_at,
                    'status' => 'scheduled',
                ]);

                if ($visit->checklist_items_count === null) {
                    $visit->loadCount('checklistItems');
                }

                if ($visit->checklist_items_count === 0) {
                    foreach (($assignment->service?->checklist_template ?? []) as $item) {
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
