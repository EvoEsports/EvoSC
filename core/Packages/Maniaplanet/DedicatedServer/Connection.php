<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer;

/**
 * Dedicated Server Connection Instance
 * Methods returns nothing if $multicall = true
 */
class Connection
{
    const API_2011_08_01 = '2011-08-01';
    const API_2011_10_06 = '2011-10-06';
    const API_2012_06_19 = '2012-06-19';
    const API_2013_04_16 = '2013-04-16';

    /** @var Connection[] */
    protected static $instances = array();
    /** @var int[] */
    private static $levels = array(
        null => -1,
        'User' => 0,
        'Admin' => 1,
        'SuperAdmin' => 2
    );
    /** @var Xmlrpc\GbxRemote */
    protected $xmlrpcClient;
    /** @var string */
    protected $user;
    /** @var callable[] */
    private $multicallHandlers = array();

    /**
     * @param string $host
     * @param int $port
     * @param int $timeout
     */
    protected function __construct($host, $port, $timeout)
    {
        $this->xmlrpcClient = new Xmlrpc\GbxRemote($host, $port, $timeout);
    }

    /**
     * @param string $host
     * @param int $port
     * @param int $timeout (in s)
     * @param string $user
     * @param string $password
     * @param string $apiVersion
     * @return Connection
     */
    static function factory($host = '127.0.0.1', $port = 5000, $timeout = 5, $user = 'SuperAdmin', $password = 'SuperAdmin', $apiVersion = self::API_2013_04_16)
    {
        $key = $host . ':' . $port;
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($host, $port, $timeout);
        }
        self::$instances[$key]->authenticate($user, $password);
        self::$instances[$key]->setApiVersion($apiVersion);

