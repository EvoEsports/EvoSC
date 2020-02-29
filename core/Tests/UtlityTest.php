<?php


namespace esc\Tests;


use esc\Classes\Utlity;
use PHPUnit\Framework\TestCase;

class UtlityTest extends TestCase
{
    public function testGetRangRange()
    {
        $this->assertEquals([4, 17], Utlity::getRankRange(6, 3, 16, 100));
        $this->assertEquals([6, 19], Utlity::getRankRange(12, 3, 16, 100));
        $this->assertEquals([4, 8], Utlity::getRankRange(7, 3, 16, 8));
        $this->assertEquals([18, 31], Utlity::getRankRange(24, 3, 16, 100));
        $this->assertEquals([12, 25], Utlity::getRankRange(24, 3, 16, 25));
        $this->assertEquals([12, 25], Utlity::getRankRange(24, 3, 16, 25));
        $this->assertEquals([12, 25], Utlity::getRankRange(25, 3, 16, 25));
        $this->assertEquals([4, 3], Utlity::getRankRange(2, 3, 16, 3)); //
        $this->assertEquals([4, 17], Utlity::getRankRange(1, 3, 16, 24));
        $this->assertEquals([4, 17], Utlity::getRankRange(3, 3, 16, 24));
        $this->assertEquals([86, 99], Utlity::getRankRange(92, 3, 16, 100));
        $this->assertEquals([87, 100], Utlity::getRankRange(93, 3, 16, 100));
        $this->assertEquals([87, 100], Utlity::getRankRange(94, 3, 16, 100));
        $this->assertEquals([87, 100], Utlity::getRankRange(99, 3, 16, 100));
        $this->assertNotEquals([4, 17], Utlity::getRankRange(4, 3, 16, 3));
    }
}