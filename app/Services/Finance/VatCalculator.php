<?php

namespace App\Services\Finance;

class VatCalculator
{
    public function fromExclusive(int $netHalalas, int $vatRate): VatAmount
    {
        $vat = (int) round($netHalalas * ($vatRate / 100));

        return new VatAmount($netHalalas, $vat, $netHalalas + $vat);
    }

    public function fromInclusive(int $grossHalalas, int $vatRate): VatAmount
    {
        $net = (int) round($grossHalalas / (1 + ($vatRate / 100)));

        return new VatAmount($net, $grossHalalas - $net, $grossHalalas);
    }
}
