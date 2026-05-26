<?php

declare(strict_types=1);

namespace Wordvel\WordPress;

use RuntimeException;

final class WordPressPathResolver
{
    public function loader(): string
    {
        $configured = base_path((string) config('wordvel.wordpress.path', 'wordpress'));
        $candidates = [
            rtrim($configured, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'wp-load.php',
            base_path('../wordpress/wp-load.php'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return (string) realpath($candidate);
            }
        }

        throw new RuntimeException('WordPress could not be found. Set WORDVEL_WORDPRESS_PATH to the WordPress install directory.');
    }
}
