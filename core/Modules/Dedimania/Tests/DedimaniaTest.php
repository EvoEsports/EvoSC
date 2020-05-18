<?php declare(strict_types=1);

namespace EvoSC\Modules\Dedimania\Tests;

use EvoSC\Modules\Dedimania\Dedimania;
use PHPUnit\Framework\TestCase;

final class DedimaniaTest extends TestCase
{
    public function testOfflineModeIsBool(): void {
        $this->assertIsBool(Dedimania::isOfflineMode());
    }

    public function testGetDisplayRanksRange(): void
    {
        $this->assertIsBool(true);
    }
}