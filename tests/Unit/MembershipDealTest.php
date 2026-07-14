<?php

namespace Tests\Unit;

use App\Support\MembershipDeal;
use PHPUnit\Framework\TestCase;

class MembershipDealTest extends TestCase
{
    public function test_it_limits_deals_by_membership_tier(): void
    {
        $deals = array_map(
            fn (int $number): array => ['title' => "Deal {$number}"],
            range(1, 6)
        );

        $this->assertCount(0, MembershipDeal::normalise($deals, 'free'));
        $this->assertCount(1, MembershipDeal::normalise($deals, 'starter'));
        $this->assertCount(3, MembershipDeal::normalise($deals, 'growth'));
        $this->assertCount(5, MembershipDeal::normalise($deals, 'premium'));
    }

    public function test_it_migrates_legacy_fields_and_mirrors_the_first_deal(): void
    {
        $record = [
            'DealTitle' => 'Lunch special',
            'DealDescription' => 'Weekdays only',
            'DealCode' => 'LUNCH20',
            'DealExpiry' => '2030-01-31',
        ];

        $deals = MembershipDeal::fromRecord($record, 'starter');
        MembershipDeal::applyToRecord($record, $deals, 'starter');

        $this->assertCount(1, $record['Deals']);
        $this->assertSame('Lunch special', $record['Deals'][0]['Title']);
        $this->assertSame('LUNCH20', $record['Deals'][0]['Code']);
        $this->assertSame('Lunch special', $record['DealTitle']);
        $this->assertSame('2030-01-31', $record['DealExpiry']);
    }

    public function test_it_removes_deals_from_free_listings(): void
    {
        $record = [
            'Deals' => [['Title' => 'Not entitled']],
            'DealTitle' => 'Not entitled',
        ];

        MembershipDeal::applyToRecord(
            $record,
            MembershipDeal::fromRecord($record, 'free'),
            'free'
        );

        $this->assertArrayNotHasKey('Deals', $record);
        $this->assertArrayNotHasKey('DealTitle', $record);
    }
}
