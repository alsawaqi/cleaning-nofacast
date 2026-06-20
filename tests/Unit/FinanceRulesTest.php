<?php

namespace Tests\Unit;

use App\Services\Finance\VatCalculator;
use App\Services\Finance\ZatcaQrCode;
use PHPUnit\Framework\TestCase;

class FinanceRulesTest extends TestCase
{
    public function test_exclusive_vat_calculates_net_tax_and_total(): void
    {
        $calculator = new VatCalculator;

        $amounts = $calculator->fromExclusive(1000, 15);

        $this->assertSame(1000, $amounts->netHalalas);
        $this->assertSame(150, $amounts->vatHalalas);
        $this->assertSame(1150, $amounts->grossHalalas);
    }

    public function test_inclusive_vat_splits_net_and_tax_without_losing_halalas(): void
    {
        $calculator = new VatCalculator;

        $amounts = $calculator->fromInclusive(1150, 15);

        $this->assertSame(1000, $amounts->netHalalas);
        $this->assertSame(150, $amounts->vatHalalas);
        $this->assertSame(1150, $amounts->grossHalalas);
    }

    public function test_zatca_qr_payload_uses_tag_length_value_base64_encoding(): void
    {
        $qr = new ZatcaQrCode;

        $payload = $qr->generate(
            sellerName: 'Nofa Clean',
            vatNumber: '300000000000003',
            issuedAt: '2026-06-14T12:00:00+03:00',
            totalWithVat: '115.00',
            vatTotal: '15.00',
        );

        $decoded = base64_decode($payload, strict: true);

        $this->assertIsString($decoded);
        $this->assertStringContainsString('Nofa Clean', $decoded);
        $this->assertStringContainsString('300000000000003', $decoded);
        $this->assertStringContainsString('2026-06-14T12:00:00+03:00', $decoded);
        $this->assertStringContainsString('115.00', $decoded);
        $this->assertStringContainsString('15.00', $decoded);
        $this->assertSame(1, ord($decoded[0]));
        $this->assertSame(strlen('Nofa Clean'), ord($decoded[1]));
    }
}
