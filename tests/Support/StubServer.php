<?php

declare(strict_types=1);

namespace GenderApi\Tests\Support;

/**
 * Starts PHP's built-in web server on 127.0.0.1 with stub-server.php as router.
 */
final class StubServer
{
    /** @var resource */
    private $process;

    private function __construct($process, public readonly int $port, public readonly string $logFile)
    {
        $this->process = $process;
    }

    public static function start(): self
    {
        $port = self::freePort();
        $logFile = (string) tempnam(sys_get_temp_dir(), 'genderapi-stub-');
        $env = getenv();
        $env['STUB_LOG'] = $logFile;
        $env['PHP_CLI_SERVER_WORKERS'] = '4';
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/stub-server.php'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            $env,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start the PHP built-in server.');
        }
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($socket !== false) {
                fclose($socket);

                return new self($process, $port, $logFile);
            }
            usleep(50_000);
        }
        proc_terminate($process);
        throw new \RuntimeException('The PHP built-in server did not start.');
    }

    public static function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            throw new \RuntimeException('No free port: ' . $errstr);
        }
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    public function url(string $scenario): string
    {
        return 'http://127.0.0.1:' . $this->port . '/' . $scenario . '/api/v2';
    }

    public function hits(string $scenario): int
    {
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES) ?: [];

        return count(array_filter($lines, static fn (string $l): bool => str_contains($l, ' /' . $scenario . '/')));
    }

    public function stop(): void
    {
        // With PHP_CLI_SERVER_WORKERS the master does not stop its workers on SIGTERM.
        $pid = proc_get_status($this->process)['pid'];
        $killer = proc_open(['pkill', '-TERM', '-P', (string) $pid], [], $pipes);
        if (is_resource($killer)) {
            proc_close($killer);
        }
        proc_terminate($this->process);
        proc_close($this->process);
        @unlink($this->logFile);
    }
}
