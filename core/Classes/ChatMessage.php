<?php

namespace EvoSC\Classes;


use EvoSC\Controllers\ChatController;
use EvoSC\Models\Map;
use EvoSC\Models\Player;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Class ChatMessage
 *
 * Create and send chat/info/warning messages.
 *
 * @package EvoSC\Classes
 */
class ChatMessage
{
    private array $parts;
    private $color;
    private $icon;

    /**
     * ChatMessage constructor.
     *
     * Parts can be strings, numbers, player/group/map/local/etc-objects (most objects are formatted automatically).
     *
     * @param mixed ...$message
     */
    public function __construct(...$message)
    {
        $this->color = config('theme.chat.info');
        $this->parts = $message;
    }

    /**
     * Set the primary color of the chat message.
     *
     * @param string $color
     *
     * @return ChatMessage
     */
    public function setColor(string $color): ChatMessage
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Set the icon of the chat-message, can contain color-code.
     *
     * @param string $icon
     *
     * @return ChatMessage
     */
    public function setIcon(string $icon): ChatMessage
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set info-color and icon on chat-message.
     *
     * @return ChatMessage
     */
    public function setIsInfoMessage(): ChatMessage
    {
        $this->color = config('theme.chat.info');
        $this->icon = '';

        return $this;
    }

    /**
     * Set warning-color and icon on chat-message.
     *
     * @return ChatMessage
     */
    public function setIsWarning(): ChatMessage
    {
        $this->color = config('theme.chat.warning');
        $this->icon = '';

        return $this;
    }

    /**
     * @return $this
     */
    public function setIsDanger(): ChatMessage
    {
        $this->color = config('theme.chat.danger');
        $this->icon = '';

        return $this;
    }

    /**
     * @return $this
     */
    public function setIsSuccess(): ChatMessage
    {
        $this->color = config('theme.chat.success');
        $this->icon = '';

        return $this;
    }

    /**
     * Overwrite the chat-message content.
     *
     * @param mixed ...$parts
     *
     * @return ChatMessage
     */
    public function setParts(...$parts): ChatMessage
    {
        $this->parts = $parts;

        return $this;
    }

    /**
     * Get the formatted message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        $parts = '';
        foreach ($this->parts as $part) {
            if ($part instanceof Player || $part instanceof Map || is_numeric($part) || preg_match('/(\d:)?\d{2}\.\d{3}/', "$part")) {
                $parts .= secondary("\$<$part\$>");
                continue;
            }

            if ($part instanceof stdClass) {
                $parts .= secondary('#stdClass');
                continue;
            }

            $parts .= $part;
        }

        $message = ($this->icon ? '$fff' . $this->icon . ' ' : '') . sprintf('$%s%s', $this->color, $parts);

        return '$z$s' . preg_replace('/(?:(?<=[^$])\$s|^\$s)/i', '', $message) . '$z';
    }

    /**
     * Send the message to everyone.
     */
    public function sendAll()
    {
        $message = $this->getMessage();
        ChatController::sendServerMessage($message, collect(Server::getPlayerList())->pluck('login'));
        Hook::fire('ChatLine', $message);

        if (isVerbose()) {
            Log::info($message);
        }
    }

    /**
     * Send the message to everyone with the admin_echoes access-right.
     */
    public function sendAdmin()
    {
        $this->setIcon('$666');
        $message = $this->getMessage();

        ChatController::sendServerMessage($message, echoPlayers()->pluck('Login'));
        Hook::fire('ChatLine', $message);

        if (isVerbose()) {
            Log::info($message);
        }
    }

    /**
     * Send the message to a specific player.
     *
     * @param mixed $player
     */
    public function send($player = null)
    {
        if (!$player) {
            return;
        }

        $message = $this->getMessage();

        try {
            if ($player instanceof Player) {
                ChatController::sendServerMessage($message, collect([$player->Login]));
                Log::info("(@$player)" . $message, isVerbose());
            } else if (is_string($player)) {
                ChatController::sendServerMessage($message, collect([$player]));
                Log::info("(@$player)" . $message, isVerbose());
            } else if ($player instanceof Collection) {
                ChatController::sendServerMessage($message, $player->pluck('Login'));
                Log::info($message, isVerbose());
            }
        } catch (\Exception $e) {
            Log::warningWithCause('Failed to deliver message', $e);
        }
    }
}
