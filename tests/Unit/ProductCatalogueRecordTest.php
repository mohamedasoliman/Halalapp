<?php

namespace Tests\Unit;

use App\Support\ProductCatalogueRecord;
use PHPUnit\Framework\TestCase;

class ProductCatalogueRecordTest extends TestCase
{
    public function test_it_keeps_identity_fields_and_discards_external_assessment_fields(): void
    {
        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Milk',
            'brand' => 'Mooloo Mountain',
            'origin' => 'Australia',
            'main_category' => 'Dairy',
            'ingredients' => '<p>Cows milk</p>',
            'main_image' => 'https://www.mustakshif.com/public/uploads/products/milk.jpg',
            'type' => 'halal',
            'status' => 'approved',
            'locked_type' => 'halal',
            'change_reason' => 'External assessment',
        ]);

        $this->assertNotNull($record);
        $this->assertSame('Mooloo Mountain Milk', $record['product_name']);
        $this->assertSame('Mooloo Mountain', $record['brand']);
        $this->assertSame('Australia', $record['country']);
        $this->assertSame('Dairy', $record['category']);
        $this->assertSame('Cows milk', $record['ingredient']);
        $this->assertSame(
            'https://www.mustakshif.com/public/uploads/products/milk.jpg',
            $record['image_download_url']
        );
        $this->assertArrayNotHasKey('type', $record);
        $this->assertArrayNotHasKey('status', $record);
        $this->assertArrayNotHasKey('change_reason', $record);
        $this->assertArrayNotHasKey('source', $record);
        $this->assertArrayNotHasKey('imported_at', $record);
    }

    public function test_it_rejects_invalid_barcodes_and_generic_names(): void
    {
        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040384',
            'name' => 'Milk',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Product',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9400563455629',
            'name' => 'Health Star Rating 4',
        ]));
    }

    public function test_import_rows_cannot_store_remote_paths(): void
    {
        $record = ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Mooloo Mountain Milk',
            'product_image' => 'https://example.com/product.jpg',
        ]);

        $this->assertNotNull($record);
        $this->assertNull($record['product_image']);
    }

    public function test_import_rows_reapply_identity_cleanup(): void
    {
        $record = ProductCatalogueRecord::fromImportRow([
            'barcode' => '000790430070',
            'product_name' => "Welch's, Healthy Food Brands Llc Welch's Trail Mix (product not available)",
            'brand' => "Welch's, Healthy Food Brands Llc",
            'country' => 'United States, United States',
        ]);

        $this->assertNotNull($record);
        $this->assertSame("Welch's Trail Mix", $record['product_name']);
        $this->assertSame("Welch's", $record['brand']);
        $this->assertSame('United States', $record['country']);
    }

    public function test_it_removes_catalogue_quality_annotations_from_names(): void
    {
        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '00000116',
            'name' => 'Mini Biscuits With Chia And Quinoa-ingredients missing',
        ]);

        $this->assertNotNull($record);
        $this->assertSame('Mini Biscuits With Chia And Quinoa', $record['product_name']);
    }

    public function test_it_collapses_repeated_by_brand_values(): void
    {
        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '00000383',
            'name' => 'Cauliflower',
            'brand' => "Sainsbury's,by sainsbury's",
        ]);

        $this->assertNotNull($record);
        $this->assertSame("Sainsbury's Cauliflower", $record['product_name']);
        $this->assertSame("Sainsbury's", $record['brand']);
    }

    public function test_it_cleans_unavailable_suffixes_and_duplicate_countries(): void
    {
        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9300601010899',
            'name' => 'Australian Apricot Halves (product not available)',
            'brand' => 'Coles, Coles Group',
            'origin' => 'Australia, Australia',
        ]);

        $this->assertNotNull($record);
        $this->assertSame('Coles Australian Apricot Halves', $record['product_name']);
        $this->assertSame('Coles', $record['brand']);
        $this->assertSame('Australia', $record['country']);
    }
}
