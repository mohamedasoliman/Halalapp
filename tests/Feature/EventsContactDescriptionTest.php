<?php

namespace Tests\Feature;

use App\Mail\EventsContactUsEmail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventsContactDescriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api_key' => 'test-mobile-key', 'cache.default' => 'array']);
        Mail::fake();
    }

    public function test_event_description_uuid_and_app_metadata_reach_only_the_events_mail(): void
    {
        $this->withHeader('X-API-Key', 'test-mobile-key')->postJson('/api/events-contact-us', [
            'subject' => 'Community dinner',
            'email' => 'organiser@example.com',
            'contact' => '021 123 456',
            'eventName' => 'Community dinner',
            'date' => '2026-09-01',
            'time' => '18:00',
            'address' => '1 Example Street',
            'link' => 'https://example.com/event',
            'description' => 'A family-friendly community meal.',
            'submission_uuid' => '3dd93e10-c7da-4f11-921d-fe4144db5fcf',
            'category' => 'event_submission',
            'context_type' => 'event',
            'context_id' => 'Community dinner',
            'platform' => 'ios',
            'app_version' => '10.3.0',
            'app_build' => '200',
        ])->assertOk();

        Mail::assertSent(EventsContactUsEmail::class, function (EventsContactUsEmail $mail) {
            return $mail->hasTo('events@halalkiwi.com')
                && ! $mail->hasTo('appsupport@halalkiwi.com')
                && str_contains($mail->render(), 'A family-friendly community meal.')
                && str_contains($mail->render(), '3dd93e10-c7da-4f11-921d-fe4144db5fcf')
                && str_contains($mail->render(), 'event Community dinner')
                && str_contains($mail->render(), '021 123 456');
        });
    }

    public function test_legacy_event_payload_without_date_time_or_address_remains_accepted(): void
    {
        $this->withHeader('X-API-Key', 'test-mobile-key')->postJson('/api/events-contact-us', [
            'subject' => 'Legacy event',
            'email' => 'organiser@example.com',
            'eventName' => 'Legacy event',
            'description' => 'Details supplied in the description.',
        ])->assertOk();

        Mail::assertSent(EventsContactUsEmail::class, fn (EventsContactUsEmail $mail) => $mail->hasTo('events@halalkiwi.com'));
    }
}
