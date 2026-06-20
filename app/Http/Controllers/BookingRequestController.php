<?php

namespace App\Http\Controllers;

use App\Models\BookingRequest;
use App\Services\Operations\BookingAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingRequestController extends Controller
{
    public function approve(Request $request, BookingRequest $bookingRequest, BookingAvailability $availability): RedirectResponse
    {
        $bookingRequest->load(['service', 'servicePackage']);

        if ($bookingRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'booking_request' => __('Only pending booking requests can be approved.'),
            ]);
        }

        if (! $availability->isSlotAvailable(
            $bookingRequest->service,
            $bookingRequest->servicePackage,
            Carbon::parse($bookingRequest->requested_for),
            substr((string) $bookingRequest->starts_at, 0, 5),
            $bookingRequest->id,
        )) {
            throw ValidationException::withMessages([
                'booking_request' => __('This booking slot is no longer available. Choose another time with the customer.'),
            ]);
        }

        $bookingRequest->forceFill([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'admin_note' => $request->input('admin_note', $bookingRequest->admin_note),
        ])->save();

        return redirect("/app/contracts/create?booking_request_id={$bookingRequest->id}")
            ->with('success', __('Booking request approved. Complete the contract wizard to create the agreement.'));
    }

    public function reject(Request $request, BookingRequest $bookingRequest): RedirectResponse
    {
        if (! in_array($bookingRequest->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages([
                'booking_request' => __('This booking request cannot be rejected.'),
            ]);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $bookingRequest->forceFill([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? null,
            'rejected_at' => now(),
        ])->save();

        return back()->with('success', __('Booking request rejected.'));
    }
}
