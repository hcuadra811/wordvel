<?php

declare(strict_types=1);

namespace Wordvel\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ZipArchive;
use Symfony\Component\Process\Process;

final class InstallCommand extends Command
{
    private const SUPPORTED_WORDPRESS_VERSION = '7.0';

    protected $signature = 'wordvel:install
        {--force : Overwrite existing WordVel starter files}
        {--with-api-kit : Install optional langsys/laravel-api-development-kit scaffolding when available}
        {--skip-redocly : Skip the ReDocly API documentation viewer scaffold}
        {--skip-wordpress : Skip the local WordPress backend scaffold}
        {--wordpress-version= : WordPress version to install, defaults to the latest WordVel-supported version}
        {--wordpress-dir=wordpress : Directory where the local WordPress backend should live}';

    protected $description = 'Install WordVel into a Laravel API application.';

    public function handle(Filesystem $files): int
    {
        $this->info('Installing WordVel...');

        $this->publishConfig();
        $this->ensureDirectory($files, storage_path('wordvel'));
        $this->ensureApiRouting($files);
        $this->writeStarterFiles($files);

        if (! $this->option('skip-redocly')) {
            $this->writeRedoclyFiles($files);
        }

        if (! $this->option('skip-wordpress')) {
            $this->installWordPressBackend($files);
        }

        if ($this->option('with-api-kit')) {
            $this->installApiKit();
        }

        $this->callSilent('optimize:clear');
        $this->runArtisanInFreshProcess('wordvel:manifest');

        if ($this->hasCommand('openapi:generate')) {
            $this->runArtisanInFreshProcess('openapi:generate');
        }

        $this->newLine();
        $this->info('WordVel installed successfully.');
        $this->line('Starter endpoint: GET /api/v1/health');
        $this->line('API documentation: GET /api/documentation');
        $this->line('WordPress backend: ' . $this->option('wordpress-dir'));
        $this->line('Manifest: storage/wordvel/manifest.json');

        if (! $this->hasCommand('api-kit:install')) {
            $this->warn('Optional API Kit commands were not found. Install langsys/laravel-api-development-kit to enable API Kit setup.');
        }

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $parameters = [
            '--tag' => 'wordvel-config',
        ];

        if ($this->option('force')) {
            $parameters['--force'] = true;
        }

        $this->call('vendor:publish', $parameters);
    }

    private function installApiKit(): void
    {
        if (! $this->hasCommand('api-kit:install')) {
            $this->warn('Skipping API Kit setup because api-kit:install is not available.');

            return;
        }

        $this->call('api-kit:install');
    }

    private function ensureApiRouting(Filesystem $files): void
    {
        $routesPath = base_path('routes/api.php');

        if (! $files->exists($routesPath)) {
            $this->ensureDirectory($files, dirname($routesPath));
            $files->put($routesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n");
        }

        $bootstrapPath = base_path('bootstrap/app.php');

        if (! $files->exists($bootstrapPath)) {
            return;
        }

        $bootstrap = $files->get($bootstrapPath);

        if (str_contains($bootstrap, 'routes/api.php')) {
            return;
        }

        $updated = preg_replace(
            "/(->withRouting\\(\\s*)(web:\\s*__DIR__\\s*\\.\\s*['\"]\\/\\.\\.\\/routes\\/web\\.php['\"],)/s",
            "$1$2\n        api: __DIR__.'/../routes/api.php',",
            $bootstrap,
            1,
        );

        if (is_string($updated) && $updated !== $bootstrap) {
            $files->put($bootstrapPath, $updated);
        }
    }

    private function writeStarterFiles(Filesystem $files): void
    {
        $this->writeFile(
            $files,
            app_path('Data/WordvelHealthResource.php'),
            $this->healthResource(),
        );

        $this->writeFile(
            $files,
            app_path('Http/Controllers/WordvelHealthController.php'),
            $this->healthController($this->hasApiKit()),
        );

        $this->writeFile(
            $files,
            app_path('OpenApi/WordvelOpenApi.php'),
            $this->openApiInfo(),
        );

        $this->appendRoute($files);
    }

    private function writeRedoclyFiles(Filesystem $files): void
    {
        $this->writeFile(
            $files,
            resource_path('views/redoc.blade.php'),
            $this->redocView(),
        );

        $this->appendWebRoutes($files);
    }

    private function appendWebRoutes(Filesystem $files): void
    {
        $routesPath = base_path('routes/web.php');

        if (! $files->exists($routesPath)) {
            $this->ensureDirectory($files, dirname($routesPath));
            $files->put($routesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n");
        }

        $routes = $files->get($routesPath);

        if (str_contains($routes, 'api.documentation') && str_contains($routes, 'api.docs')) {
            return;
        }

        $routeBlock = <<<'PHP'

Route::get('/api/documentation', function () {
    return view('redoc');
})->name('api.documentation');

Route::get('/api/docs/{jsonFile}', function (string $jsonFile) {
    $path = storage_path("api-docs/$jsonFile");

    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache',
    ]);
})->where('jsonFile', '[\w\-]+\.json')->name('api.docs');
PHP;

        $files->append($routesPath, $routeBlock . PHP_EOL);
    }

