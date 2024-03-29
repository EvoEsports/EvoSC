<?php


namespace EvoSC\Tests;


use EvoSC\Classes\Utility;
use PHPUnit\Framework\TestCase;

class UtlityTest extends TestCase
{
    public function testGetRangRange()
    {
        $this->assertEquals([4, 17], Utility::getRankRange(6, 3, 16, 100));
        $this->assertEquals([6, 19], Utility::getRankRange(12, 3, 16, 100));
        $this->assertEquals([4, 8], Utility::getRankRange(7, 3, 16, 8));
        $this->assertEquals([18, 31], Utility::getRankRange(24, 3, 16, 100));
        $this->assertEquals([12, 25], Utility::getRankRange(24, 3, 16, 25));
        $this->assertEquals([12, 25], Utility::getRankRange(24, 3, 16, 25));
        $this->assertEquals([12, 25], Utility::getRankRange(25, 3, 16, 25));
        $this->assertEquals([4, 3], Utility::getRankRange(2, 3, 16, 3)); //
        $this->assertEquals([4, 17], Utility::getRankRange(1, 3, 16, 24));
        $this->assertEquals([4, 17], Utility::getRankRange(3, 3, 16, 24));
        $this->assertEquals([86, 99], Utility::getRankRange(92, 3, 16, 100));
        $this->assertEquals([87, 100], Utility::getRankRange(93, 3, 16, 100));
        $this->assertEquals([87, 100], Utility::getRankRange(94, 3, 16, 100));
        $this->assertEquals([87, 100], Utility::getRankRange(99, 3, 16, 100));
        $this->assertNotEquals([4, 17], Utility::getRankRange(4, 3, 16, 3));
    }

    public function testSetPropertyViaDotNotation()
    {
        $targetObject = (object)[
            'existing' => false,
            'nested' => (object)[
                'value' => 'nope'
            ]
        ];

        Utility::setPropertyViaDotNotation($targetObject, 'existing', true);
        Utility::setPropertyViaDotNotation($targetObject, 'unit.test', 'hey');
        Utility::setPropertyViaDotNotation($targetObject, 'nested.value', 'yep');
        Utility::setPropertyViaDotNotation($targetObject, 'array', [
            'hey' => 'there',
        ]);

        $this->assertTrue($targetObject->existing);
        $this->assertEquals('hey', $targetObject->unit->test);
        $this->assertEquals('yep', $targetObject->nested->value);
        $this->assertIsArray($targetObject->array);
        $this->assertEquals('there', $targetObject->array['hey']);
    }
}