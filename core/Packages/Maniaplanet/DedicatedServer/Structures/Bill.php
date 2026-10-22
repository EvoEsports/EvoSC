<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class Bill extends AbstractStructure
{
    const STATE_CREATING_TRANSACTION = 1;
    const STATE_ISSUED = 2;
    const STATE_VALIDATING_PAYMENT = 3;
    const STATE_PAYED = 4;
    const STATE_REFUSED = 5;
    const STATE_ERROR = 6;

    /** @var int */
    public $state;
    /** @var string */
    public $stateName;
    /** @var int */
    public $transactionId;
}
