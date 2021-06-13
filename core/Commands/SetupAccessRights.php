<?php

namespace EvoSC\Commands;

use EvoSC\Classes\Database;
use EvoSC\Classes\DB;
use EvoSC\Classes\Log;
use EvoSC\Controllers\ConfigController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SetupAccessRights extends Command
{
    /**
     * Command settings
     */
    protected function configure()
    {
        $this->setName('setup:access-rights')
            ->setDescription('Lets you choose the access-rights for your groups.');
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
        $output->writeln('<fg=green>AccessRights-Setup started.</>');

        $question = new ConfirmationQuestion('<fg=yellow>Do you want to load the default configuration [y/N]?</>', false);

        if ($this->getHelper('question')->ask($input, $output, $question)) {
            $this->loadDefaultConfiguration($input, $output);
            return 0;
        }

        $this->menuLoop($input, $output);

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function menuLoop(InputInterface $input, OutputInterface $output)
    {
        $groups = DB::table('groups')->where('id', '>', 1)->get();
        $groupNames = $groups->pluck('Name', 'id');
        $groupNames->put(0, 'EXIT');

        $question = new ChoiceQuestion(
            'Choose group to edit [2]',
            $groupNames->toArray(),
            2
        );

        $question->setErrorMessage('Group %s is invalid.');
        $name = $this->getHelper('question')->ask($input, $output, $question);

        if ($name == 'EXIT') {
            return;
        }

        $this->editGroup($name, $input, $output);
        $this->menuLoop($input, $output);
    }

    /**
     * @param $groupName
     * @param $input
     * @param $output
     */
    private function editGroup($groupName, InputInterface $input, OutputInterface $output)
    {
        $accessRights = DB::table('access-rights')->pluck('name');

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            "Please select the access-rights that $groupName should get (comma separated)",
            $accessRights->toArray(),
            ''
        );
        $question->setMultiselect(true);

        $accessRights = $helper->ask($input, $output, $question);
        $groupId = DB::table('groups')->where('Name', '=', $groupName)->first()->id;
        $this->setAccessRights($groupId, $accessRights);

        $output->writeln("$groupName updated.");
    }

    private function setAccessRights($groupId, array $accessRights)
    {
        collect($accessRights)->each(function ($value) use ($groupId) {
            if ($value != "0") {
                DB::table('access_right_group')->insert([
                    'group_id' => $groupId,
                    'access_right_id' => DB::table('access-rights')->where('name', '=', $value)->first()->id, //TOD: Remove after July 2020
                    'access_right_name' => $value
                ]);
            }
        });
    }

    private function loadDefaultConfiguration(InputInterface $input, OutputInterface $output)
    {
        DB::table('access_right_group')->where('group_id', '=', 2)->delete();

        $this->setAccessRights(2, [
            'admin_echoes',
            'always_print_join_msg',
            'force_end_round',
            'info_messages',
            'local_delete',
            'manipulate_points',
            'manipulate_time',
            'map_add',
            'map_delete',
            'map_disable',
            'map_queue_drop',
            'map_queue_keep',
            'map_queue_multiple',
            'map_queue_recent',
            'map_replay',
            'map_reset',
            'map_skip',
            'no_vote_limits',
            'player_ban',
            'player_force_spec',
            'player_kick',
            'player_mute',
            'player_warn',
            'vote_custom',
            'warm_up_skip',
        ]);

        $output->writeln('<fg=green>Default access-rights loaded.</>');
    }
}