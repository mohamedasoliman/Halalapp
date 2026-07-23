<?php

namespace Tests\Unit;

use App\Support\HalalStatus;
use PHPUnit\Framework\TestCase;

class HalalStatusTest extends TestCase
{
    public function test_all_supported_statuses_have_labels(): void
    {
        $this->assertSame(['0', '1', '2', '3'], HalalStatus::values());
        $this->assertSame('Halal', HalalStatus::label('0'));
        $this->assertSame('Not Halal', HalalStatus::label('1'));
        $this->assertSame('Unreviewed', HalalStatus::label('2'));
        $this->assertSame('Mashbooh', HalalStatus::label('3'));
    }

    public function test_unknown_status_falls_back_to_unreviewed(): void
    {
        $this->assertSame('Unreviewed', HalalStatus::label('99'));
        $this->assertSame('label-warning', HalalStatus::badgeClass('99'));
    }
}