    private function installWordPressBackend(Filesystem $files): void
    {
        $version = (string) ($this->option('wordpress-version') ?: config('wordvel.wordpress.version', self::SUPPORTED_WORDPRESS_VERSION));
        $relativePath = trim((string) ($this->option('wordpress-dir') ?: config('wordvel.wordpress.path', 'wordpress')), '/');
        $wordpressPath = base_path($relativePath);
        $currentVersion = $this->installedWordPressVersion($files, $wordpressPath);

        if ($currentVersion === $version) {
            $this->line("WordPress {$version} already installed in {$relativePath}.");
        } elseif ($currentVersion !== null && ! $this->option('force')) {
            $this->warn("WordPress {$currentVersion} is already installed in {$relativePath}. Run with --force to update it to {$version}.");
        } else {
            $this->downloadAndExtractWordPress($files, $version, $wordpressPath, $currentVersion !== null);
            $this->line("Installed WordPress {$version} in {$relativePath}.");
        }

        $this->writeWordPressConfig($files, $wordpressPath);
        $this->writeWordPressScripts($files, $wordpressPath);
        $this->installWordPressBridge($files, $wordpressPath);
    }

    private function installedWordPressVersion(Filesystem $files, string $wordpressPath): ?string
    {
        $versionFile = $wordpressPath . '/wp-includes/version.php';

        if (! $files->exists($versionFile)) {
            return null;
        }

        $contents = $files->get($versionFile);

        if (preg_match("/\\\$wp_version\\s*=\\s*'([^']+)'/", $contents, $matches) !== 1) {
            return null;
        }

        return preg_replace('/\\.0$/', '', $matches[1]);
    }

