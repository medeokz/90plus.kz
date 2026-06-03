<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PleskTaskController extends Controller
{
    public function handle(Request $request): SymfonyResponse
    {
        $root = base_path();
        $key = (string) $request->query('key', '');

        if ($request->query('deploy') === '1') {
            return $this->runDeploy($root, $key);
        }

        $job = (string) $request->query('job', '');
        if ($job !== '') {
            return $this->runJob($root, $job, $key);
        }

        @mkdir($root.'/storage/logs', 0775, true);
        file_put_contents(
            $root.'/storage/logs/ping.log',
            date('c').' ping via Laravel route'.PHP_EOL,
            FILE_APPEND
        );

        $body = "OK site root={$root}\n"
            ."Use: /plesk-task.php (recommended)\n"
            ."Deploy:  /plesk-task.php?deploy=1&key=YOUR_KEY\n"
            ."Cron:    /plesk-task.php?job=articles&key=YOUR_KEY\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function runDeploy(string $root, string $key): SymfonyResponse
    {
        $runner = $root.'/scripts/plesk-deploy-runner.php';
        if (! is_file($runner)) {
            return $this->plain('ERROR: missing scripts/plesk-deploy-runner.php', 500);
        }

        ob_start();
        $run = require $runner;
        $code = $run($root);
        $output = ob_get_clean();

        return $this->plain($output, $code === 0 ? 200 : 500);
    }

    private function runJob(string $root, string $job, string $key): SymfonyResponse
    {
        $runner = $root.'/scripts/plesk-artisan-runner.php';
        if (! is_file($runner)) {
            return $this->plain('ERROR: missing scripts/plesk-artisan-runner.php', 500);
        }

        ob_start();
        $run = require $runner;
        [$code] = $run($root, $job, $key);
        $output = ob_get_clean();

        return $this->plain($output, $code === 0 ? 200 : ($code === 1 ? 500 : 403));
    }

    private function plain(string $body, int $status): Response
    {
        return response($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
