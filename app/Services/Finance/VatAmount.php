<?php

namespace App\Services\Finance;

final readonly class VatAmount
{
    public function __construct(
        public int $netHalalas,
        public int $vatHalalas,
        public int $grossHalalas,
    ) {}
}
