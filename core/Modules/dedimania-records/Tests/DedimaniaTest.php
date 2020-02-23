<?php declare(strict_types=1);

use esc\Classes\Database;
use esc\Classes\Log;
use esc\Controllers\ConfigController;
use esc\Modules\Dedimania;
use PHPUnit\Framework\TestCase;

final class DedimaniaTest extends TestCase
{
    public function testGetDisplayRanksRange(): void
    {
        ConfigController::init();
        Database::init();
        var_dump(Dedimania::getRanksRange(11));

        $this->assertIsBool(true);
    }
}