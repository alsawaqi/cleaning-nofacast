<?php

namespace App\Services\Operations;

use App\Models\Assignment;
use App\Models\BookingRequest;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Visit;
use App\Models\Worker;
use Illuminate\Support\Carbon;

class BookingAvailability
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function calendar(Service $service, ?ServicePackage $package, Carbon $from, int $days = 14): array
    {
        return collect(range(0, max(1, $days) - 1))
            ->map(fn (int $offset): array => $this->day($service, $package, $from->copy()->addDays($offset)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function day(Service $service, ?ServicePackage $package, Carbon $date): array
    {
        return [
            'date' => $date->toDateString(),
            'weekday' => $date->dayName,
            'slots' => collect($this->slotStarts())
                ->map(fn (string $startsAt): array => $this->slot($service, $package, $date, $startsAt))
                ->filter(fn (array $slot): bool => $slot['within_workday'])
                ->values()
                ->all(),
        ];
    }

    public function isSlotAvailable(Service $service, ?ServicePackage $package, Carbon $date, string $startsAt, ?int $ignoreBookingRequestId = null): bool
    {
        [$endsAt, $durationMinutes, $requiredWorkers] = $this->window($service, $package, $startsAt);

        if (! $this->withinWorkday($startsAt, $endsAt)) {
            return false;
        }

        return $this->availableWorkers($date, $startsAt, $endsAt, $ignoreBookingRequestId) >= $requiredWorkers
            && $durationMinutes > 0;
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    public function window(Service $service, ?ServicePackage $package, string $startsAt): array
    {
        $durationMinutes = $this->durationMinutes($service, $package);
        $requiredWorkers = $this->requiredWorkers($service, $package);
        $endsAt = Carbon::createFromFormat('H:i', substr($startsAt, 0, 5))
            ->addMinutes($durationMinutes)
            ->format('H:i');

        return [$endsAt, $durationMinutes, $requiredWorkers];
    }

    /**
     * @return array<string, mixed>
     */
    private function slot(Service $service, ?ServicePackage $package, Carbon $date, string $startsAt): array
    {
        [$endsAt, $durationMinutes, $requiredWorkers] = $this->window($service, $package, $startsAt);
        $withinWorkday = $this->withinWorkday($startsAt, $endsAt);
        $availableWorkers = $withinWorkday
            ? $this->availableWorkers($date, $startsAt, $endsAt)
            : 0;

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'label' => "{$startsAt} - {$endsAt}",
            'duration_minutes' => $durationMinutes,
            'required_workers' => $requiredWorkers,
            'available_workers' => $availableWorkers,
            'available' => $withinWorkday && $availableWorkers >= $requiredWorkers,
            'within_workday' => $withinWorkday,
        ];
    }

    /**
     * @return list<string>
     */
    private function slotStarts(): array
    {
        return ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
    }

    private function durationMinutes(Service $service, ?ServicePackage $package): int
    {
        return max(30, (int) ($package?->duration_minutes ?: $service->default_duration_minutes ?: $service->minimum_billable_minutes ?: 120));
    }

    private function requiredWorkers(Service $service, ?ServicePackage $package): int
    {
        return max(1, (int) ($package?->worker_count ?: $service->default_workers ?: 1));
    }

    private function withinWorkday(string $startsAt, string $endsAt): bool
    {
        return $startsAt >= '08:00' && $endsAt <= '20:00' && $endsAt > $startsAt;
    }

    private function availableWorkers(Carbon $date, string $startsAt, string $endsAt, ?int $ignoreBookingRequestId = null): int
    {
        $workerIds = Worker::query()
            ->whereIn('status', ['available', 'assigned'])
            ->pluck('id');

        if ($workerIds->isEmpty()) {
            return 0;
        }

        $bookedWorkerIds = collect()
            ->merge($this->assignmentWorkerIds($date, $startsAt, $endsAt))
            ->merge($this->visitWorkerIds($date, $startsAt, $endsAt))
            ->unique()
            ->values();

        $freeWorkers = $workerIds
            ->reject(fn (int $workerId): bool => $bookedWorkerIds->contains($workerId))
            ->count();

        return max(0, $freeWorkers - $this->reservedWorkers($date, $startsAt, $endsAt, $ignoreBookingRequestId));
    }

    /**
     * @return list<int>
     */
    private function assignmentWorkerIds(Carbon $date, string $startsAt, string $endsAt): array
    {
        return Assignment::query()
            ->where('weekday', $date->dayOfWeekIso)
            ->where('status', 'active')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->pluck('worker_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function visitWorkerIds(Carbon $date, string $startsAt, string $endsAt): array
    {
        return Visit::query()
            ->whereDate('scheduled_for', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'missed'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->pluck('worker_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function reservedWorkers(Carbon $date, string $startsAt, string $endsAt, ?int $ignoreBookingRequestId): int
    {
        return (int) BookingRequest::query()
            ->whereDate('requested_for', $date->toDateString())
            ->whereIn('status', ['pending', 'approved'])
            ->when($ignoreBookingRequestId, fn ($query) => $query->where('id', '<>', $ignoreBookingRequestId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->sum('worker_count');
    }
}
