<?php

namespace Tests\Feature;

use App\Mail\KiwiSaverContactUsEmail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KiwiSaverContactApiTest extends TestCase
{
    private const API_KEY = 'test-mobile-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.api_key' => self::API_KEY,
            'cache.default' => 'array',
        ]);
        Mail::fake();
    }

    public function test_it_sends_a_valid_enquiry_to_the_kiwisaver_mailbox(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/kiwisaver-contact', $this->validPayload())
            ->assertOk()
            ->assertJson(['message' => 'Mail Sent']);

        Mail::assertSent(KiwiSaverContactUsEmail::class, function ($mail): bool {
            return $mail->hasTo('kiwisaver@halalkiwi.com')
                && $mail->request->email === 'amina+kiwi@example.co.nz';
        });
    }

    public function test_it_rejects_invalid_email_data_without_sending_mail(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/kiwisaver-contact', array_merge(
                $this->validPayload(),
                ['email' => 'not-an-email'],
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_it_requires_explicit_consent(): void
    {
        $this->withHeader('X-API-Key', self::API_KEY)
            ->postJson('/api/kiwisaver-contact', array_merge(
                $this->validPayload(),
                ['consent' => '0'],
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consent');

        Mail::assertNothingSent();
    }

    public function test_it_requires_the_mobile_api_key(): void
    {
        $this->postJson('/api/kiwisaver-contact', $this->validPayload())
            ->assertUnauthorized();

        Mail::assertNothingSent();
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'first_name' => 'Amina',
            'last_name' => 'Khan',
            'email' => 'amina+kiwi@example.co.nz',
            'heard_about' => 'Halal Kiwi',
            'mailing_list' => '1',
            'consent' => '1',
        ];
    }
}
