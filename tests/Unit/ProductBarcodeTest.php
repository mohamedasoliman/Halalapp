<?php

namespace Tests\Unit;

use App\Support\ProductBarcode;
use PHPUnit\Framework\TestCase;

class ProductBarcodeTest extends TestCase
{
    public function test_it_canonicalises_equivalent_gtin_formats(): void
    {
        $this->assertSame('078895743050', ProductBarcode::canonical('78895743050'));
        $this->assertSame('078895743050', ProductBarcode::canonical('0078895743050'));
        $this->assertSame('93519441', ProductBarcode::canonical('0000093519441'));
        $this->assertSame('9310012060284', ProductBarcode::canonical('09310012060284'));
    }

    public function test_it_preserves_invalid_and_placeholder_barcodes(): void
    {
        $this->assertSame('0', ProductBarcode::canonical('0'));
        $this->assertSame('12345678', ProductBarcode::canonical('12345678'));
        $this->assertNull(ProductBarcode::key('000'));
        $this->assertSame('ABC', ProductBarcode::key('0ABC'));
    }

    public function test_it_validates_supported_gtin_lengths_and_check_digits(): void
    {
        $this->assertTrue(ProductBarcode::isValidGtin('9310036040385'));
        $this->assertTrue(ProductBarcode::isValidGtin('93519441'));
        $this->assertFalse(ProductBarcode::isValidGtin('9310036040384'));
        $this->assertFalse(ProductBarcode::isValidGtin('123'));
        $this->assertFalse(ProductBarcode::isValidGtin('not-a-barcode'));
    }
}