        return self::$instances[$key];
    }

    /**
     * Allow user authentication by specifying a login and a password, to gain access to the set of functionalities corresponding to this authorization level.
     * @param string $user
     * @param string $password
     * @return bool
     * @throws InvalidArgumentException
     */
    function authenticate($user, $password)
    {
        if (!is_string($user) || !isset(self::$levels[$user])) {
            throw new InvalidArgumentException('user = ' . print_r($user, true));
        }
        if (self::$levels[$this->user] >= self::$levels[$user]) {
            return true;
        }

        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        $res = $this->execute(ucfirst(__FUNCTION__), array($user, $password));
        if ($res) {
            $this->user = $user;
        }
        return $res;
    }

    /**
     * Add a call in queue. It will be executed by the next Call from the user to executeMulticall
     * @param string $methodName
     * @param mixed[] $params
     * @param bool|callable $multicall True to queue the request or false to execute it immediately
     * @return mixed
     */
    public function execute($methodName, $params = array(), $multicall = false)
    {
        if ($multicall) {
            $this->xmlrpcClient->addCall($methodName, $params);
            $this->multicallHandlers[] = $multicall;
        } else {
            return $this->xmlrpcClient->query($methodName, $params);
        }
    }

    /**
     * Define the wanted api.
     * @param string $version
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setApiVersion($version, $multicall = false)
    {
        if (!is_string($version)) {
            throw new InvalidArgumentException('version = ' . print_r($version, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($version), $multicall);
    }

    /**
     * @param Connection|string $hostOrConnection
     * @param int $port
     * @return bool
     */
    static function delete($hostOrConnection, $port = null)
    {
        if ($hostOrConnection instanceof Connection) {
            $key = array_search($hostOrConnection, self::$instances);
        } else {
            $key = $hostOrConnection . ':' . $port;
        }
        if (isset(self::$instances[$key])) {
            self::$instances[$key]->terminate();
            unset(self::$instances[$key]);
            return true;
        }
        return false;
    }

    /**
     * Close the current socket connexion
     * Never call this method, use instead DedicatedApi::delete($host, $port)
     */
    protected function terminate()
    {
        $this->xmlrpcClient->terminate();
    }

    /**
     * Change client timeouts
     * @param int $read read timeout (in ms), 0 to leave unchanged
     * @param int $write write timeout (in ms), 0 to leave unchanged
     */
    function setTimeouts($read = null, $write = null)
    {
        $this->xmlrpcClient->setTimeouts($read, $write);
    }

    /**
     * @return int Network idle time in seconds
     */
    function getIdleTime()
    {
        return $this->xmlrpcClient->getIdleTime();
    }

    /**
     * Return pending callbacks
     * @return mixed[]
     */
    function executeCallbacks()
    {
        return $this->xmlrpcClient->getCallbacks();
    }

    /**
     * Execute the calls in queue and return the result
     * @return mixed[]
     */
    function executeMulticall()
    {
        $responses = $this->xmlrpcClient->multiquery();
        foreach ($responses as $i => &$response) {
            if (!($response instanceof Xmlrpc\FaultException) && is_callable($this->multicallHandlers[$i])) {
                $response = call_user_func($this->multicallHandlers[$i], $response);
            }
        }
        $this->multicallHandlers = array();
        return $responses;
    }

    /**
     * Change the password for the specified login/user.
     * Only available to SuperAdmin.
     * @param string $user
     * @param string $password
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function changeAuthPassword($user, $password, $multicall = false)
    {
        if (!is_string($user) || !isset(self::$levels[$user])) {
            throw new InvalidArgumentException('user = ' . print_r($user, true));
        }
        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($user, $password), $multicall);
    }

    /**
     * Allow the GameServer to call you back.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function enableCallbacks($enable = true, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Returns a struct with the Name, TitleId, Version, Build and ApiVersion of the application remotely controlled.
     * @param bool $multicall
     * @return Structures\Version
     */
    function getVersion($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Version'));
        }
        return Structures\Version::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * @param string $struct
     * @param bool $array
     * @return callable
     */
    private function structHandler($struct, $array = false)
    {
        return array('\\' . __NAMESPACE__ . '\Structures\\' . $struct, 'fromArray' . ($array ? 'OfArray' : ''));
    }

    /**
     * Returns the current status of the server.
     * @param bool $multicall
     * @return Structures\Status
     */
    function getStatus($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Status'));
        }
        return Structures\Status::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Quit the application.
     * Only available to SuperAdmin.
     * @param bool $multicall
     * @return bool
     */
    function quitGame($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Call a vote to kick a player.
     * You can additionally supply specific parameters for this vote: a ratio, a time out and who is voting.
     * Only available to Admin.
     * @param mixed $player A player object or a login
     * @param float $ratio In range [0,1] or -1 for default ratio
     * @param int $timeout In milliseconds, 0 for default timeout, 1 for indefinite
     * @param int $voters 0: active players, 1: any player, 2: everybody including pure spectators
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function callVoteKick($player, $ratio = 0.5, $timeout = 0, $voters = 1, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        $vote = new Structures\Vote(Structures\VoteRatio::COMMAND_KICK, array($login));
        return $this->callVote($vote, $ratio, $timeout, $voters, $multicall);
    }

    /**
     * Returns the login of the given player
     * @param mixed $player
     * @return string|bool
     */
    private function getLogin($player, $allowEmpty = false)
    {
        if (is_object($player)) {
            if (property_exists($player, 'login')) {
                $player = $player->login;
            } else {
                return false;
            }
        }
        if (empty($player)) {
            return $allowEmpty ? '' : false;
        }
        if (is_string($player)) {
            return $player;
        }
        return false;
    }

    /**
     * Call a vote for a command.
     * You can additionally supply specific parameters for this vote: a ratio, a time out and who is voting.
     * Only available to Admin.
     * @param Structures\Vote $vote
     * @param float $ratio In range [0,1] or -1 for default ratio
     * @param int $timeout In milliseconds, 0 for default timeout, 1 for indefinite
     * @param int $voters 0: active players, 1: any player, 2: everybody including pure spectators
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function callVote($vote, $ratio = -1., $timeout = 0, $voters = 1, $multicall = false)
    {
        if (!($vote instanceof Structures\Vote && $vote->isValid())) {
            throw new InvalidArgumentException('vote = ' . print_r($vote, true));
        }
        if (!Structures\VoteRatio::isRatio($ratio)) {
            throw new InvalidArgumentException('ratio = ' . print_r($ratio, true));
        }
        if (!is_int($timeout)) {
            throw new InvalidArgumentException('timeout = ' . print_r($timeout, true));
        }
        if (!is_int($voters) || $voters < 0 || $voters > 2) {
            throw new InvalidArgumentException('voters = ' . print_r($voters, true));
        }

        $xml = Xmlrpc\Request::encode($vote->cmdName, $vote->cmdParam, false);
        return $this->execute(ucfirst(__FUNCTION__) . 'Ex', array($xml, $ratio, $timeout, $voters), $multicall);
    }

    /**
     * Call a vote to ban a player.
     * You can additionally supply specific parameters for this vote: a ratio, a time out and who is voting.
     * Only available to Admin.
     * @param mixed $player A player object or a login
     * @param float $ratio In range [0,1] or -1 for default ratio
     * @param int $timeout In milliseconds, 0 for default timeout, 1 for indefinite
     * @param int $voters 0: active players, 1: any player, 2: everybody including pure spectators
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function callVoteBan($player, $ratio = 0.6, $timeout = 0, $voters = 1, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        $vote = new Structures\Vote(Structures\VoteRatio::COMMAND_BAN, array($login));
        return $this->callVote($vote, $ratio, $timeout, $voters, $multicall);
    }

    /**
     * Call a vote to restart the current map.
     * You can additionally supply specific parameters for this vote: a ratio, a time out and who is voting.
     * Only available to Admin.
     * @param float $ratio In range [0,1] or -1 for default ratio
     * @param int $timeout In milliseconds, 0 for default timeout, 1 for indefinite
     * @param int $voters 0: active players, 1: any player, 2: everybody including pure spectators
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function callVoteRestartMap($ratio = 0.5, $timeout = 0, $voters = 1, $multicall = false)
    {
        $vote = new Structures\Vote(Structures\VoteRatio::COMMAND_RESTART_MAP);
        return $this->callVote($vote, $ratio, $timeout, $voters, $multicall);
    }

    /**
     * Call a vote to go to the next map.
     * You can additionally supply specific parameters for this vote: a ratio, a time out and who is voting.
     * Only available to Admin.
     * @param float $ratio In range [0,1] or -1 for default ratio
     * @param int $timeout In milliseconds, 0 for default timeout, 1 for indefinite
     * @param int $voters 0: active players, 1: any player, 2: everybody including pure spectators
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function callVoteNextMap($ratio = 0.5, $timeout = 0, $voters = 1, $multicall = false)
    {
        $vote = new Structures\Vote(Structures\VoteRatio::COMMAND_NEXT_MAP);
        return $this->callVote($vote, $ratio, $timeout, $voters, $multicall);
    }

    /**
     * Cancel the current vote.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function cancelVote($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the vote currently in progress.
     * @param $multicall
     * @return Structures\Vote
     */
    function getCurrentCallVote($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Vote'));
        }
        return Structures\Vote::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set a new timeout for waiting for votes.
     * Only available to Admin.
     * @param int $timeout In milliseconds, 0 to disable votes
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCallVoteTimeOut($timeout, $multicall = false)
    {
        if (!is_int($timeout)) {
            throw new InvalidArgumentException('timeout = ' . print_r($timeout, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($timeout), $multicall);
    }

    /**
     * Get the current and next timeout for waiting for votes.
     * @param $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getCallVoteTimeOut($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new default ratio for passing a vote.
     * Only available to Admin.
     * @param float $ratio In range [0,1] or -1 to disable votes
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCallVoteRatio($ratio, $multicall = false)
    {
        if (!Structures\VoteRatio::isRatio($ratio)) {
            throw new InvalidArgumentException('ratio = ' . print_r($ratio, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($ratio), $multicall);
    }

    /**
     * Get the current default ratio for passing a vote.
     * @param bool $multicall
     * @return float
     */
    function getCallVoteRatio($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * @deprecated
     * @see setCallVoteRatios()
     */
    function setCallVoteRatiosEx($replaceAll, $ratios, $multicall = false)
    {
        return $this->setCallVoteRatios($ratios, $replaceAll, $multicall);
    }

    /**
     * Set the ratios list for passing specific votes, extended version with parameters matching.
     * Only available to Admin.
     * @param Structures\VoteRatio[] $ratios
     * @param bool $replaceAll True to override the whole ratios list or false to modify only specified ratios
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCallVoteRatios($ratios, $replaceAll = true, $multicall = false)
    {
        if (!is_array($ratios)) {
            throw new InvalidArgumentException('ratios = ' . print_r($ratios, true));
        }
        foreach ($ratios as $i => &$ratio) {
            if (!($ratio instanceof Structures\VoteRatio && $ratio->isValid())) {
                throw new InvalidArgumentException('ratios[' . $i . '] = ' . print_r($ratios, true));
            }
            $ratio = $ratio->toArray();
        }
        if (!is_bool($replaceAll)) {
            throw new InvalidArgumentException('replaceAll = ' . print_r($replaceAll, true));
        }

        return $this->execute(ucfirst(__FUNCTION__) . 'Ex', array($replaceAll, $ratios), $multicall);
    }

    /**
     * @deprecated
     * @see getCallVoteRatios()
     */
    function getCallVoteRatiosEx($multicall = false)
    {
        return $this->getCallVoteRatios($multicall);
    }

    /**
     * Get the current ratios for passing votes, extended version with parameters matching.
     * @param bool $multicall
     * @return Structures\VoteRatio[]
     */
    function getCallVoteRatios($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__) . 'Ex', array(), $this->structHandler('VoteRatio', true));
        }
        return Structures\VoteRatio::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__) . 'Ex'));
    }

    /**
     * @deprecated
     * @see chatSendServerMessage()
     */
    function chatSendServerMessageToLanguage($messages, $recipient = null, $multicall = false)
    {
        return $this->chatSendServerMessage($messages, $recipient, $multicall);
    }

    /**
     * Send a text message, possibly localised to a specific login or to everyone, without the server login.
     * Only available to Admin.
     * @param string|string[][] $message Single string or array of structures {Lang='xx', Text='...'}:
     * if no matching language is found, the last text in the array is used
     * @param mixed $recipient Login, player object or array; null for all
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function chatSendServerMessage($message, $recipient = null, $multicall = false)
    {
        $logins = $this->getLogins($recipient, true);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }

        if (is_array($message)) {
            return $this->execute(ucfirst(__FUNCTION__) . 'ToLanguage', array($message, $logins), $multicall);
        }
        if (is_string($message)) {
            if ($logins) {
                return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($message, $logins), $multicall);
            }
            return $this->execute(ucfirst(__FUNCTION__), array($message), $multicall);
        }
        // else
        throw new InvalidArgumentException('message = ' . print_r($message, true));
    }

    /**
     * Returns logins of given players
     * @param mixed $players
     * @return string|bool
     */
    private function getLogins($players, $allowEmpty = false)
    {
        if (is_array($players)) {
            $logins = array();
            foreach ($players as $player) {
                $login = $this->getLogin($player);
                if ($login === false) {
                    return false;
                }
                $logins[] = $login;
            }

            return implode(',', $logins);
        }
        return $this->getLogin($players, $allowEmpty);
    }

    /**
     * @deprecated
     * @see chatSend()
     */
    function chatSendToLanguage($messages, $recipient = null, $multicall = false)
    {
        return $this->chatSend($messages, $recipient, $multicall);
    }

    /**
     * Send a text message, possibly localised to a specific login or to everyone.
     * Only available to Admin.
     * @param string|string[][] $message Single string or array of structures {Lang='xx', Text='...'}:
     * if no matching language is found, the last text in the array is used
     * @param mixed $recipient Login, player object or array; null for all
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function chatSend($message, $recipient = null, $multicall = false)
    {
        $logins = $this->getLogins($recipient, true);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }

        if (is_array($message)) {
            return $this->execute(ucfirst(__FUNCTION__) . 'ToLanguage', array($message, $logins), $multicall);
        }
        if (is_string($message)) {
            if ($logins) {
                return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($message, $logins), $multicall);
            }
            return $this->execute(ucfirst(__FUNCTION__), array($message), $multicall);
        }
        // else
        throw new InvalidArgumentException('message = ' . print_r($message, true));
    }

    /**
     * Returns the last chat lines. Maximum of 40 lines.
     * Only available to Admin.
     * @param bool $multicall
     * @return string[]
     */
    function getChatLines($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * The chat messages are no longer dispatched to the players, they only go to the rpc callback and the controller has to manually forward them.
     * Only available to Admin.
     * @param bool $enable
     * @param bool $excludeServer Allows all messages from the server to be automatically forwarded.
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function chatEnableManualRouting($enable = true, $excludeServer = false, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }
        if (!is_bool($excludeServer)) {
            throw new InvalidArgumentException('excludeServer = ' . print_r($excludeServer, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable, $excludeServer), $multicall);
    }

    /**
     * @deprecated
     * @see chatForward()
     */
    function chatForwardToLogin($message, $sender, $recipient = null, $multicall = false)
    {
        return $this->chatForward($message, $sender, $recipient, $multicall);
    }

    /**
     * Send a message to the specified recipient (or everybody if empty) on behalf of sender.
     * Only available if manual routing is enabled.
     * Only available to Admin.
     * @param string $message
     * @param mixed $sender Login or player object
     * @param mixed $recipient Login, player object or array; empty for all
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function chatForward($message, $sender, $recipient = null, $multicall = false)
    {
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }

        $senderLogin = $this->getLogin($sender);
        if ($senderLogin === false) {
            throw new InvalidArgumentException('sender = ' . print_r($sender, true));
        }
        $recipientLogins = $this->getLogins($recipient, true);
        if ($recipientLogins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }

        return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($message, $senderLogin, $recipientLogins), $multicall);
    }

    /**
     * Display a notice on all clients.
     * Only available to Admin.
     * @param mixed $recipient Login, player object or array; empty for all
     * @param string $message
     * @param mixed $avatar Login or player object whose avatar will be displayed; empty for none
     * @param int $variant 0: normal, 1: sad, 2: happy
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function sendNotice($recipient, $message, $avatar = null, $variant = 0, $multicall = false)
    {
        $logins = $this->getLogins($recipient, true);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }
        $avatarLogin = $this->getLogin($avatar, true);
        if ($avatarLogin === false) {
            throw new InvalidArgumentException('avatar = ' . print_r($avatar, true));
        }
        if (!is_int($variant) || $variant < 0 || $variant > 2) {
            throw new InvalidArgumentException('variant = ' . print_r($variant, true));
        }

        if ($logins) {
            return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($logins, $message, $avatarLogin, $variant), $multicall);
        }
        return $this->execute(ucfirst(__FUNCTION__), array($message, $avatar, $variant), $multicall);
    }

    /**
     * Display a manialink page on all clients.
     * Only available to Admin.
     * @param mixed $recipient Login, player object or array; empty for all
     * @param string $manialinks XML string
     * @param int $timeout Seconds before autohide, 0 for permanent
     * @param bool $hideOnClick Hide as soon as the user clicks on a page option
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function sendDisplayManialinkPage($recipient, $manialinks, $timeout = 0, $hideOnClick = false, $multicall = false)
    {
        $logins = $this->getLogins($recipient, true);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }
        if (!is_string($manialinks)) {
            throw new InvalidArgumentException('manialinks = ' . print_r($manialinks, true));
        }
        if (!is_int($timeout)) {
            throw new InvalidArgumentException('timeout = ' . print_r($timeout, true));
        }
        if (!is_bool($hideOnClick)) {
            throw new InvalidArgumentException('hideOnClick = ' . print_r($hideOnClick, true));
        }

        if ($logins) {
            return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($logins, $manialinks, $timeout, $hideOnClick), $multicall);
        }
        return $this->execute(ucfirst(__FUNCTION__), array($manialinks, $timeout, $hideOnClick), $multicall);
    }

    /**
     * Hide the displayed manialink page.
     * Only available to Admin.
     * @param mixed $recipient Login, player object or array; empty for all
     * @param bool $multicall
     * @return bool
     */
    function sendHideManialinkPage($recipient = null, $multicall = false)
    {
        $logins = $this->getLogins($recipient, true);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }

        if ($logins) {
            return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($logins), $multicall);
        }
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the latest results from the current manialink page as an array of structs {string Login, int PlayerId, int Result}:
     * - Result == 0 -> no answer
     * - Result > 0 -> answer from the player.
     * @param bool $multicall
     * @return Structures\PlayerAnswer[]
     */
    function getManialinkPageAnswers($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('PlayerAnswer', true));
        }
        return Structures\PlayerAnswer::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Opens a link in the client with the specified login.
     * Only available to Admin.
     * @param mixed $recipient Login, player object or array
     * @param string $link URL to open
     * @param int $linkType 0: in the external browser, 1: in the internal manialink browser
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function sendOpenLink($recipient, $link, $linkType, $multicall = false)
    {
        $logins = $this->getLogins($recipient);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }
        if (!is_string($link)) {
            throw new InvalidArgumentException('link = ' . print_r($link, true));
        }
        if ($linkType !== 0 && $linkType !== 1) {
            throw new InvalidArgumentException('linkType = ' . print_r($linkType, true));
        }

        return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($logins, $link, $linkType), $multicall);
    }

    /**
     * Prior to loading next map, execute SendToServer url '#qjoin=login@title'
     * Only available to Admin.
     * Available since ManiaPlanet 4
     * @param      $link
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function sendToServerAfterMatchEnd($link, $multicall = false)
    {
        if (!is_string($link)) {
            throw new InvalidArgumentException('link = ' . print_r($link, true));
        }

        $link = str_replace("maniaplanet://", "", $link);

        return $this->execute(ucfirst(__FUNCTION__), array($link), $multicall);
    }

    /**
     * Kick the player with the specified login, with an optional message.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param string $message
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function kick($player, $message = '', $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login, $message), $multicall);
    }

    /**
     * Ban the player with the specified login, with an optional message.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param string $message
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function ban($player, $message = '', $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login, $message), $multicall);
    }

    /**
     * Ban the player with the specified login, with a message.
     * Add it to the black list, and optionally save the new list.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param string $message
     * @param bool $save
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function banAndBlackList($player, $message = '', $save = false, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }
        if (!is_bool($save)) {
            throw new InvalidArgumentException('save = ' . print_r($save, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($player, $message, $save), $multicall);
    }

    /**
     * Unban the player with the specified login.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function unBan($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Clean the ban list of the server.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function cleanBanList($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the list of banned players.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param bool $multicall
     * @return Structures\PlayerBan[]
     * @throws InvalidArgumentException
     */
    function getBanList($length = -1, $offset = 0, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset), $this->structHandler('PlayerBan', true));
        }
        return Structures\PlayerBan::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset)));
    }

    /**
     * Blacklist the player with the specified login.
     * Only available to SuperAdmin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function blackList($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * UnBlackList the player with the specified login.
     * Only available to SuperAdmin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function unBlackList($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Clean the blacklist of the server.
     * Only available to SuperAdmin.
     * @param bool $multicall
     * @return bool
     */
    function cleanBlackList($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the list of blacklisted players.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param bool $multicall
     * @return Structures\Player[]
     * @throws InvalidArgumentException
     */
    function getBlackList($length = -1, $offset = 0, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset), $this->structHandler('Player', true));
        }
        return Structures\Player::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset)));
    }

    /**
     * Load the black list file with the specified file name.
     * Only available to Admin.
     * @param string $filename Empty for default filename (blacklist.txt)
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function loadBlackList($filename = '', $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * @param string|string[] $filename
     * @return string|string[]
     */
    private function secureUtf8($filename)
    {
        if (is_string($filename)) {
            $filename = $this->stripBom($filename);
            if (preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $filename)) {
                return "\xEF\xBB\xBF" . $filename;
            }
            return $filename;
        }
        return array_map(array($this, 'secureUtf8'), $filename);
    }

    /**
     * @param string|string[] $str
     * @return string|string[]
     */
    private function stripBom($str)
    {
        if (is_string($str)) {
            return str_replace("\xEF\xBB\xBF", '', $str);
        }
        return array_map(array($this, 'stripBom'), $str);
    }

    /**
     * Save the black list in the file with specified file name.
     * Only available to Admin.
     * @param string $filename Empty for default filename (blacklist.txt)
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function saveBlackList($filename = '', $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Add the player with the specified login on the guest list.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function addGuest($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Remove the player with the specified login from the guest list.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function removeGuest($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Clean the guest list of the server.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function cleanGuestList($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the list of players on the guest list.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param bool $multicall
     * @return Structures\Player[]
     * @throws InvalidArgumentException
     */
    function getGuestList($length = -1, $offset = 0, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset), $this->structHandler('Player', true));
        }
        return Structures\Player::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset)));
    }

    /**
     * Load the guest list file with the specified file name.
     * Only available to Admin.
     * @param string $filename Empty for default filename (guestlist.txt)
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function loadGuestList($filename = '', $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Save the guest list in the file with specified file name.
     * Only available to Admin.
     * @param string $filename Empty for default filename (guestlist.txt)
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function saveGuestList($filename = '', $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Sets whether buddy notifications should be sent in the chat.
     * Only available to Admin.
     * @param mixed $player Login or player object; empty for global setting
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setBuddyNotification($player, $enable, $multicall = false)
    {
        $login = $this->getLogin($player, true);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login, $enable), $multicall);
    }

    /**
     * Gets whether buddy notifications are enabled.
     * @param mixed $player Login or player object; empty for global setting
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function getBuddyNotification($player = null, $multicall = false)
    {
        $login = $this->getLogin($player, true);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Write the data to the specified file.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param string $localFilename
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function writeFileFromFile($filename, $localFilename, $multicall = false)
    {
        if (!file_exists($localFilename)) {
            throw new InvalidArgumentException('localFilename = ' . print_r($localFilename, true));
        }

        $contents = file_get_contents($localFilename);
        return $this->writeFile($filename, $contents, $multicall);
    }

    /**
     * Write the data to the specified file.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param string $data
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function writeFile($filename, $data, $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);
        if (!is_string($data)) {
            throw new InvalidArgumentException('data = ' . print_r($data, true));
        }

        $data = new Xmlrpc\Base64($data);
        return $this->execute(ucfirst(__FUNCTION__), array($filename, $data), $multicall);
    }

    /**
     * Send the data to the specified player. Login can be a single login or a list of comma-separated logins.
     * Only available to Admin.
     * @param mixed $recipient Login or player object or array
     * @param string $filename
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function tunnelSendDataFromFile($recipient, $filename, $multicall = false)
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }

        $contents = file_get_contents($filename);
        return $this->tunnelSendData($recipient, $contents, $multicall);
    }

    /**
     * Send the data to the specified player. Login can be a single login or a list of comma-separated logins.
     * Only available to Admin.
     * @param mixed $recipient Login, player object or array
     * @param string $data
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function tunnelSendData($recipient, $data, $multicall = false)
    {
        $logins = $this->getLogins($recipient);
        if ($logins === false) {
            throw new InvalidArgumentException('recipient = ' . print_r($recipient, true));
        }
        if (!is_string($data)) {
            throw new InvalidArgumentException('data = ' . print_r($data, true));
        }

        $data = new Xmlrpc\Base64($data);
        return $this->execute(ucfirst(__FUNCTION__) . 'ToLogin', array($logins, $data), $multicall);
    }

    /**
     * Just log the parameters and invoke a callback.
     * Can be used to talk to other xmlrpc clients connected, or to make custom votes.
     * If used in a callvote, the first parameter will be used as the vote message on the clients.
     * Only available to Admin.
     * @param string $message
     * @param string $callback
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function dedicatedEcho($message, $callback = '', $multicall = false)
    {
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }
        if (!is_string($callback)) {
            throw new InvalidArgumentException('callback = ' . print_r($callback, true));
        }

        return $this->execute('Echo', array($message, $callback), $multicall);
    }

    /**
     * Ignore the player with the specified login.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function ignore($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Unignore the player with the specified login.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function unIgnore($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Clean the ignore list of the server.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function cleanIgnoreList($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the list of ignored players.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param bool $multicall
     * @return Structures\Player[]
     * @throws InvalidArgumentException
     */
    function getIgnoreList($length = -1, $offset = 0, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset), $this->structHandler('Player', true));
        }
        return Structures\Player::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset)));
    }

    /**
     * Pay planets from the server account to a player.
     * The creation of the transaction itself may cost planets, so you need to have planets on the server account.
     * Only available to Admin.
     * @param mixed $payee Login or player object
     * @param int $amount
     * @param string $message
     * @param bool $multicall
     * @return int BillId
     * @throws InvalidArgumentException
     */
    function pay($payee, $amount, $message = '', $multicall = false)
    {
        $login = $this->getLogin($payee);
        if ($login === false) {
            throw new InvalidArgumentException('payee = ' . print_r($payee, true));
        }
        if (!is_int($amount) || $amount < 1) {
            throw new InvalidArgumentException('amount = ' . print_r($amount, true));
        }
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login, $amount, $message), $multicall);
    }

    /**
     * Create a bill, send it to a player, and return the BillId.
     * The creation of the transaction itself may cost planets, so you need to have planets on the server account.
     * Only available to Admin.
     * @param mixed $payer Login or player object
     * @param int $amount
     * @param string $message
     * @param mixed $payee Login or player object; empty for server account
     * @param bool $multicall
     * @return int BillId
     * @throws InvalidArgumentException
     */
    function sendBill($payer, $amount, $message = '', $payee = null, $multicall = false)
    {
        $payerLogin = $this->getLogin($payer);
        if ($payerLogin === false) {
            throw new InvalidArgumentException('payer = ' . print_r($payer, true));
        }
        if (!is_int($amount) || $amount < 1) {
            throw new InvalidArgumentException('amount = ' . print_r($amount, true));
        }
        if (!is_string($message)) {
            throw new InvalidArgumentException('message = ' . print_r($message, true));
        }
        $payeeLogin = $this->getLogin($payee, true);
        if ($payeeLogin === false) {
            throw new InvalidArgumentException('payee = ' . print_r($payee, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($payerLogin, $amount, $message, $payeeLogin), $multicall);
    }

    /**
     * Returns the current state of a bill.
     * @param int $billId
     * @param bool $multicall
     * @return Structures\Bill
     * @throws InvalidArgumentException
     */
    function getBillState($billId, $multicall = false)
    {
        if (!is_int($billId)) {
            throw new InvalidArgumentException('billId = ' . print_r($billId, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($billId), $this->structHandler('Bill'));
        }
        return Structures\Bill::fromArray($this->execute(ucfirst(__FUNCTION__), array($billId)));
    }

    /**
     * Returns the current number of planets on the server account.
     * @param bool $multicall
     * @return int
     */
    function getServerPlanets($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Get some system infos, including connection rates (in kbps).
     * @param bool $multicall
     * @return Structures\SystemInfos
     */
    function getSystemInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('SystemInfos'));
        }
        return Structures\SystemInfos::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set the download and upload rates (in kbps).
     * @param int $downloadRate
     * @param int $uploadRate
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setConnectionRates($downloadRate, $uploadRate, $multicall = false)
    {
        if (!is_int($downloadRate)) {
            throw new InvalidArgumentException('downloadRate = ' . print_r($downloadRate, true));
        }
        if (!is_int($uploadRate)) {
            throw new InvalidArgumentException('uploadRate = ' . print_r($uploadRate, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($downloadRate, $uploadRate), $multicall);
    }

    /**
     * Returns the list of tags and associated values set on this server.
     * Only available to Admin.
     * @param bool $multicall
     * @return Structures\Tag[]
     */
    function getServerTags($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Tag', true));
        }
        return Structures\Tag::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set a tag and its value on the server.
     * Only available to Admin.
     * @param string $key
     * @param string $value
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerTag($key, $value, $multicall = false)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('key = ' . print_r($key, true));
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('value = ' . print_r($value, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($key, $value), $multicall);
    }

    /**
     * Unset the tag with the specified name on the server.
     * Only available to Admin.
     * @param string $key
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function unsetServerTag($key, $multicall = false)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('key = ' . print_r($key, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($key), $multicall);
    }

    /**
     * Reset all tags on the server.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function resetServerTags($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new server name in utf8 format.
     * Only available to Admin.
     * @param string $name
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerName($name, $multicall = false)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('name = ' . print_r($name, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($name), $multicall);
    }

    /**
     * Get the server name in utf8 format.
     * @param bool $multicall
     * @return string
     */
    function getServerName($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new server comment in utf8 format.
     * Only available to Admin.
     * @param string $comment
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerComment($comment, $multicall = false)
    {
        if (!is_string($comment)) {
            throw new InvalidArgumentException('comment = ' . print_r($comment, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($comment), $multicall);
    }

    /**
     * Get the server comment in utf8 format.
     * @param bool $multicall
     * @return string
     */
    function getServerComment($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set whether the server should be hidden from the public server list.
     * Only available to Admin.
     * @param int $visibility 0: visible, 1: always hidden, 2: hidden from nations
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setHideServer($visibility, $multicall = false)
    {
        if (!is_int($visibility) || $visibility < 0 || $visibility > 2) {
            throw new InvalidArgumentException('visibility = ' . print_r($visibility, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($visibility), $multicall);
    }

    /**
     * Get whether the server wants to be hidden from the public server list.
     * @param bool $multicall
     * @return int
     */
    function getHideServer($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns true if this is a relay server.
     * @param bool $multicall
     * @return bool
     */
    function isRelayServer($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new password for the server.
     * Only available to Admin.
     * @param string $password
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerPassword($password, $multicall = false)
    {
        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($password), $multicall);
    }

    /**
     * Get the server password if called as Admin or Super Admin, else returns if a password is needed or not.
     * @param bool $multicall
     * @return string|bool
     */
    function getServerPassword($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new password for the spectator mode.
     * Only available to Admin.
     * @param string $password
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerPasswordForSpectator($password, $multicall = false)
    {
        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($password), $multicall);
    }

    /**
     * Get the password for spectator mode if called as Admin or Super Admin, else returns if a password is needed or not.
     * @param bool $multicall
     * @return string|bool
     */
    function getServerPasswordForSpectator($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new maximum number of players.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $maxPlayers
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setMaxPlayers($maxPlayers, $multicall = false)
    {
        if (!is_int($maxPlayers)) {
            throw new InvalidArgumentException('maxPlayers = ' . print_r($maxPlayers, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($maxPlayers), $multicall);
    }

    /**
     * Get the current and next maximum number of players allowed on server.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getMaxPlayers($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new maximum number of spectators.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $maxSpectators
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setMaxSpectators($maxSpectators, $multicall = false)
    {
        if (!is_int($maxSpectators)) {
            throw new InvalidArgumentException('maxSpectators = ' . print_r($maxSpectators, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($maxSpectators), $multicall);
    }

    /**
     * Get the current and next maximum number of Spectators allowed on server.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getMaxSpectators($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Declare if the server is a lobby, the number and maximum number of players currently managed by it, and the average level of the players.
     * Only available to Admin.
     * @param bool $isLobby
     * @param int $currentPlayers
     * @param int $maxPlayers
     * @param float $averageLevel
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setLobbyInfo($isLobby, $currentPlayers, $maxPlayers, $averageLevel, $multicall = false)
    {
        if (!is_bool($isLobby)) {
            throw new InvalidArgumentException('isLobby = ' . print_r($isLobby, true));
        }
        if (!is_int($currentPlayers)) {
            throw new InvalidArgumentException('currentPlayers = ' . print_r($currentPlayers, true));
        }
        if (!is_int($maxPlayers)) {
            throw new InvalidArgumentException('maxPlayers = ' . print_r($maxPlayers, true));
        }
        if (!is_float($averageLevel)) {
            throw new InvalidArgumentException('averageLevel = ' . print_r($averageLevel, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($isLobby, $currentPlayers, $maxPlayers, $averageLevel), $multicall);
    }

    /**
     * Get whether the server if a lobby, the number and maximum number of players currently managed by it.
     * @param bool $multicall
     * @return Structures\LobbyInfo
     */
    function getLobbyInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('LobbyInfo'));
        }
        return Structures\LobbyInfo::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Customize the clients 'leave server' dialog box.
     * Only available to Admin.
     * @param string $manialink
     * @param string $sendToServer Server URL, eg. '#qjoin=login@title'
     * @param bool $askFavorite
     * @param int $quitButtonDelay In milliseconds
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function customizeQuitDialog($manialink, $sendToServer = '', $askFavorite = true, $quitButtonDelay = 0, $multicall = false)
    {
        if (!is_string($manialink)) {
            throw new InvalidArgumentException('manialink = ' . print_r($manialink, true));
        }
        if (!is_string($sendToServer)) {
            throw new InvalidArgumentException('sendToServer = ' . print_r($sendToServer, true));
        }
        if (!is_bool($askFavorite)) {
            throw new InvalidArgumentException('askFavorite = ' . print_r($askFavorite, true));
        }
        if (!is_int($quitButtonDelay)) {
            throw new InvalidArgumentException('quitButtonDelay = ' . print_r($quitButtonDelay, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($manialink, $sendToServer, $askFavorite, $quitButtonDelay), $multicall);
    }

    /**
     * Set whether, when a player is switching to spectator, the server should still consider him a player and keep his player slot, or not.
     * Only available to Admin.
     * @param bool $keep
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function keepPlayerSlots($keep = true, $multicall = false)
    {
        if (!is_bool($keep)) {
            throw new InvalidArgumentException('keep = ' . print_r($keep, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($keep), $multicall);
    }

    /**
     * Get whether the server keeps player slots when switching to spectator.
     * @param bool $multicall
     * @return bool
     */
    function isKeepingPlayerSlots($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Enable or disable peer-to-peer upload from server.
     * Only available to Admin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function enableP2PUpload($enable = true, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Returns if the peer-to-peer upload from server is enabled.
     * @param bool $multicall
     * @return bool
     */
    function isP2PUpload($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Enable or disable peer-to-peer download for server.
     * Only available to Admin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function enableP2PDownload($enable = true, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Returns if the peer-to-peer download for server is enabled.
     * @param bool $multicall
     * @return bool
     */
    function isP2PDownload($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Allow clients to download maps from the server.
     * Only available to Admin.
     * @param bool $allow
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function allowMapDownload($allow = true, $multicall = false)
    {
        if (!is_bool($allow)) {
            throw new InvalidArgumentException('allow = ' . print_r($allow, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($allow), $multicall);
    }

    /**
     * Returns if clients can download maps from the server.
     * @param bool $multicall
     * @return bool
     */
    function isMapDownloadAllowed($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the path of the game datas directory.
     * Only available to Admin.
     * @param bool $multicall
     * @return string
     */
    function gameDataDirectory($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), array($this, 'stripBom'));
        }
        return $this->stripBom($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns the path of the maps directory.
     * Only available to Admin.
     * @param bool $multicall
     * @return string
     */
    function getMapsDirectory($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), array($this, 'stripBom'));
        }
        return $this->stripBom($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns the path of the skins directory.
     * Only available to Admin.
     * @param bool $multicall
     * @return string
     */
    function getSkinsDirectory($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), array($this, 'stripBom'));
        }
        return $this->stripBom($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * @deprecated since version 2013-04-11
     * Set Team names and colors.
     * Only available to Admin.
     * @param string $name1
     * @param float $color1
     * @param string $path1
     * @param string $name2
     * @param float $color2
     * @param string $path2
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setTeamInfo($name1, $color1, $path1, $name2, $color2, $path2, $multicall = false)
    {
        if (!is_string($name1)) {
            throw new InvalidArgumentException('name1 = ' . print_r($name1, true));
        }
        if (!is_float($color1)) {
            throw new InvalidArgumentException('color1 = ' . print_r($color1, true));
        }
        if (!is_string($path1)) {
            throw new InvalidArgumentException('path1 = ' . print_r($path1, true));
        }
        if (!is_string($name2)) {
            throw new InvalidArgumentException('name2 = ' . print_r($name2, true));
        }
        if (!is_float($color2)) {
            throw new InvalidArgumentException('color2 = ' . print_r($color2, true));
        }
        if (!is_string($path2)) {
            throw new InvalidArgumentException('path2 = ' . print_r($path2, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array('unused', 0., 'World', $name1, $color1, $path1, $name2, $color2, $path2), $multicall);
    }

    /**
     * Return info for a given team.
     * Only available to Admin.
     * @param int $team 0: no clan, 1 or 2
     * @param bool $multicall
     * @return Structures\Team
     * @throws InvalidArgumentException
     */
    function getTeamInfo($team, $multicall = false)
    {
        if (!is_int($team) || $team < 0 || $team > 2) {
            throw new InvalidArgumentException('team = ' . print_r($team, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($team), $this->structHandler('Team'));
        }
        return Structures\Team::fromArray($this->execute(ucfirst(__FUNCTION__), array($team)));
    }

    /**
     * Set the clublinks to use for the two teams.
     * Only available to Admin.
     * @param string $team1
     * @param string $team2
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setForcedClubLinks($team1, $team2, $multicall = false)
    {
        if (!is_string($team1)) {
            throw new InvalidArgumentException('team1 = ' . print_r($team1, true));
        }
        if (!is_string($team2)) {
            throw new InvalidArgumentException('team2 = ' . print_r($team2, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($team1, $team2), $multicall);
    }

    /**
     * Get the forced clublinks.
     * @param bool $multicall
     * @return string[]
     */
    function getForcedClubLinks($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * (debug tool) Connect a fake player to the server and returns the login.
     * Only available to Admin.
     * @param bool $multicall
     * @return string
     */
    function connectFakePlayer($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * (debug tool) Disconnect a fake player.
     * Only available to Admin.
     * @param string $login Fake player login or '*' for all
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function disconnectFakePlayer($login, $multicall = false)
    {
        if (!is_string($login)) {
            throw new InvalidArgumentException('login = ' . print_r($login, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Returns the token infos for a player.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return Structures\TokenInfos
     * @throws InvalidArgumentException
     */
    function getDemoTokenInfosForPlayer($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($login), $this->structHandler('TokenInfos'));
        }
        return Structures\TokenInfos::fromArray($this->execute(ucfirst(__FUNCTION__), array($login)));
    }

    /**
     * Disable player horns.
     * Only available to Admin.
     * @param bool $disable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function disableHorns($disable = true, $multicall = false)
    {
        if (!is_bool($disable)) {
            throw new InvalidArgumentException('disable = ' . print_r($disable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($disable), $multicall);
    }

    /**
     * Returns whether the horns are disabled.
     * @param bool $multicall
     * @return bool
     */
    function areHornsDisabled($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Disable the automatic mesages when a player connects/disconnects from the server.
     * Only available to Admin.
     * @param bool $disable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function disableServiceAnnounces($disable = true, $multicall = false)
    {
        if (!is_bool($disable)) {
            throw new InvalidArgumentException('disable = ' . print_r($disable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($disable), $multicall);
    }

    /**
     * Returns whether the automatic mesages are disabled.
     * @param bool $multicall
     * @return bool
     */
    function areServiceAnnouncesDisabled($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Enable the autosaving of all replays (vizualisable replays with all players, but not validable) on the server.
     * Only available to SuperAdmin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function autoSaveReplays($enable = true, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Enable the autosaving on the server of validation replays, every time a player makes a new time.
     * Only available to SuperAdmin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function autoSaveValidationReplays($enable = true, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Returns if autosaving of all replays is enabled on the server.
     * @param bool $multicall
     * @return bool
     */
    function isAutoSaveReplaysEnabled($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns if autosaving of validation replays is enabled on the server.
     * @param bool $multicall
     * @return bool
     */
    function isAutoSaveValidationReplaysEnabled($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Saves the current replay (vizualisable replays with all players, but not validable).
     * Only available to Admin.
     * @param string $filename Empty for automatic filename
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function saveCurrentReplay($filename = '', $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Saves a replay with the ghost of all the players' best race.
     * Only available to Admin.
     * @param mixed $player Login or player object; empty for all
     * @param string $filename Empty for automatic filename
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function saveBestGhostsReplay($player = null, $filename = '', $multicall = false)
    {
        $login = $this->getLogin($player, true);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($login, $filename), $multicall);
    }

    /**
     * Returns a replay containing the data needed to validate the current best time of the player.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return string
     * @throws InvalidArgumentException
     */
    function getValidationReplay($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($login), function ($v) {
                return $v->scalar;
            });
        }
        return $this->execute(ucfirst(__FUNCTION__), array($login))->scalar;
    }

    /**
     * Set a new ladder mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $mode 0: disabled, 1: forced
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setLadderMode($mode, $multicall = false)
    {
        if ($mode !== 0 && $mode !== 1) {
            throw new InvalidArgumentException('mode = ' . print_r($mode, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($mode), $multicall);
    }

    /**
     * Get the current and next ladder mode on server.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getLadderMode($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Get the ladder points limit for the players allowed on this server.
     * @param bool $multicall
     * @return Structures\LadderLimits
     */
    function getLadderServerLimits($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('LadderLimits'));
        }
        return Structures\LadderLimits::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set the network vehicle quality.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $quality 0: fast, 1: high
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setVehicleNetQuality($quality, $multicall = false)
    {
        if ($quality !== 0 && $quality !== 1) {
            throw new InvalidArgumentException('quality = ' . print_r($quality, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($quality), $multicall);
    }

    /**
     * Get the current and next network vehicle quality on server.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getVehicleNetQuality($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set new server options using the struct passed as parameters.
     * Mandatory fields:
     *  Name, Comment, Password, PasswordForSpectator, NextCallVoteTimeOut and CallVoteRatio.
     * Ignored fields:
     *  LadderServerLimitMin, LadderServerLimitMax and those starting with Current.
     * All other fields are optional and can be set to null to be ignored.
     * Only available to Admin.
     * A change of any field starting with Next requires a map restart to be taken into account.
     * @param Structures\ServerOptions $options
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerOptions($options, $multicall = false)
    {
        if (!($options instanceof Structures\ServerOptions && $options->isValid())) {
            throw new InvalidArgumentException('options = ' . print_r($options, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($options->toSetterArray()), $multicall);
    }

    /**
     * Returns a struct containing the server options
     * @param bool $multicall
     * @return Structures\ServerOptions
     */
    function getServerOptions($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('ServerOptions'));
        }
        return Structures\ServerOptions::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set whether the players can choose their side or if the teams are forced by the server (using ForcePlayerTeam()).
     * Only available to Admin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setForcedTeams($enable, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Returns whether the players can choose their side or if the teams are forced by the server.
     * @param bool $multicall
     * @return bool
     */
    function getForcedTeams($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Defines the packmask of the server.
     * Only maps matching the packmask will be allowed on the server, so that player connecting to it know what to expect.
     * Only available when the server is stopped.
     * Only available in 2011-08-01 API version.
     * Only available to Admin.
     * @param string $packMask
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setServerPackMask($packMask, $multicall = false)
    {
        if (!is_string($packMask)) {
            throw new InvalidArgumentException('packMask = ' . print_r($packMask, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($packMask), $multicall);
    }

    /**
     * Get the packmask of the server.
     * Only available in 2011-08-01 API version.
     * @param bool $multicall
     * @return string
     */
    function getServerPackMask($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the mods to apply on the clients.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param bool $override If true, even the maps with a mod will be overridden by the server setting
     * @param Structures\Mod|Structures\Mod[] $mods Array of structures [{string Env, string Url}, ...]
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setForcedMods($override, $mods, $multicall = false)
    {
        if (!is_bool($override)) {
            throw new InvalidArgumentException('override = ' . print_r($override, true));
        }
        if (is_array($mods)) {
            foreach ($mods as $i => &$mod) {
                if (!($mod instanceof Structures\Mod)) {
                    throw new InvalidArgumentException('mods[' . $i . '] = ' . print_r($mod, true));
                }
                $mod = $mod->toArray();
            }
        } elseif ($mods instanceof Structures\Mod) {
            $mods = array($mods->toArray());
        } else {
            throw new InvalidArgumentException('mods = ' . print_r($mods, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($override, $mods), $multicall);
    }

    /**
     * Get the mods settings.
     * @param bool $multicall
     * @return array {bool Override, Structures\Mod[] Mods}
     */
    function getForcedMods($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), function ($v) {
                $v['Mods'] = Structures\Mod::fromArrayOfArray($v['Mods']);
                return $v;
            });
        }
        $result = $this->execute(ucfirst(__FUNCTION__));
        $result['Mods'] = Structures\Mod::fromArrayOfArray($result['Mods']);
        return $result;
    }

    /**
     * Set the music to play on the clients.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param bool $override If true, even the maps with a custom music will be overridden by the server setting
     * @param string $music Url or filename relative to the GameData path
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setForcedMusic($override, $music, $multicall = false)
    {
        if (!is_bool($override)) {
            throw new InvalidArgumentException('override = ' . print_r($override, true));
        }
        if (!is_string($music)) {
            throw new InvalidArgumentException('music = ' . print_r($music, true));
        }
        if (!preg_match('~^.+?://~', $music)) {
            $music = $this->secureUtf8($music);
        }

        return $this->execute(ucfirst(__FUNCTION__), array($override, $music), $multicall);
    }

    /**
     * Get the music setting.
     * @param bool $multicall
     * @return Structures\Music
     */
    function getForcedMusic($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Music'));
        }
        return Structures\Music::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Defines a list of remappings for player skins.
     * Will only affect players connecting after the value is set.
     * Only available to Admin.
     * @param Structures\ForcedSkin|Structures\ForcedSkin[] $skins List of structs {Orig, Name, Checksum, Url}:
     * - Orig is the name of the skin to remap, or '*' for any other
     * - Name, Checksum, Url define the skin to use (you may set value '' for any of those, all 3 null means same as Orig).
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setForcedSkins($skins, $multicall = false)
    {
        if (is_array($skins)) {
            foreach ($skins as $i => &$skin) {
                if (!($skin instanceof Structures\ForcedSkin)) {
                    throw new InvalidArgumentException('skins[' . $i . '] = ' . print_r($skin, true));
                }
                $skin = $skin->toArray();
            }
        } elseif ($skins instanceof Structures\ForcedSkin) {
            $skins = array($skins->toArray());
        } else {
            throw new InvalidArgumentException('skins = ' . print_r($skins, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($skins), $multicall);
    }

    /**
     * Get the current forced skins.
     * @param bool $multicall
     * @return Structures\ForcedSkin[]
     */
    function getForcedSkins($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('ForcedSkin', true));
        }
        return Structures\ForcedSkin::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns the last error message for an internet connection.
     * Only available to Admin.
     * @param bool $multicall
     * @return string
     */
    function getLastConnectionErrorMessage($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new password for the referee mode.
     * Only available to Admin.
     * @param string $password
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setRefereePassword($password, $multicall = false)
    {
        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($password), $multicall);
    }

    /**
     * Get the password for referee mode if called as Admin or Super Admin, else returns if a password is needed or not.
     * @param bool $multicall
     * @return string|bool
     */
    function getRefereePassword($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the referee validation mode.
     * Only available to Admin.
     * @param int $mode 0: validate the top3 players, 1: validate all players
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setRefereeMode($mode, $multicall = false)
    {
        if ($mode !== 0 && $mode !== 1) {
            throw new InvalidArgumentException('mode = ' . print_r($mode, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($mode), $multicall);
    }

    /**
     * Get the referee validation mode.
     * @param bool $multicall
     * @return int
     */
    function getRefereeMode($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set whether the game should use a variable validation seed or not.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setUseChangingValidationSeed($enable, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Get the current and next value of UseChangingValidationSeed.
     * @param bool $multicall
     * @return bool[] {bool CurrentValue, bool NextValue}
     */
    function getUseChangingValidationSeed($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the maximum time the server must wait for inputs from the clients before dropping data, or '0' for auto-adaptation.
     * Only used by ShootMania.
     * Only available to Admin.
     * @param int $latency
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setClientInputsMaxLatency($latency, $multicall = false)
    {
        if (!is_int($latency)) {
            throw new InvalidArgumentException('latency = ' . print_r($latency, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($latency), $multicall);
    }

    /**
     * Get the current ClientInputsMaxLatency.
     * Only used by ShootMania.
     * @param bool $multicall
     * @return int
     */
    function getClientInputsMaxLatency($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Sets whether the server is in warm-up phase or not.
     * Only available to Admin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setWarmUp($enable, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Returns whether the server is in warm-up phase.
     * @param bool $multicall
     * @return bool
     */
    function getWarmUp($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Get the current mode script.
     * @param bool $multicall
     * @return string
     */
    function getModeScriptText($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the mode script and restart.
     * Only available to Admin.
     * @param string $script
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setModeScriptText($script, $multicall = false)
    {
        if (!is_string($script)) {
            throw new InvalidArgumentException('script = ' . print_r($script, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($script), $multicall);
    }

    /**
     * Returns the description of the current mode script.
     * @param bool $multicall
     * @return Structures\ScriptInfo
     */
    function getModeScriptInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('ScriptInfo'));
        }
        return Structures\ScriptInfo::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns the current settings of the mode script.
     * @param bool $multicall
     * @return array {mixed <setting name>, ...}
     */
    function getModeScriptSettings($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Change the settings of the mode script.
     * Only available to Admin.
     * @param mixed[] $settings {mixed <setting name>, ...}
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setModeScriptSettings($settings, $multicall = false)
    {
        if (!is_array($settings) || !$settings) {
            throw new InvalidArgumentException('settings = ' . print_r($settings, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($settings), $multicall);
    }

    /**
     * Send commands to the mode script.
     * Only available to Admin.
     * @param mixed[] $commands {mixed <command name>, ...}
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function sendModeScriptCommands($commands, $multicall = false)
    {
        if (!is_array($commands) || !$commands) {
            throw new InvalidArgumentException('commands = ' . print_r($commands, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($commands), $multicall);
    }

    /**
     * Change the settings and send commands to the mode script.
     * Only available to Admin.
     * @param mixed[] $settings {mixed <setting name>, ...}
     * @param mixed[] $commands {mixed <command name>, ...}
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setModeScriptSettingsAndCommands($settings, $commands, $multicall = false)
    {
        if (!is_array($settings) || !$settings) {
            throw new InvalidArgumentException('settings = ' . print_r($settings, true));
        }
        if (!is_array($commands) || !$commands) {
            throw new InvalidArgumentException('commands = ' . print_r($commands, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($settings, $commands), $multicall);
    }

    /**
     * Returns the current xml-rpc variables of the mode script.
     * @param bool $multicall
     * @return array {mixed <variable name>, ...}
     */
    function getModeScriptVariables($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the xml-rpc variables of the mode script.
     * Only available to Admin.
     * @param mixed[] $variables {mixed <variable name>, ...}
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setModeScriptVariables($variables, $multicall = false)
    {
        if (!is_array($variables) || !$variables) {
            throw new InvalidArgumentException('variables = ' . print_r($variables, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($variables), $multicall);
    }

    /**
     * @deprecated
     * @see triggerModeScriptEvent()
     */
    function triggerModeScriptEventArray($event, $params = array(), $multicall = false)
    {
        return $this->triggerModeScriptEvent($event, $params, $multicall);
    }

    /**
     * Send an event to the mode script.
     * Only available to Admin.
     * @param string $event
     * @param string|string[] $params
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function triggerModeScriptEvent($event, $params = '', $multicall = false)
    {
        if (!is_string($event)) {
            throw new InvalidArgumentException('event name must be a string: event = ' . print_r($event, true));
        }

        if (is_string($params)) {
            return $this->execute(ucfirst(__FUNCTION__), array($event, $params), $multicall);
        }

        if (is_array($params)) {
            foreach ($params as $param) {
                if (!is_string($param)) {
                    throw new InvalidArgumentException('argument must be a string: param = ' . print_r($param, true));
                }
            }
            return $this->execute(ucfirst(__FUNCTION__) . 'Array', array($event, $params), $multicall);
        }

        // else
        throw new InvalidArgumentException('argument must be string or string[]: params = ' . print_r($params, true));
    }

    /**
     * Get the script cloud variables of given object.
     * Only available to Admin.
     * @param string $type
     * @param string $id
     * @param bool $multicall
     * @return array {mixed <variable name>, ...}
     * @throws InvalidArgumentException
     */
    function getScriptCloudVariables($type, $id, $multicall = false)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('type = ' . print_r($type, true));
        }
        if (!is_string($id)) {
            throw new InvalidArgumentException('id = ' . print_r($id, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($type, $id), $multicall);
    }

    /**
     * Set the script cloud variables of given object.
     * Only available to Admin.
     * @param string $type
     * @param string $id
     * @param mixed[] $variables {mixed <variable name>, ...}
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setScriptCloudVariables($type, $id, $variables, $multicall = false)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('type = ' . print_r($type, true));
        }
        if (!is_string($id)) {
            throw new InvalidArgumentException('id = ' . print_r($id, true));
        }
        if (!is_array($variables) || !$variables) {
            throw new InvalidArgumentException('variables = ' . print_r($variables, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($type, $id, $variables), $multicall);
    }

    /**
     * Restarts the map.
     * Only available to Admin.
     * @param bool $dontClearCupScores Only available in legacy cup mode
     * @param bool $multicall
     * @return bool
     */
    function restartMap($dontClearCupScores = false, $multicall = false)
    {
        if (!is_bool($dontClearCupScores)) {
            throw new InvalidArgumentException('dontClearCupScores = ' . print_r($dontClearCupScores, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($dontClearCupScores), $multicall);
    }

    /**
     * Switch to next map.
     * Only available to Admin.
     * @param bool $dontClearCupScores Only available in legacy cup mode
     * @param bool $multicall
     * @return bool
     */
    function nextMap($dontClearCupScores = false, $multicall = false)
    {
        if (!is_bool($dontClearCupScores)) {
            throw new InvalidArgumentException('dontClearCupScores = ' . print_r($dontClearCupScores, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($dontClearCupScores), $multicall);
    }

    /**
     * Attempt to balance teams.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function autoTeamBalance($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Stop the server.
     * Only available to SuperAdmin.
     * @param bool $multicall
     * @return bool
     */
    function stopServer($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * In legacy Rounds or Laps mode, force the end of round without waiting for all players to giveup/finish.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function forceEndRound($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set new game settings using the struct passed as parameters.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param Structures\GameInfos $gameInfos
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setGameInfos($gameInfos, $multicall = false)
    {
        if (!($gameInfos instanceof Structures\GameInfos)) {
            throw new InvalidArgumentException('gameInfos = ' . print_r($gameInfos, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($gameInfos->toArray()), $multicall);
    }

    /**
     * Returns a struct containing the current game settings.
     * @param bool $multicall
     * @return Structures\GameInfos
     */
    function getCurrentGameInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('GameInfos'));
        }
        return Structures\GameInfos::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns a struct containing the game settings for the next map.
     * @param bool $multicall
     * @return Structures\GameInfos
     */
    function getNextGameInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('GameInfos'));
        }
        return Structures\GameInfos::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns a struct containing two other structures, the first containing the current game settings and the second the game settings for next map.
     * @param bool $multicall
     * @return Structures\GameInfos[] {Structures\GameInfos CurrentGameInfos, Structures\GameInfos NextGameInfos}
     */
    function getGameInfos($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('GameInfos', true));
        }
        return Structures\GameInfos::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set a new game mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $gameMode 0: Script, 1: Rounds, 2: TimeAttack, 3: Team, 4: Laps, 5: Cup, 6: Stunt
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setGameMode($gameMode, $multicall = false)
    {
        if (!is_int($gameMode) || $gameMode < 0 || $gameMode > 6) {
            throw new InvalidArgumentException('gameMode = ' . print_r($gameMode, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($gameMode), $multicall);
    }

    /**
     * Get the current game mode.
     * @param bool $multicall
     * @return int
     */
    function getGameMode($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new chat time value (actually the duration of the podium).
     * Only available to Admin.
     * @param int $chatTime In milliseconds, 0: no podium displayed
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setChatTime($chatTime, $multicall = false)
    {
        if (!is_int($chatTime)) {
            throw new InvalidArgumentException('chatTime = ' . print_r($chatTime, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($chatTime), $multicall);
    }

    /**
     * Get the current and next chat time.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getChatTime($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new finish timeout value for legacy laps and rounds based modes.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $timeout In milliseconds, 0: default, 1: adaptative to the map duration
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setFinishTimeout($timeout, $multicall = false)
    {
        if (!is_int($timeout)) {
            throw new InvalidArgumentException('timeout = ' . print_r($timeout, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($timeout), $multicall);
    }

    /**
     * Get the current and next FinishTimeout.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getFinishTimeout($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set whether to enable the automatic warm-up phase in all modes.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $duration 0: disable, number of rounds in rounds based modes, number of times the gold medal time otherwise
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setAllWarmUpDuration($duration, $multicall = false)
    {
        if (!is_int($duration)) {
            throw new InvalidArgumentException('duration = ' . print_r($duration, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($duration), $multicall);
    }

    /**
     * Get whether the automatic warm-up phase is enabled in all modes.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getAllWarmUpDuration($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set whether to disallow players to respawn.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param bool $disable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setDisableRespawn($disable, $multicall = false)
    {
        if (!is_bool($disable)) {
            throw new InvalidArgumentException('disable = ' . print_r($disable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($disable), $multicall);
    }

    /**
     * Get whether players are disallowed to respawn.
     * @param bool $multicall
     * @return bool[] {bool CurrentValue, bool NextValue}
     */
    function getDisableRespawn($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set whether to override the players preferences and always display all opponents.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $opponents 0: no override, 1: show all, else: minimum number of opponents
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setForceShowAllOpponents($opponents, $multicall = false)
    {
        if (!is_int($opponents)) {
            throw new InvalidArgumentException('opponents = ' . print_r($opponents, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($opponents), $multicall);
    }

    /**
     * Get whether players are forced to show all opponents.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getForceShowAllOpponents($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new mode script name for script mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param string $script
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setScriptName($script, $multicall = false)
    {
        if (!is_string($script)) {
            throw new InvalidArgumentException('script = ' . print_r($script, true));
        }
        $script = $this->secureUtf8($script);

        return $this->execute(ucfirst(__FUNCTION__), array($script), $multicall);
    }

    /**
     * Get the current and next mode script name for script mode.
     * @param bool $multicall
     * @return string[] {string CurrentValue, string NextValue}
     */
    function getScriptName($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), array($this, 'stripBom'));
        }
        return $this->stripBom($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Set a new time limit for legacy time attack mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $limit In milliseconds
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setTimeAttackLimit($limit, $multicall = false)
    {
        if (!is_int($limit)) {
            throw new InvalidArgumentException('limit = ' . print_r($limit, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($limit), $multicall);
    }

    /**
     * Get the current and next time limit for legacy time attack mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getTimeAttackLimit($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new synchronized start period for legacy time attack mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $synch
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setTimeAttackSynchStartPeriod($synch, $multicall = false)
    {
        if (!is_int($synch)) {
            throw new InvalidArgumentException('synch = ' . print_r($synch, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($synch), $multicall);
    }

    /**
     * Get the current and synchronized start period for legacy time attack mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getTimeAttackSynchStartPeriod($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new time limit for legacy laps mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $limit
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setLapsTimeLimit($limit, $multicall = false)
    {
        if (!is_int($limit)) {
            throw new InvalidArgumentException('limit = ' . print_r($limit, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($limit), $multicall);
    }

    /**
     * Get the current and next time limit for legacy laps mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getLapsTimeLimit($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new number of laps for legacy laps mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $laps
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setNbLaps($laps, $multicall = false)
    {
        if (!is_int($laps)) {
            throw new InvalidArgumentException('laps = ' . print_r($laps, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($laps), $multicall);
    }

    /**
     * Get the current and next number of laps for legacy laps mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getNbLaps($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new number of laps for legacy rounds mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $laps 0: map default
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setRoundForcedLaps($laps, $multicall = false)
    {
        if (!is_int($laps)) {
            throw new InvalidArgumentException('laps = ' . print_r($laps, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($laps), $multicall);
    }

    /**
     * Get the current and next number of laps for rounds mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getRoundForcedLaps($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new points limit for legacy rounds mode (value set depends on UseNewRulesRound).
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $limit
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setRoundPointsLimit($limit, $multicall = false)
    {
        if (!is_int($limit)) {
            throw new InvalidArgumentException('limit = ' . print_r($limit, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($limit), $multicall);
    }

    /**
     * Get the current and next points limit for rounds mode (values returned depend on UseNewRulesRound).
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getRoundPointsLimit($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the points used for the scores in legacy rounds mode.
     * Only available to Admin.
     * @param int[] $points Array of decreasing integers for the players from the first to last
     * @param bool $relax True to relax the constraint checking on the scores
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setRoundCustomPoints($points, $relax = false, $multicall = false)
    {
        if (!is_array($points)) {
            throw new InvalidArgumentException('points = ' . print_r($points, true));
        }
        if (!is_bool($relax)) {
            throw new InvalidArgumentException('relax = ' . print_r($relax, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($points, $relax), $multicall);
    }

    /**
     * Gets the points used for the scores in legacy rounds mode.
     * @param bool $multicall
     * @return int[]
     */
    function getRoundCustomPoints($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set if new rules are used for legacy rounds mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param bool $newRules
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setUseNewRulesRound($newRules, $multicall = false)
    {
        if (!is_bool($newRules)) {
            throw new InvalidArgumentException('newRules = ' . print_r($newRules, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($newRules), $multicall);
    }

    /**
     * Get if the new rules are used for legacy rounds mode (Current and next values).
     * @param bool $multicall
     * @return bool[] {bool CurrentValue, bool NextValue}
     */
    function getUseNewRulesRound($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new points limit for team mode (value set depends on UseNewRulesTeam).
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $limit
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setTeamPointsLimit($limit, $multicall = false)
    {
        if (!is_int($limit)) {
            throw new InvalidArgumentException('limit = ' . print_r($limit, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($limit), $multicall);
    }

    /**
     * Get the current and next points limit for team mode (values returned depend on UseNewRulesTeam).
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getTeamPointsLimit($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set a new number of maximum points per round for team mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $max
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setMaxPointsTeam($max, $multicall = false)
    {
        if (!is_int($max)) {
            throw new InvalidArgumentException('max = ' . print_r($max, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($max), $multicall);
    }

    /**
     * Get the current and next number of maximum points per round for team mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getMaxPointsTeam($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set if new rules are used for team mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param bool $newRules
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setUseNewRulesTeam($newRules, $multicall = false)
    {
        if (!is_bool($newRules)) {
            throw new InvalidArgumentException('newRules = ' . print_r($newRules, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($newRules), $multicall);
    }

    /**
     * Get if the new rules are used for team mode (Current and next values).
     * @param bool $multicall
     * @return bool[] {bool CurrentValue, bool NextValue}
     */
    function getUseNewRulesTeam($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the points needed for victory in Cup mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $limit
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCupPointsLimit($limit, $multicall = false)
    {
        if (!is_int($limit)) {
            throw new InvalidArgumentException('limit = ' . print_r($limit, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($limit), $multicall);
    }

    /**
     * Get the points needed for victory in Cup mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getCupPointsLimit($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Sets the number of rounds before going to next map in Cup mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $rounds
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCupRoundsPerMap($rounds, $multicall = false)
    {
        if (!is_int($rounds)) {
            throw new InvalidArgumentException('rounds = ' . print_r($rounds, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($rounds), $multicall);
    }

    /**
     * Get the number of rounds before going to next map in Cup mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getCupRoundsPerMap($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set whether to enable the automatic warm-up phase in Cup mode.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $duration Number of rounds, 0: no warm-up
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCupWarmUpDuration($duration, $multicall = false)
    {
        if (!is_int($duration)) {
            throw new InvalidArgumentException('duration = ' . print_r($duration, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($duration), $multicall);
    }

    /**
     * Get whether the automatic warm-up phase is enabled in Cup mode.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getCupWarmUpDuration($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Set the number of winners to determine before the match is considered over.
     * Only available to Admin.
     * Requires a map restart to be taken into account.
     * @param int $winners
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setCupNbWinners($winners, $multicall = false)
    {
        if (!is_int($winners)) {
            throw new InvalidArgumentException('winners = ' . print_r($winners, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($winners), $multicall);
    }

    /**
     * Get the number of winners to determine before the match is considered over.
     * @param bool $multicall
     * @return int[] {int CurrentValue, int NextValue}
     */
    function getCupNbWinners($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the current map index in the selection, or -1 if the map is no longer in the selection.
     * @param bool $multicall
     * @return int
     */
    function getCurrentMapIndex($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the map index in the selection that will be played next (unless the current one is restarted...)
     * @param bool $multicall
     * @return int
     */
    function getNextMapIndex($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Sets the map index in the selection that will be played next (unless the current one is restarted...)
     * @param int $index
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setNextMapIndex($index, $multicall = false)
    {
        if (!is_int($index)) {
            throw new InvalidArgumentException('index = ' . print_r($index, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($index), $multicall);
    }

    /**
     * Sets the map in the selection that will be played next (unless the current one is restarted...)
     * @param string $ident
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function setNextMapIdent($ident, $multicall = false)
    {
        if (!is_string($ident)) {
            throw new InvalidArgumentException('ident = ' . print_r($ident, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($ident), $multicall);
    }

    /**
     * Immediately jumps to the map designated by the index in the selection.
     * @param int $index
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function jumpToMapIndex($index, $multicall = false)
    {
        if (!is_int($index)) {
            throw new InvalidArgumentException('index = ' . print_r($index, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($index), $multicall);
    }

    /**
     * Immediately jumps to the map designated by its identifier (it must be in the selection).
     * @param string $ident
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function jumpToMapIdent($ident, $multicall = false)
    {
        if (!is_string($ident)) {
            throw new InvalidArgumentException('ident = ' . print_r($ident, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($ident), $multicall);
    }

    /**
     * Returns a struct containing the infos for the current map.
     * @param bool $multicall
     * @return Structures\Map
     */
    function getCurrentMapInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Map'));
        }
        return Structures\Map::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns a struct containing the infos for the next map.
     * @param bool $multicall
     * @return Structures\Map
     */
    function getNextMapInfo($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('Map'));
        }
        return Structures\Map::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Returns a struct containing the infos for the map with the specified filename.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return Structures\Map
     * @throws InvalidArgumentException
     */
    function getMapInfo($filename, $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($filename), $this->structHandler('Map'));
        }
        return Structures\Map::fromArray($this->execute(ucfirst(__FUNCTION__), array($filename)));
    }

    /**
     * Returns a boolean if the map with the specified filename matches the current server settings.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function checkMapForCurrentServerParams($filename, $multicall = false)
    {
        if (!is_string($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Returns a list of maps among the current selection of the server.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param bool $multicall
     * @return Structures\Map[]
     * @throws InvalidArgumentException
     */
    function getMapList($length = -1, $offset = 0, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset), $this->structHandler('Map', true));
        }
        return Structures\Map::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset)));
    }

    /**
     * Add the map with the specified filename at the end of the current selection.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function addMap($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Add the list of maps with the specified filenames at the end of the current selection.
     * Only available to Admin.
     * @param string[] $filenames Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps actually added
     * @throws InvalidArgumentException
     */
    function addMapList($filenames, $multicall = false)
    {
        if (!is_array($filenames)) {
            throw new InvalidArgumentException('filenames = ' . print_r($filenames, true));
        }
        $filenames = $this->secureUtf8($filenames);

        return $this->execute(ucfirst(__FUNCTION__), array($filenames), $multicall);
    }

    /**
     * Remove the map with the specified filename from the current selection.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function removeMap($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Remove the list of maps with the specified filenames from the current selection.
     * Only available to Admin.
     * @param string[] $filenames Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps actually removed
     * @throws InvalidArgumentException
     */
    function removeMapList($filenames, $multicall = false)
    {
        if (!is_array($filenames)) {
            throw new InvalidArgumentException('filenames = ' . print_r($filenames, true));
        }
        $filenames = $this->secureUtf8($filenames);

        return $this->execute(ucfirst(__FUNCTION__), array($filenames), $multicall);
    }

    /**
     * Insert the map with the specified filename after the current map.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function insertMap($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Insert the list of maps with the specified filenames after the current map.
     * Only available to Admin.
     * @param string[] $filenames Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps actually inserted
     * @throws InvalidArgumentException
     */
    function insertMapList($filenames, $multicall = false)
    {
        if (!is_array($filenames)) {
            throw new InvalidArgumentException('filenames = ' . print_r($filenames, true));
        }
        $filenames = $this->secureUtf8($filenames);

        return $this->execute(ucfirst(__FUNCTION__), array($filenames), $multicall);
    }

    /**
     * Set as next map the one with the specified filename, if it is present in the selection.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function chooseNextMap($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Set as next maps the list of maps with the specified filenames, if they are present in the selection.
     * Only available to Admin.
     * @param string[] $filenames Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps actually chosen
     * @throws InvalidArgumentException
     */
    function chooseNextMapList($filenames, $multicall = false)
    {
        if (!is_array($filenames)) {
            throw new InvalidArgumentException('filenames = ' . print_r($filenames, true));
        }
        $filenames = $this->secureUtf8($filenames);

        return $this->execute(ucfirst(__FUNCTION__), array($filenames), $multicall);
    }

    /**
     * Set a list of maps defined in the playlist with the specified filename as the current selection of the server, and load the gameinfos from the same file.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps in the new list
     * @throws InvalidArgumentException
     */
    function loadMatchSettings($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Add a list of maps defined in the playlist with the specified filename at the end of the current selection.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps actually added
     * @throws InvalidArgumentException
     */
    function appendPlaylistFromMatchSettings($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Save the current selection of map in the playlist with the specified filename, as well as the current gameinfos.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps in the saved playlist
     * @throws InvalidArgumentException
     */
    function saveMatchSettings($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Insert a list of maps defined in the playlist with the specified filename after the current map.
     * Only available to Admin.
     * @param string $filename Relative to the Maps path
     * @param bool $multicall
     * @return int Number of maps actually inserted
     * @throws InvalidArgumentException
     */
    function insertPlaylistFromMatchSettings($filename, $multicall = false)
    {
        if (!is_string($filename) || !strlen($filename)) {
            throw new InvalidArgumentException('filename = ' . print_r($filename, true));
        }
        $filename = $this->secureUtf8($filename);

        return $this->execute(ucfirst(__FUNCTION__), array($filename), $multicall);
    }

    /**
     * Returns the list of players on the server.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param int $compatibility 0: united, 1: forever, 2: forever including servers
     * @param bool $multicall
     * @return Structures\PlayerInfo[]
     * @throws InvalidArgumentException
     */
    function getPlayerList($length = -1, $offset = 0, $compatibility = 1, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }
        if (!is_int($compatibility) || $compatibility < 0 || $compatibility > 2) {
            throw new InvalidArgumentException('compatibility = ' . print_r($compatibility, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset, $compatibility), $this->structHandler('PlayerInfo', true));
        }
        return Structures\PlayerInfo::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset, $compatibility)));
    }

    /**
     * Returns a struct containing the infos on the player with the specified login.
     * @param mixed $player Login or player object
     * @param int $compatibility 0: united, 1: forever
     * @param bool $multicall
     * @return Structures\PlayerInfo
     * @throws InvalidArgumentException
     */
    function getPlayerInfo($player, $compatibility = 1, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if ($compatibility !== 0 && $compatibility !== 1) {
            throw new InvalidArgumentException('compatibility = ' . print_r($compatibility, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($login, $compatibility), $this->structHandler('PlayerInfo'));
        }
        return Structures\PlayerInfo::fromArray($this->execute(ucfirst(__FUNCTION__), array($login, $compatibility)));
    }

    /**
     * Returns a struct containing the infos on the player with the specified login.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return Structures\PlayerDetailedInfo
     * @throws InvalidArgumentException
     */
    function getDetailedPlayerInfo($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($login), $this->structHandler('PlayerDetailedInfo'));
        }
        return Structures\PlayerDetailedInfo::fromArray($this->execute(ucfirst(__FUNCTION__), array($login)));
    }

    /**
     * Returns a struct containing the player infos of the game server
     * (ie: in case of a basic server, itself; in case of a relay server, the main server)
     * @param int $compatibility 0: united, 1: forever
     * @param bool $multicall
     * @return Structures\PlayerInfo
     * @throws InvalidArgumentException
     */
    function getMainServerPlayerInfo($compatibility = 1, $multicall = false)
    {
        if (!is_int($compatibility)) {
            throw new InvalidArgumentException('compatibility = ' . print_r($compatibility, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($compatibility), $this->structHandler('PlayerInfo'));
        }
        return Structures\PlayerInfo::fromArray($this->execute(ucfirst(__FUNCTION__), array($compatibility)));
    }

    /**
     * Returns the current rankings for the match in progress.
     * In script modes, scores aren't returned.
     * In team modes, the scores for the two teams are returned.
     * In other modes, it's the individual players' scores.
     * @param int $length Maximum number of infos to be returned
     * @param int $offset Starting index in the list
     * @param bool $multicall
     * @return Structures\PlayerRanking[]
     * @throws InvalidArgumentException
     */
    function getCurrentRanking($length = -1, $offset = 0, $multicall = false)
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('length = ' . print_r($length, true));
        }
        if (!is_int($offset)) {
            throw new InvalidArgumentException('offset = ' . print_r($offset, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($length, $offset), $this->structHandler('PlayerRanking', true));
        }
        return Structures\PlayerRanking::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($length, $offset)));
    }

    /**
     * Returns the current ranking of the player with the specified login (or list of comma-separated logins) for the match in progress.
     * In script modes, scores aren't returned.
     * In other modes, it's the individual players' scores.
     * @param mixed $players Login, player object or array
     * @param bool $multicall
     * @return Structures\PlayerRanking[]
     * @throws InvalidArgumentException
     */
    function getCurrentRankingForLogin($players, $multicall = false)
    {
        $logins = $this->getLogins($players);
        if ($logins === false) {
            throw new InvalidArgumentException('players = ' . print_r($players, true));
        }

        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array($logins), $this->structHandler('PlayerRanking', true));
        }
        return Structures\PlayerRanking::fromArrayOfArray($this->execute(ucfirst(__FUNCTION__), array($logins)));
    }

    /**
     * Returns the current winning team for the race in progress.
     * @param bool $multicall
     * @return int -1: if not in team mode or draw match, 0 or 1 otherwise
     */
    function getCurrentWinnerTeam($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Force the scores of the current game.
     * Only available in rounds and team mode.
     * Only available to Admin/SuperAdmin.
     * @param int[][] $scores Array of structs {int PlayerId, int Score}
     * @param bool $silent True to update silently (only available for SuperAdmin)
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function forceScores($scores, $silent, $multicall = false)
    {
        if (!is_array($scores)) {
            throw new InvalidArgumentException('scores = ' . print_r($scores, true));
        }
        foreach ($scores as $i => $score) {
            if (!is_array($score)) {
                throw new InvalidArgumentException('score[' . $i . '] = ' . print_r($score, true));
            }
            if (!isset($score['PlayerId']) || !is_int($score['PlayerId'])) {
                throw new InvalidArgumentException('score[' . $i . ']["PlayerId"] = ' . print_r($score, true));
            }
            if (!isset($score['Score']) || !is_int($score['Score'])) {
                throw new InvalidArgumentException('score[' . $i . ']["Score"] = ' . print_r($score, true));
            }
        }
        if (!is_bool($silent)) {
            throw new InvalidArgumentException('silent = ' . print_r($silent, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($scores, $silent), $multicall);
    }

    /**
     * Force the team of the player.
     * Only available in team mode.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param int $team 0 or 1
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function forcePlayerTeam($player, $team, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if ($team !== 0 && $team !== 1) {
            throw new InvalidArgumentException('team = ' . print_r($team, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login, $team), $multicall);
    }

    /**
     * Force the spectating status of the player.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param int $mode 0: user selectable, 1: spectator, 2: player, 3: spectator but keep selectable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function forceSpectator($player, $mode, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }
        if (!is_int($mode) || $mode < 0 || $mode > 3) {
            throw new InvalidArgumentException('mode = ' . print_r($mode, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login, $mode), $multicall);
    }

    /**
     * Force spectators to look at a specific player.
     * Only available to Admin.
     * @param mixed $spectator Login or player object; empty for all
     * @param mixed $target Login or player object; empty for automatic
     * @param int $camera -1: leave unchanged, 0: replay, 1: follow, 2: free
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function forceSpectatorTarget($spectator, $target, $camera, $multicall = false)
    {
        $spectatorLogin = $this->getLogin($spectator, true);
        if ($spectatorLogin === false) {
            throw new InvalidArgumentException('player = ' . print_r($spectator, true));
        }
        $targetLogin = $this->getLogin($target, true);
        if ($targetLogin === false) {
            throw new InvalidArgumentException('target = ' . print_r($target, true));
        }
        if (!is_int($camera) || $camera < -1 || $camera > 2) {
            throw new InvalidArgumentException('camera = ' . print_r($camera, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($spectatorLogin, $targetLogin, $camera), $multicall);
    }

    /**
     * Pass the login of the spectator.
     * A spectator that once was a player keeps his player slot, so that he can go back to player mode.
     * Calling this function frees this slot for another player to connect.
     * Only available to Admin.
     * @param mixed $player Login or player object
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function spectatorReleasePlayerSlot($player, $multicall = false)
    {
        $login = $this->getLogin($player);
        if ($login === false) {
            throw new InvalidArgumentException('player = ' . print_r($player, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($login), $multicall);
    }

    /**
     * Enable control of the game flow: the game will wait for the caller to validate state transitions.
     * Only available to Admin.
     * @param bool $enable
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function manualFlowControlEnable($enable = true, $multicall = false)
    {
        if (!is_bool($enable)) {
            throw new InvalidArgumentException('enable = ' . print_r($enable, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array($enable), $multicall);
    }

    /**
     * Allows the game to proceed.
     * Only available to Admin.
     * @param bool $multicall
     * @return bool
     */
    function manualFlowControlProceed($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns whether the manual control of the game flow is enabled.
     * Only available to Admin.
     * @param bool $multicall
     * @return int 0: no, 1: yes by the xml-rpc client making the call, 2: yes by some other xml-rpc client
     */
    function manualFlowControlIsEnabled($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the transition that is currently blocked, or '' if none.
     * (That's exactly the value last received by the callback.)
     * Only available to Admin.
     * @param bool $multicall
     * @return string
     */
    function manualFlowControlGetCurTransition($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns the current match ending condition.
     * @param bool $multicall
     * @return string 'Playing', 'ChangeMap' or 'Finished'
     */
    function checkEndMatchCondition($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Returns a struct containing the networks stats of the server.
     * Only available to SuperAdmin.
     * @param bool $multicall
     * @return Structures\NetworkStats
     */
    function getNetworkStats($multicall = false)
    {
        if ($multicall) {
            return $this->execute(ucfirst(__FUNCTION__), array(), $this->structHandler('NetworkStats'));
        }
        return Structures\NetworkStats::fromArray($this->execute(ucfirst(__FUNCTION__)));
    }

    /**
     * Start a server on lan, using the current configuration.
     * Only available to SuperAdmin.
     * @param bool $multicall
     * @return bool
     */
    function startServerLan($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Start a server on internet, using the current configuration.
     * Only available to SuperAdmin.
     * @param bool $multicall
     * @return bool
     */
    function startServerInternet($multicall = false)
    {
        return $this->execute(ucfirst(__FUNCTION__), array(), $multicall);
    }

    /**
     * Join the server on lan.
     * Only available on client.
     * Only available to Admin.
     * @param string $host IPv4 with optionally a port (eg. '192.168.1.42:2350')
     * @param string $password
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function joinServerLan($host, $password = '', $multicall = false)
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('host = ' . print_r($host, true));
        }
        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array(array('Server' => $host, 'ServerPassword' => $password)), $multicall);
    }

    /**
     * Join the server on internet.
     * Only available on client.
     * Only available to Admin.
     * @param string $host Server login or IPv4 with optionally a port (eg. '192.168.1.42:2350')
     * @param string $password
     * @param bool $multicall
     * @return bool
     * @throws InvalidArgumentException
     */
    function joinServerInternet($host, $password = '', $multicall = false)
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('host = ' . print_r($host, true));
        }
        if (!is_string($password)) {
            throw new InvalidArgumentException('password = ' . print_r($password, true));
        }

        return $this->execute(ucfirst(__FUNCTION__), array(array('Server' => $host, 'ServerPassword' => $password)), $multicall);
    }
}

/**
 * Exception Dedicated to Invalid Argument Error on Request Call
 */
class InvalidArgumentException extends \Exception
{
}
