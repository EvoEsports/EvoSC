<?php


namespace EvoSC\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedule';

    protected $guarded = ['id'];

    /**
     * @param Player $player
     * @param string $title
     * @param Carbon|string $executeAt
     * @param string $event
     * @param mixed ...$arguments
     * @return static
     */
    public static function maniaLinkEvent(Player $player, string $title, $executeAt, string $event, ...$arguments): self
    {
        $datetime = is_string($executeAt) ? $executeAt : $executeAt->toDateTimeString();

        $task = self::create([
            'title'        => $title,
            'event'        => $event,
            'arguments'    => serialize($arguments),
            'execute_at'   => $datetime,
            'scheduled_by' => $player->id
        ]);

        successMessage($player, ' scheduled ', secondary($title), ' for ', secondary($datetime))->sendAdmin();

        return $task;
    }
}