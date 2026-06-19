<?php

namespace App\Services\Support;

use Illuminate\Database\Eloquent\Model;

class NumberGenerator
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function next(string $prefix, string $modelClass, string $column = 'number'): string
    {
        $count = $modelClass::query()->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, now()->format('Y'), $count);
    }
}