    private function downloadAndExtractWordPress(Filesystem $files, string $version, string $targetPath, bool $updateExisting): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required to install WordPress.');
        }

        $cachePath = storage_path("wordvel/cache/wordpress-{$version}.zip");
        $extractPath = storage_path("wordvel/cache/wordpress-{$version}");
        $downloadUrl = "https://wordpress.org/wordpress-{$version}.zip";

        $this->ensureDirectory($files, dirname($cachePath));

        if (! $files->exists($cachePath)) {
            $this->line("Downloading WordPress {$version}...");
            $files->put($cachePath, file_get_contents($downloadUrl));
        }

        if ($files->isDirectory($extractPath)) {
            $files->deleteDirectory($extractPath);
        }

        $zip = new ZipArchive();

        if ($zip->open($cachePath) !== true) {
            throw new \RuntimeException("Unable to open downloaded WordPress archive: {$cachePath}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $sourcePath = $extractPath . '/wordpress';

        if (! $updateExisting) {
            $this->ensureDirectory($files, dirname($targetPath));
            $files->copyDirectory($sourcePath, $targetPath);

            return;
        }

        foreach (['wp-admin', 'wp-includes'] as $directory) {
            if ($files->isDirectory($targetPath . '/' . $directory)) {
                $files->deleteDirectory($targetPath . '/' . $directory);
            }

            $files->copyDirectory($sourcePath . '/' . $directory, $targetPath . '/' . $directory);
        }

        foreach ($files->files($sourcePath) as $file) {
            if ($file->getFilename() === 'wp-config-sample.php') {
                continue;
            }

            $files->copy($file->getPathname(), $targetPath . '/' . $file->getFilename());
        }
    }

    private function writeWordPressConfig(Filesystem $files, string $wordpressPath): void
    {
        $this->writeFile(
            $files,
            $wordpressPath . '/wp-config.php',
            $this->wordPressConfig(),
        );
    }

    private function writeWordPressScripts(Filesystem $files, string $wordpressPath): void
    {
        $this->writeFile($files, $wordpressPath . '/install-local.php', $this->wordPressInstallScript());
        $this->writeFile($files, $wordpressPath . '/activate-headless-theme.php', $this->wordPressThemeScript());
        $this->writeFile($files, $wordpressPath . '/enable-pretty-permalinks.php', $this->wordPressPermalinksScript());
    }

    private function installWordPressBridge(Filesystem $files, string $wordpressPath): void
    {
        $source = dirname(__DIR__, 2) . '/testing/wordpress/wp-content';
        $contentPath = $wordpressPath . '/wp-content';

        if (! $files->isDirectory($source)) {
            $this->warn('WordPress bridge proof assets were not found; skipping bridge plugin/theme scaffold.');

            return;
        }

        $this->ensureDirectory($files, $contentPath . '/plugins');
        $this->ensureDirectory($files, $contentPath . '/themes');
        $this->ensureDirectory($files, $contentPath . '/mu-plugins');

        $files->copyDirectory($source . '/plugins/wordvel-proof', $contentPath . '/plugins/wordvel-proof');
        $files->copyDirectory($source . '/themes/wordvel-headless', $contentPath . '/themes/wordvel-headless');
        $files->copy($source . '/mu-plugins/wordvel-proof-loader.php', $contentPath . '/mu-plugins/wordvel-proof-loader.php');
        $this->patchBridgePlugin($files, $contentPath . '/plugins/wordvel-proof/wordvel-proof.php');
    }

    private function patchBridgePlugin(Filesystem $files, string $pluginPath): void
    {
        if (! $files->exists($pluginPath)) {
            return;
        }

        $contents = $files->get($pluginPath);
        $contents = str_replace(
            "\$laravelPath = dirname(__DIR__, 4) . '/laravel';",
            "\$laravelPath = defined('WORDVEL_LARAVEL_PATH')\n    ? WORDVEL_LARAVEL_PATH\n    : dirname(__DIR__, 4) . '/laravel';",
            $contents,
        );
        $contents = str_replace(
            "\$manifestPath = dirname(__DIR__, 4) . '/laravel/storage/wordvel/manifest.json';",
            "\$manifestPath = wordvel_laravel_path() . '/storage/wordvel/manifest.json';",
            $contents,
        );

        if (! str_contains($contents, 'function wordvel_laravel_path(): string')) {
            $contents .= <<<'PHP'

function wordvel_laravel_path(): string
{
    return defined('WORDVEL_LARAVEL_PATH')
        ? WORDVEL_LARAVEL_PATH
        : dirname(__DIR__, 4) . '/laravel';
}
PHP;
        }

        $files->put($pluginPath, rtrim($contents) . PHP_EOL);
    }

    private function appendRoute(Filesystem $files): void
    {
        $routesPath = base_path('routes/api.php');
        $route = <<<'PHP'

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [\App\Http\Controllers\WordvelHealthController::class, 'show'])
        ->name('wordvel.health')
        ->responseDto(\App\Data\WordvelHealthResource::class);
});
PHP;

        $routes = $files->get($routesPath);

        if (str_contains($routes, 'wordvel.health')) {
            return;
        }

        $files->append($routesPath, $route . PHP_EOL);
    }

    private function writeFile(Filesystem $files, string $path, string $contents): void
    {
        if ($files->exists($path) && ! $this->option('force')) {
            $this->line('Keeping existing ' . $this->relativePath($path));

            return;
        }

        $this->ensureDirectory($files, dirname($path));
        $files->put($path, rtrim($contents) . PHP_EOL);
        $this->line('Wrote ' . $this->relativePath($path));
    }

    private function ensureDirectory(Filesystem $files, string $path): void
    {
        if (! $files->isDirectory($path)) {
            $files->makeDirectory($path, 0755, true);
        }
    }

    private function hasApiKit(): bool
    {
        return trait_exists(\Langsys\ApiKit\Traits\ApiResponse::class);
    }

    private function hasCommand(string $name): bool
    {
        return $this->getApplication()?->has($name) === true;
    }

    private function runArtisanInFreshProcess(string $command): void
    {
        $process = new Process([PHP_BINARY, 'artisan', $command], base_path());
        $process->setTimeout(null);
        $process->mustRun(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }

    private function healthResource(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class WordvelHealthResource extends Data
{
    public function __construct(
        public string $name,
        public string $status,
        public string $framework,
    ) {}
}
PHP;
    }

    private function healthController(bool $usesApiKit): string
    {
        if ($usesApiKit) {
            return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WordvelHealthResource;
use Illuminate\Http\JsonResponse;
use Langsys\ApiKit\Traits\ApiResponse;

final class WordvelHealthController
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/health",
     *     summary="Check API health",
     *     description="Returns a small DTO-backed response that confirms WordVel is installed.",
     *     tags={"System"},
     *     @OA\Response(response="200", description="API is ready", @OA\JsonContent(ref="#/components/schemas/WordvelHealthResource"))
     * )
     */
    public function show(): JsonResponse
    {
        return $this->resourceResponse(new WordvelHealthResource(
            name: config('app.name', 'WordVel API'),
            status: 'ready',
            framework: 'wordvel',
        ));
    }
}
PHP;
        }

        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\WordvelHealthResource;
