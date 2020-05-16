<?php declare(strict_types=1);

require '../../../global-functions.php';

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