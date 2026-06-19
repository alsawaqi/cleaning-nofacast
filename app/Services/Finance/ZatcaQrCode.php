<?php

namespace App\Services\Finance;

class ZatcaQrCode
{
    public function generate(
        string $sellerName,
        string $vatNumber,
        string $issuedAt,
        string $totalWithVat,
        string $vatTotal,
    ): string {
        return base64_encode(
            $this->tag(1, $sellerName).
            $this->tag(2, $vatNumber).
            $this->tag(3, $issuedAt).
            $this->tag(4, $totalWithVat).
            $this->tag(5, $vatTotal)
        );
    }

    private function tag(int $tag, string $value): string
    {
        return chr($tag).chr(strlen($value)).$value;
    }
}
