<?php

declare(strict_types=1);

namespace Wordvel\Console;

use Illuminate\Console\Command;
use Wordvel\Runtime\Application;
use Wordvel\Testing\FakeWpRestRequest;

final class ProofCommand extends Command
{
    protected $signature = 'wordvel:proof {name=Humberto}';

    protected $description = 'Prove WordVel can dispatch a WordPress-shaped request through Laravel.';

    public function handle(Application $runtime): int
    {
        $controller = 'App\\Http\\Controllers\\WordvelExampleController';
        $requestDto = 'App\\Data\\WordvelExampleRequest';

        if (! class_exists($controller) || ! class_exists($requestDto)) {
            $this->error('Missing Laravel proof classes in the host app.');

            return self::FAILURE;
        }

        $response = $runtime->dispatch([
            'controller' => $controller,
            'method' => 'show',
            'request_dto' => $requestDto,
            'response_dto' => null,
            'wp_request' => new FakeWpRestRequest(query: [
                'name' => $this->argument('name'),
            ]),
        ]);

        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
