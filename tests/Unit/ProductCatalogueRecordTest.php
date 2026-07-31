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

    public function test_it_rejects_non_food_categories_and_names(): void
    {
        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Fresh Lemon Dishwashing Liquid 900ml',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Fresh Lemon',
            'main_category' => 'Household',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Bright White Bleach',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Mega Ultra Household Towels',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => "Palmer's Cocoa Butter Formula",
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Cocoa Butter Skin Therapy Oil',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Dove Body Love Pampering Care Lotion',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Old Spice',
        ]));

        $this->assertNull(ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Dr Bronners Pure Castille Soap Green Tea',
        ]));

        foreach ([
            'Anti-bacterial Cleaner',
            'Dove Glowing Beauty Bar',
            'dove Sensitive Bar 3.75 oz, 16 Bars',
            'CeraVe Hydrating Cleanser 236ml',
            'Garnier Moisture Bomb',
            'Rimmel London Nail base & top coat nail Nurse',
            'loccitane sheah butter lip',
            "L’oréal L'Oreal Men Expert Total Clean Shower Gel Large XL 400ml",
            'Listerine Pocketpaks Fresh Breath Strips Cool Mint',
            "Bells Healthcare Allergy Relief Tablet's",
            'tesco hayfever relief tablets',
            'Ricola Sugar Free Lemon Mint Cough Drops',
            'alka-seltzer plus cold & flu',
            'Night Nurse Liquid For Colds & Flu 160 ml',
            'Relisan Alcohol Hand Gel - 500ml',
            'Asda Vitamin C Facial Gel Cleanser 150ml',
            'Pollinosan Hayfever Tablets',
            'dove Beauty Cream Bar Original 1 x 100g',
            'Chicken puree Applaws',
            'Tresemme Botanique',
            "Allen's Soothers Butter-menthol Throat Lozenge 3 Pack",
        ] as $name) {
            $this->assertNull(ProductCatalogueRecord::fromImportRow([
                'barcode' => '9310036040385',
                'product_name' => $name,
            ]), $name);
        }

        $this->assertNotNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Strong White Unbleached Bread Flour',
            'main_category' => 'Pantry',
        ]));
    }

    public function test_it_rejects_generic_names_and_brand_only_records(): void
    {
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('https://example.com/product'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('Test Product'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('Easter'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('Barcode is wrong'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('#NAME?'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('ts'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('tes5 2'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('Stabilité'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('muas'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('Gh Four'));
        $this->assertFalse(ProductCatalogueRecord::hasUsableName('Vegetarian, Vegan'));
        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => "Mama Lisa's",
        ]));
        $this->assertNull(ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Example Brand',
            'brand' => 'Example Brand',
        ]));
    }

    public function test_it_removes_additional_source_quality_suffixes_from_names(): void
    {
        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Wholegrain Large Wraps ingrediants missing',
        ]);

        $this->assertSame('Wholegrain Large Wraps', $record['product_name']);

        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Tomato Passata - Need More Information',
        ]);

        $this->assertSame('Tomato Passata', $record['product_name']);
    }

    public function test_it_rejects_generic_placeholder_image_urls(): void
    {
        $record = ProductCatalogueRecord::fromApiProduct([
            'barcode' => '9310036040385',
            'name' => 'Milk',
            'main_image' => 'https://admin.mustakshif.com/uploads/products/product.jpeg',
        ]);

        $this->assertNull($record['image_download_url']);
    }

    public function test_it_discards_corrupt_ingredient_text_without_rejecting_the_product(): void
    {
        $record = ProductCatalogueRecord::fromImportRow([
            'barcode' => '9310036040385',
            'product_name' => 'Brown Sugar Pastry',
            'ingredient' => 'Harina, vitamina B1, �cido f�lico',
        ]);

        $this->assertNotNull($record);
        $this->assertNull($record['ingredient']);
    }
}
