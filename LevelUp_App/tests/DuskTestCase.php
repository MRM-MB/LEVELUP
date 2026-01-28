<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;
use Symfony\Component\Process\Process;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Reference to the PHP built-in server process spun up for browser tests.
     */
    protected static ?Process $laravelServer = null;

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        static::ensureSqliteDatabaseExists();

        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }

        static::startLaravelServer();
    }

    /**
     * Tear down shared resources once the browser test suite has finished.
     */
    #[AfterClass]
    public static function destroy(): void
    {
        static::stopLaravelServer();
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Ensure the sqlite database file exists before migrations run.
     */
    protected static function ensureSqliteDatabaseExists(): void
    {
        $databasePath = static::projectPath('database/database.sqlite');

        if (! file_exists($databasePath)) {
            $directory = dirname($databasePath);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            touch($databasePath);
        }
    }

    /**
     * Boot a dedicated PHP server that serves the application during Dusk runs.
     */
    protected static function startLaravelServer(): void
    {
        if (static::$laravelServer instanceof Process && static::$laravelServer->isRunning()) {
            return;
        }

        if ($socket = @fsockopen('127.0.0.1', 8000)) {
            fclose($socket);

            return;
        }

        $command = [
            PHP_BINARY,
            'artisan',
            'serve',
            '--host=127.0.0.1',
            '--port=8000',
        ];

        static::$laravelServer = new Process($command, static::projectPath());
        static::$laravelServer->setTimeout(null);
        static::$laravelServer->start();

        $attempts = 0;

        while ($attempts < 40) {
            if (! static::$laravelServer->isRunning()) {
                $errorOutput = trim(static::$laravelServer->getErrorOutput());

                throw new RuntimeException(
                    'Laravel test server terminated unexpectedly'.($errorOutput !== '' ? ': '.$errorOutput : '.')
                );
            }

            if ($socket = @fsockopen('127.0.0.1', 8000)) {
                fclose($socket);
                return;
            }

            usleep(250000);
            $attempts++;
        }

        throw new RuntimeException('Timed out waiting for the Laravel test server to start on http://127.0.0.1:8000.');
    }

    /**
     * Stop the PHP server that was started for browser tests.
     */
    protected static function stopLaravelServer(): void
    {
        if (static::$laravelServer instanceof Process) {
            static::$laravelServer->stop();
            static::$laravelServer = null;
        }
    }

    /**
     * Resolve a path relative to the project root without relying on the Laravel helpers.
     */
    protected static function projectPath(string $path = ''): string
    {
        $base = dirname(__DIR__);

        if ($path === '') {
            return $base;
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));

        return $base.DIRECTORY_SEPARATOR.$relative;
    }
}
