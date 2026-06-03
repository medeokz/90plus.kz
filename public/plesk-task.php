<?php



/**

 * https://90plus.kz/plesk-task.php?key=KEY

 * Cron:   ?job=scheduler&key=KEY

 * Ping:    ?job=cron-ping&key=KEY  — проверка, что Plesk дергает URL

 */



declare(strict_types=1);



header('Content-Type: text/plain; charset=utf-8');



$resolve = require dirname(__DIR__).'/scripts/plesk-laravel-root.php';

$root = $resolve(__DIR__);



$logHit = static function (string $message) use ($root): void {

    $logFile = $root.'/storage/logs/plesk-cron-hits.log';

    @mkdir(dirname($logFile), 0775, true);

    @file_put_contents($logFile, date('Y-m-d H:i:s').' '.$message.PHP_EOL, FILE_APPEND | LOCK_EX);

};



if (isset($_GET['job']) && $_GET['job'] !== '') {

    $job = (string) $_GET['job'];

    $key = (string) ($_GET['key'] ?? '');



    $verify = require $root.'/scripts/plesk-verify-key.php';

    $secret = $verify($root);

    if ($secret === null || $secret === '' || ! hash_equals($secret, $key)) {

        http_response_code(403);

        echo "Forbidden\n";

        exit(1);

    }



    if ($job === 'cron-ping') {

        $logHit('cron-ping OK');

        echo "cron-ping OK ".date('c')."\n";

        exit(0);

    }



    if ($job === 'scheduler') {

        $logHit('scheduler hit → dispatch worker');

        $early = require $root.'/scripts/plesk-early-response.php';

        $early("scheduler: dispatched\n");



        $spawn = require $root.'/scripts/plesk-spawn-worker.php';

        if ($spawn($root, $key)) {

            $logHit('worker spawn OK');

        } else {

            $logHit('worker spawn FAILED — check APP_URL, curl, allow_url_fopen');

            @file_put_contents(

                $root.'/storage/logs/cron-scheduler.log',

                date('Y-m-d H:i:s')." ERROR: worker spawn failed\n",

                FILE_APPEND | LOCK_EX

            );

        }



        exit(0);

    }



    if ($job === 'worker') {

        $logHit('worker started');

        $runner = $root.'/scripts/plesk-scheduler-runner.php';

        if (! is_file($runner)) {

            http_response_code(500);

            echo "ERROR: missing scripts/plesk-scheduler-runner.php\n";

            exit(1);

        }

        @ini_set('max_execution_time', '0');

        @set_time_limit(0);

        ignore_user_abort(true);

        $run = require $runner;
        $code = $run($root, $key);
        $logHit('worker finished exit='.$code);
        exit($code);

    }



    $logHit("job={$job}");

    $early = require $root.'/scripts/plesk-early-response.php';

    $early("job: {$job} accepted\n");



    $runner = $root.'/scripts/plesk-artisan-runner.php';

    if (! is_file($runner)) {

        http_response_code(500);

        echo "ERROR: missing scripts/plesk-artisan-runner.php\n";

        exit(1);

    }

    $run = require $runner;

    [$code] = $run($root, $job, $key);

    exit($code);

}



if (isset($_GET['deploy']) && (string) $_GET['deploy'] === '1') {

    $runner = $root.'/scripts/plesk-deploy-runner.php';

    if (! is_file($runner)) {

        http_response_code(500);

        echo "ERROR: missing scripts/plesk-deploy-runner.php\n";

        exit(1);

    }

    $run = require $runner;

    exit($run($root));

}



if (isset($_GET['refresh-config']) && (string) $_GET['refresh-config'] === '1') {

    $runner = $root.'/scripts/plesk-config-refresh.php';

    if (! is_file($runner)) {

        http_response_code(500);

        echo "ERROR: missing scripts/plesk-config-refresh.php\n";

        exit(1);

    }

    $run = require $runner;

    exit($run($root));

}



if (isset($_GET['db-check']) && (string) $_GET['db-check'] === '1') {

    $runner = $root.'/scripts/plesk-db-check.php';

    if (! is_file($runner)) {

        http_response_code(500);

        echo "ERROR: missing scripts/plesk-db-check.php\n";

        exit(1);

    }

    $run = require $runner;

    exit((int) $run($root));

}



@mkdir($root.'/storage/logs', 0775, true);

file_put_contents($root.'/storage/logs/ping.log', date('c')." plesk-task OK\n", FILE_APPEND);



echo "OK laravel-root={$root}\n";

echo "Cron ping:     ?job=cron-ping&key=YOUR_KEY\n";

echo "Scheduler:     ?job=scheduler&key=YOUR_KEY\n";

echo "Worker (test): ?job=worker&key=YOUR_KEY\n";

echo "Deploy:        ?deploy=1&key=YOUR_KEY\n";


