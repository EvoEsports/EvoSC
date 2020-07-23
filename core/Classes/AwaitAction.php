<?php


namespace EvoSC\Classes;


use EvoSC\Models\Player;
use Illuminate\Support\Collection;

class AwaitAction
{
    const ACTION_CONFIRM = 0;
    const ACTION_INPUT = 1;

    private static Collection $queue;

    private string $id;
    private Player $player;
    private string $message;
    private $closure;
    private int $type;
    private string $response;
    private int $time_queued;

    /**
     * AwaitAction constructor.
     * @param Player $player
     * @param string $message
     * @param $closure
     * @param int $type
     */
    public function __construct(Player $player, string $message, $closure, int $type = self::ACTION_CONFIRM)
    {
        $this->id = uniqid();
        $this->time_queued = time();
        $this->player = $player;
        $this->message = $message;
        $this->closure = $closure;
        $this->type = $type;
        $this->response = '';
    }

    /**
     *
     */
    public static function createQueueAndStartCheckCycle()
    {
        self::$queue = collect();

        ManiaLinkEvent::add('await_action.respond', [self::class, 'mleRespond']);

        Timer::create('check_await_queue', [self::class, 'checkQueue'], '5m', true);
    }

    /**
     * @param Player $player
     * @param string $message
     * @param $closure
     * @param int $type
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function add(Player $player, string $message, $closure, int $type = self::ACTION_CONFIRM)
    {
        $contract = new AwaitAction($player, $message, $closure, $type);
        self::$queue->put($contract->getId(), $contract);
        Template::show($player, 'Dialogues.wrapper', ['type' => 'confirm', 'message' => $message, 'id' => $contract->getId()]);
    }

    /**
     * @param Player $player
     * @param $response
     */
    public static function mleRespond(Player $player, $response)
    {
        if (self::$queue->has($response)) {
            self::$queue->get($response)->execute();
            self::$queue->forget($response);
        }
    }

    /**
     *
     */
    public static function checkQueue()
    {
        $queueItems = self::$queue;
        foreach ($queueItems as $item){
            if($item->tooOld()){
                self::$queue->forget($item->getId());
            }
        }
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function tooOld()
    {
        return time() - $this->time_queued > 600; //mark all awaits older than 10min as too old
    }

    /**
     *
     */
    public function execute()
    {
        call_user_func_array($this->closure, []);
    }
}