<?php

namespace Tests\Feature;

use App\Mail\UserNotificationEmail;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Models\RequestWatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequestsAutoProcessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('Barcode');
            $table->string('product_name')->nullable();
            $table->string('halal_status')->default('2');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('prioritisation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20);
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('status')->default('pending');
            $table->tinyInteger('resolved_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('request_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id');
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_dry_run_does_not_resolve_or_notify(): void
    {
        Mail::fake();
        $request = $this->resolvedProductRequest();

        $exitCode = Artisan::call('requests:auto-process', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('pending', $request->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_it_resolves_known_products_and_skips_placeholder_notifications(): void
    {
        Mail::fake();
        $request = $this->resolvedProductRequest('customer@example.com');
        $duplicate = PrioritisationRequest::create([
            'barcode' => $request->barcode,
            'product_name' => $request->product_name,
            'status' => 'contacted',
        ]);
        RequestWatcher::create(['request_id' => $request->id, 'user_email' => 'customer@example.com']);
        RequestWatcher::create(['request_id' => $duplicate->id, 'user_email' => 'anonymous@halalkiwi.com']);

        $exitCode = Artisan::call('requests:auto-process');

        $this->assertSame(0, $exitCode);
        $this->assertSame('resolved', $request->fresh()->status);
        $this->assertSame(0, $request->fresh()->resolved_status);
        $this->assertSame('resolved', $duplicate->fresh()->status);
        $this->assertStringContainsString('Auto-resolved', $request->fresh()->notes);
        Mail::assertSent(UserNotificationEmail::class, 1);
        Mail::assertSent(UserNotificationEmail::class, function (UserNotificationEmail $mail) {
            return $mail->hasTo('customer@example.com') && $mail->halalStatus === '0';
        });
    }

    public function test_it_leaves_unreviewed_products_pending(): void
    {
        Product::create([
            'Barcode' => '9400000000002',
            'product_name' => 'Unreviewed product',
            'halal_status' => '2',
        ]);
        $request = PrioritisationRequest::create([
            'barcode' => '9400000000002',
            'product_name' => 'Unreviewed product',
            'status' => 'pending',
        ]);

        Artisan::call('requests:auto-process');

        $this->assertSame('pending', $request->fresh()->status);
    }

    private function resolvedProductRequest(?string $email = null): PrioritisationRequest
    {
        Product::create([
            'Barcode' => '9400000000001',
            'product_name' => 'Reviewed product',
            'halal_status' => '0',
        ]);

        return PrioritisationRequest::create([
            'barcode' => '9400000000001',
            'product_name' => 'Reviewed product',
            'user_email' => $email,
            'status' => 'pending',
        ]);
    }
}
