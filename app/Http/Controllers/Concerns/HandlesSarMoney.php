<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait HandlesSarMoney
{
    /**
     * @return list<string>
     */
    private function sarMoneyRules(): array
    {
        return ['required', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];
    }

    /**
     * @return list<string>
     */
    private function positiveSarMoneyRules(): array
    {
        return ['required', 'numeric', 'min:0.01', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];
    }

    private function mergeSarFromHalalas(Request $request, string $sarKey, string $halalasKey): void
    {
        if (! $request->has($sarKey) && $request->has($halalasKey)) {
            $request->merge([
                $sarKey => $this->halalasToSarString((int) $request->input($halalasKey, 0)),
            ]);
        }
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