use Illuminate\Http\JsonResponse;

final class WordvelHealthController
{
    /**
     * @OA\Get(
     *     path="/api/v1/health",
     *     summary="Check API health",
     *     description="Returns a small DTO-backed response that confirms WordVel is installed.",
     *     tags={"System"},
     *     @OA\Response(response="200", description="API is ready", @OA\JsonContent(ref="#/components/schemas/WordvelHealthResource"))
     * )
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => new WordvelHealthResource(
                name: config('app.name', 'WordVel API'),
                status: 'ready',
                framework: 'wordvel',
            ),
        ]);
    }
}
PHP;
    }

    private function openApiInfo(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="WordVel API",
 *     version="1.0.0",
 *     description="API documentation generated for a WordVel application."
 * )
 */
final class WordvelOpenApi
{
}
PHP;
    }

    private function redocView(): string
    {
        return <<<'BLADE'
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name', 'WordVel') }} API Documentation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
<div id="redoc"></div>
<script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
<script>
    Redoc.init("{{ route('api.docs', ['jsonFile' => 'api-docs.json']) }}", {
        theme: {
            colors: {
                primary: {
                    main: '#1b1b18'
                }
            }
        }
    }, document.getElementById('redoc'));
</script>
</body>
</html>
BLADE;
    }

    private function wordPressConfig(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

define('DB_NAME', getenv('WORDVEL_WP_DB_NAME') ?: wordvel_env('DB_DATABASE', 'wordpress'));
define('DB_USER', getenv('WORDVEL_WP_DB_USER') ?: wordvel_env('DB_USERNAME', 'root'));
define('DB_PASSWORD', getenv('WORDVEL_WP_DB_PASSWORD') ?: wordvel_env('DB_PASSWORD', ''));
define('DB_HOST', getenv('WORDVEL_WP_DB_HOST') ?: wordvel_env('DB_HOST', '127.0.0.1'));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('AUTH_KEY', getenv('WORDVEL_WP_AUTH_KEY') ?: wordvel_env('APP_KEY', 'wordvel-local-auth-key'));
define('SECURE_AUTH_KEY', getenv('WORDVEL_WP_SECURE_AUTH_KEY') ?: wordvel_env('APP_KEY', 'wordvel-local-secure-auth-key'));
define('LOGGED_IN_KEY', getenv('WORDVEL_WP_LOGGED_IN_KEY') ?: wordvel_env('APP_KEY', 'wordvel-local-logged-in-key'));
define('NONCE_KEY', getenv('WORDVEL_WP_NONCE_KEY') ?: wordvel_env('APP_KEY', 'wordvel-local-nonce-key'));
define('AUTH_SALT', getenv('WORDVEL_WP_AUTH_SALT') ?: 'wordvel-local-auth-salt');
define('SECURE_AUTH_SALT', getenv('WORDVEL_WP_SECURE_AUTH_SALT') ?: 'wordvel-local-secure-auth-salt');
define('LOGGED_IN_SALT', getenv('WORDVEL_WP_LOGGED_IN_SALT') ?: 'wordvel-local-logged-in-salt');
define('NONCE_SALT', getenv('WORDVEL_WP_NONCE_SALT') ?: 'wordvel-local-nonce-salt');

$table_prefix = getenv('WORDVEL_WP_TABLE_PREFIX') ?: 'wp_';

define('WP_DEBUG', filter_var(wordvel_env('APP_DEBUG', true), FILTER_VALIDATE_BOOL));
define('WP_DEBUG_DISPLAY', WP_DEBUG);
define('WP_ENVIRONMENT_TYPE', wordvel_env('APP_ENV', 'local'));
define('WP_HOME', getenv('WORDVEL_WP_HOME') ?: str_replace('-api.test', '-wp.test', wordvel_env('APP_URL', 'http://wordvel-wp.test')));
define('WP_SITEURL', getenv('WORDVEL_WP_SITEURL') ?: WP_HOME);
define('WORDVEL_LARAVEL_PATH', getenv('WORDVEL_LARAVEL_PATH') ?: dirname(__DIR__));

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';

function wordvel_env(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $path = dirname(__DIR__) . '/.env';
        $env = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
    }

    $value = getenv($key);

    if ($value !== false) {
        return $value;
    }

    return $env[$key] ?? $default;
}
PHP;
    }

    private function wordPressInstallScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

