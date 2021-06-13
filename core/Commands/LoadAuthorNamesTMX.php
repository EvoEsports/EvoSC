<?php

namespace EvoSC\Commands;

use EvoSC\Classes\Cache;
use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\RestClient;
use EvoSC\Controllers\ConfigController;
use EvoSC\Controllers\HookController;
use EvoSC\Models\Map;
use EvoSC\Modules\MxDownload\MxDownload;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadAuthorNamesTMX extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('tmx:load-authors')
            ->setDescription('Loads and sets missing author names for maps.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Log::setOutput($output);
        ConfigController::init();
        Database::init();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        global $__ManiaPlanet;
        $__ManiaPlanet = false;
        HookController::init();
        ManiaLinkEvent::init();
        RestClient::init('');
        MxDownload::start('', true);

        foreach (Map::whereNotNull('mx_id')->get() as $map) {
            if ($map->author->NickName == $map->author->Login) {
                try {
                    if(Cache::has("mx-details/$map->mx_id")){
                        $details = $map->mx_details;
                    }else{
                        $details = MxDownload::loadMxDetails($map->mx_id);
                    }

                    if (!is_null($details)) {
                        DB::table('players')
                            ->where('Login', '=', $map->author->Login)
                            ->update([
                                'NickName' => $details->Username
                            ]);
                    }
                } catch (Exception $e) {
                    Log::errorWithCause("Failed to load author names", $e);
                }
            }
        }

        return 0;
    }
}
