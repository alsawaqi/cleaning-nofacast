<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceRequestController extends Controller
{
    public function approve(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        if ($serviceRequest->type !== 'reschedule') {
            throw ValidationException::withMessages([
                'service_request' => __('Only reschedule requests can be approved.'),
            ]);
        }

        if (! in_array($serviceRequest->status, ['pending', 'in_review'], true)) {
            throw ValidationException::withMessages([
                'service_request' => __('This request cannot be approved.'),
            ]);
        }

        $serviceRequest->load('visit');

        if (! $serviceRequest->visit || ! $serviceRequest->requested_for || ! $serviceRequest->starts_at || ! $serviceRequest->ends_at) {
            throw ValidationException::withMessages([
                'service_request' => __('The reschedule request is missing visit timing details.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceRequest->visit->forceFill([
            'scheduled_for' => $serviceRequest->requested_for->toDateString(),
            'starts_at' => $this->time($serviceRequest->starts_at),
            'ends_at' => $this->time($serviceRequest->ends_at),
            'status' => 'scheduled',
        ])->save();

        $serviceRequest->forceFill([
            'status' => 'approved',
            'admin_note' => $validated['admin_note'] ?? $serviceRequest->admin_note,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'resolved_at' => now(),
        ])->save();

        return back()->with('success', __('Reschedule request approved and visit updated.'));
    }

    public function reject(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        if (! in_array($serviceRequest->status, ['pending', 'in_review'], true)) {
            throw ValidationException::withMessages([
                'service_request' => __('This request cannot be rejected.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceRequest->forceFill([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? $serviceRequest->admin_note,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'resolved_at' => now(),
        ])->save();

        return back()->with('success', __('Customer request rejected.'));
    }

    public function markInReview(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        if (! in_array($serviceRequest->status, ['pending', 'in_review'], true)) {
            throw ValidationException::withMessages([
                'service_request' => __('This request cannot be moved to review.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceRequest->forceFill([
            'status' => 'in_review',
            'admin_note' => $validated['admin_note'] ?? $serviceRequest->admin_note,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        return back()->with('success', __('Customer request marked in review.'));
    }

    public function resolve(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        if (! in_array($serviceRequest->type, ['support_ticket', 'quality_follow_up'], true)) {
            throw ValidationException::withMessages([
                'service_request' => __('Only support tickets and quality follow-ups can be resolved manually.'),
            ]);
        }

        if (! in_array($serviceRequest->status, ['pending', 'in_review'], true)) {
            throw ValidationException::withMessages([
                'service_request' => __('This support ticket cannot be resolved.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceRequest->forceFill([
            'status' => 'resolved',
            'admin_note' => $validated['admin_note'] ?? $serviceRequest->admin_note,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => $serviceRequest->reviewed_at ?? now(),
            'resolved_at' => now(),
        ])->save();

        return back()->with('success', __('Support ticket resolved.'));
    }

    private function time(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