define('WP_INSTALLING', true);

$_SERVER['HTTP_HOST'] = parse_url((string) (getenv('WORDVEL_WP_HOME') ?: str_replace('-api.test', '-wp.test', wordvel_project_env('APP_URL', 'http://wordvel-wp.test'))), PHP_URL_HOST) ?: 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if (is_blog_installed()) {
    echo "WordPress is already installed.\n";

    return;
}

$result = wp_install(
    blog_title: getenv('WORDVEL_WP_TITLE') ?: wordvel_project_env('APP_NAME', 'WordVel'),
    user_name: getenv('WORDVEL_WP_ADMIN_USER') ?: 'admin',
    user_email: getenv('WORDVEL_WP_ADMIN_EMAIL') ?: 'admin@example.test',
    is_public: false,
    deprecated: '',
    user_password: getenv('WORDVEL_WP_ADMIN_PASSWORD') ?: 'password'
);

$userId = is_array($result) ? ($result['user_id'] ?? 'unknown') : $result;

echo "Installed WordPress with admin user {$userId}.\n";

function wordvel_project_env(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $path = dirname(__DIR__) . '/.env';
        $env = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
    }

    $value = getenv($key);

    if ($value !== false) {
        return $value;
    }

    return $env[$key] ?? $default;
}
PHP;
    }

    private function wordPressThemeScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = parse_url((string) (getenv('WORDVEL_WP_HOME') ?: str_replace('-api.test', '-wp.test', wordvel_project_env('APP_URL', 'http://wordvel-wp.test'))), PHP_URL_HOST) ?: 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once __DIR__ . '/wp-load.php';

switch_theme('wordvel-headless');
flush_rewrite_rules();

echo "Activated wordvel-headless theme.\n";

function wordvel_project_env(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $path = dirname(__DIR__) . '/.env';
        $env = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
    }

    $value = getenv($key);

    if ($value !== false) {
        return $value;
    }

    return $env[$key] ?? $default;
}
PHP;
    }

    private function wordPressPermalinksScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = parse_url((string) (getenv('WORDVEL_WP_HOME') ?: str_replace('-api.test', '-wp.test', wordvel_project_env('APP_URL', 'http://wordvel-wp.test'))), PHP_URL_HOST) ?: 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require_once __DIR__ . '/wp-load.php';

update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules();

echo "Pretty permalinks enabled.\n";

function wordvel_project_env(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $path = dirname(__DIR__) . '/.env';
        $env = is_file($path) ? parse_ini_file($path, false, INI_SCANNER_RAW) : [];
    }

    $value = getenv($key);

    if ($value !== false) {
        return $value;
    }

    return $env[$key] ?? $default;
}
PHP;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }
}
