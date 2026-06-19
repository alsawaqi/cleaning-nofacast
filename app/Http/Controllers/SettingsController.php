<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => SystemSetting::allKeyed(),
            'schema' => $this->schema(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company\.name' => ['required', 'string', 'max:160'],
            'company\.vat_number' => ['nullable', 'string', 'max:32'],
            'operations\.default_city' => ['required', 'string', 'max:80'],
            'operations\.working_week' => ['required', 'array', 'min:1'],
            'operations\.working_week.*' => ['required', Rule::in(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'])],
            'finance\.vat_rate' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $changes = SystemSetting::putMany($validated);

        if ($changes !== []) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'settings.updated',
                'auditable_type' => 'system_settings',
                'changes' => $changes,
            ]);
        }

        return redirect('/app/settings')->with('success', __('Settings updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'weekdays' => [
                ['value' => 'sun', 'label' => 'Sunday'],
                ['value' => 'mon', 'label' => 'Monday'],
                ['value' => 'tue', 'label' => 'Tuesday'],
                ['value' => 'wed', 'label' => 'Wednesday'],
                ['value' => 'thu', 'label' => 'Thursday'],
                ['value' => 'fri', 'label' => 'Friday'],
                ['value' => 'sat', 'label' => 'Saturday'],
            ],
            'vatRates' => [0, 5, 15],
        ];
    }
}
