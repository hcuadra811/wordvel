<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use RuntimeException;
use Symfony\Component\Process\Process;

final class WordPressCodex
{
    public function __construct(
        private readonly WordPressPathResolver $paths,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function call(string $action, array $payload = []): mixed
    {
        $process = new Process([
            $this->phpBinary(),
            __DIR__ . '/CodexBridge.php',
            $this->paths->loader(),
        ]);

        $process->setInput(json_encode([
            'action' => $action,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
        $process->setTimeout(10);
        $process->mustRun();

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('WordPress Codex returned an invalid response.');
        }

        if (($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException((string) ($decoded['error'] ?? 'WordPress Codex call failed.'));
        }

        return $decoded['data'] ?? null;
    }

    private function phpBinary(): string
    {
        $configured = env('WORDVEL_PHP_BINARY');

        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            basename(PHP_BINARY) === 'php' ? PHP_BINARY : null,
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php',
            '/usr/bin/php',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return (string) realpath($candidate);
            }
        }

        return 'php';
    }
}
