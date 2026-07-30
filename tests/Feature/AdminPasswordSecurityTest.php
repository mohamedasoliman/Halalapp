<?php

namespace Tests\Feature;

use App\Admin;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPasswordSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('role_id')->default(1);
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_csrf_token_cannot_be_used_as_an_admin_password_reset_token(): void
    {
        $admin = $this->admin();
        $originalPassword = $admin->password;

        $response = $this->post(route('admin.password.request'), [
            'token' => 'attacker-known-csrf-token',
            'email' => $admin->email,
            'password' => 'SecureReplacement123',
            'password_confirmation' => 'SecureReplacement123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame($originalPassword, $admin->fresh()->password);
    }

    public function test_admin_reset_uses_a_hashed_one_time_broker_token(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->post(route('admin.password.emails'), [
            'email' => $admin->email,
        ])->assertSessionHas('success');

        $notification = null;
        Notification::assertSentTo(
            $admin,
            AdminResetPasswordNotification::class,
            function (AdminResetPasswordNotification $sent) use (&$notification): bool {
                $notification = $sent;

                return true;
            }
        );

        $storedToken = DB::table('password_reset_tokens')
            ->where('email', $admin->email)
            ->value('token');

        $this->assertNotNull($notification);
        $this->assertNotSame($notification->token, $storedToken);
        $this->assertTrue(Hash::check($notification->token, $storedToken));

        $this->post(route('admin.password.request'), [
            'token' => $notification->token,
            'email' => $admin->email,
            'password' => 'SecureReplacement123',
            'password_confirmation' => 'SecureReplacement123',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('SecureReplacement123', $admin->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $admin->email]);
    }

    public function test_reset_request_does_not_reveal_whether_an_admin_exists(): void
    {
        Notification::fake();

        $response = $this->post(route('admin.password.emails'), [
            'email' => 'missing-admin@example.com',
        ]);

        $response->assertSessionHas(
            'success',
            'If an active administrator account exists for that address, a reset link has been sent.'
        );
        Notification::assertNothingSent();
    }

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Security Admin',
            'email' => 'security-admin@example.com',
            'password' => Hash::make('ExistingPassword123'),
            'role_id' => 1,
            'status' => 1,
        ]);
    }
}
