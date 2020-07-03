<?php


namespace EvoSC\Modules\LocalRecords;


use EvoSC\Classes\DB;
use EvoSC\Controllers\MapController;
use EvoSC\Models\Player;

class LocalsBenchmark
{
    public function run()
    {
        $localsCount = 5;
        $simulatedPlayers = 100;

        while (Player::count() <= $localsCount) {
            Player::create([
                'NickName' => uniqid('PLAYER'),
                'Login' => md5(uniqid())
            ]);
        }

        DB::table('local-records')->where('Map', '=', MapController::getCurrentMap()->id)->delete();

        echo "Test insert $localsCount random locals\n";
        $players = Player::all();
        $start = microtime(true);

        for ($i = 0; $i < $localsCount; $i++) {
            LocalRecords::playerFinish($players->get($i), rand(12000, 101000), "1300,1900,2300,2900");
        }

        $end = microtime(true);
        $result = $end - $start;
        $avg = $result / $localsCount;
        printf("Finished inserting $localsCount locals ($simulatedPlayers players simulated), took: %.03fs (avg. %.06fs).\n", $result, $avg);
    }
}