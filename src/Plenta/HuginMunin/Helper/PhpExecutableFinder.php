<?php

declare(strict_types=1);

/**
 * Hugin and Munin
 *
 * @copyright     Copyright (c) 2023, Plenta.io
 * @author        Plenta.io <https://plenta.io>
 * @link          https://jobboerse-software.de
 * @license       proprietary
 */

namespace Plenta\HuginMunin\Helper;

use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class PhpExecutableFinder
{
    private const PHP_BINARIES = [
        '/opt/plesk/php/{major}.{minor}/bin/php',
        '/bin/php{major}{minor}',
        '/opt/RZphp{major}{minor}/bin/php-cli',
        '/opt/alt/php{major}{minor}/usr/bin/php',
        '/opt/php-{major}.{minor}.{release}/bin/php',
        '/opt/php-{major}.{minor}/bin/php',
        '/opt/php{major}.{minor}/bin/php',
        '/opt/php{major}{minor}/bin/php',
        '/opt/php{major}/bin/php',
        '/usr/bin/php{major}.{minor}-cli',
        '/usr/bin/php{major}.{minor}',
        '/usr/bin/php{major}{minor}',
        '/usr/bin/php{major}{minor}/php{major}',
        '/usr/bin/php{major}',
        '/usr/bin/php',
        '/usr/iports/php{major}{minor}/bin/php',
        '/usr/lib/cgi-bin/php{major}.{minor}',
        '/usr/lib64/php{major}.{minor}/bin/php',
        '/usr/local/bin/edis-php-cli-{major}{minor}-stable-openssl',
        '/usr/local/bin/edis-php-cli-{major}{minor}',
        '/usr/local/bin/php_cli',
        '/usr/local/bin/php',
        '/usr/local/bin/php{major}-{major}{minor}LATEST-CLI',
        '/usr/local/bin/php{major}.{minor}.{release}-cli',
        '/usr/local/php-{major}.{minor}/bin/php',
        '/usr/local/php{major}{minor}/bin/php',
        '/usr/local/phpfarm/inst/php-{major}.{minor}/bin/php',
        '/usr/local/php{major}{minor}/bin/php',
        '/opt/phpbrew/php/php-{major}.{minor}/bin/php',
        '/opt/phpfarm/inst/php-{major}.{minor}/bin/php-cgi',
        '/vrmd/webserver/php{major}{minor}/bin/php',
        '/package/host/localhost/php-{major}.{minor}/bin/php',
        '/Applications/MAMP/bin/php/php{major}.{minor}.{release}/bin/php',
        'C:\XAMPP\php\php.exe',
        'D:\XAMPP\php\php.exe',
        'C:\MAMP\bin\php\php{major}.{minor}.{release}\php.exe',
        'D:\MAMP\bin\php\php{major}.{minor}.{release}\php.exe',
        'D:\laragon\bin\php\php-{major}.{minor}.{release}-Win32-VC15-x64\php.EXE',
        'C:\laragon\bin\php\php-{major}.{minor}.{release}-Win32-VC15-x64\php.EXE',
    ];

    private array $names = ['php-cli', 'php'];

    public function getExecutable(): ?string
    {
        $paths = [];

        foreach (self::PHP_BINARIES as $path) {
            $paths[] = $this->getPhpVersionPath($path);
        }

        return $this->find($paths);
    }

    /**
     * Finds the best matching PHP executable on the system.
     * Contrary to symfony/process PhpExecutableFinder we actually test if the binary is
     * the same version as the currently running web process.
     */
    public function find(array $paths = [], bool $discover = true): ?string
    {
        if (!$discover) {
            return $this->findBestBinary($paths);
        }

        if ($bin = \constant('PHP_BINARY')) {
            if (false !== ($suffix = strrchr(basename($bin), '-'))) {
                $php = substr($bin, 0, -\strlen($suffix));
                $paths[] = $php.'-cli';
                $paths[] = $php;
            }

            $paths[] = $bin.'-cli';
            $paths[] = $bin;

            $this->includePath($paths, \dirname($bin));
        }

        if (\PHP_BINDIR) {
            $this->includePath($paths, \PHP_BINDIR);
        }

        if ($php = getenv('PHP_PATH')) {
            $paths[] = $php;
        }

        if ($php = getenv('PHP_PEAR_PHP_BIN')) {
            $paths[] = $php;
        }

        $paths[] = '/usr/bin/php';

        $paths = array_merge($paths, $this->findExecutables());
        $paths = array_unique($paths);

        ksort($paths);

        return $this->findBestBinary($paths);
    }

    public function getServerInfo(string $cli): ?array
    {
        $arguments = [$cli, '-q'];

        if ('' !== ($phar = \Phar::running(false))) {
            $arguments[] = $phar;
        } else {
            $arguments[] = \dirname(__DIR__).'/console';
        }

        $arguments[] = 'test';

        try {
            $process = new Process($arguments);
            $process->mustRun(null, array_map(static fn () => false, $_ENV));
            $output = @json_decode(trim($process->getOutput()), true);

            if (null === $output) {
                throw new RuntimeException('Unexpected output from "'.implode(' ', $arguments).'": '.$process->getOutput());
            }

            return $output;
        } catch (RuntimeException $e) {
            // Do not log every attempt to find a PHP binary (exit code 127 = Command not found)
            if (127 === $process->getExitCode()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Finds PHP executables within open_basedir or PATH environment variable.
     */
    private function findExecutables(): array
    {
        $results = [];

        if (\ini_get('open_basedir')) {
            $searchPath = explode(\PATH_SEPARATOR, \ini_get('open_basedir'));
            $dirs = [];

            foreach ($searchPath as $path) {
                // Silencing against https://bugs.php.net/69240
                if (@is_dir($path)) {
                    $dirs[] = $path;
                } elseif (\in_array(basename($path), $this->names, true) && @is_executable($path)) {
                    $results[] = $path;
                }
            }
        } else {
            $dirs = [];

            if ($path = (getenv('PATH') ?: getenv('Path'))) {
                $dirs = explode(\PATH_SEPARATOR, $path);
            }

            if ('\\' === \DIRECTORY_SEPARATOR) {
                $dirs[] = 'C:\xampp\php\\';
            }
        }

        $suffixes = [''];

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $pathExt = getenv('PATHEXT');
            $suffixes = array_merge(
                $suffixes,
                $pathExt ? explode(\PATH_SEPARATOR, $pathExt) : ['.exe', '.bat', '.cmd', '.com']
            );
        }

        foreach ($this->names as $name) {
            foreach ($suffixes as $suffix) {
                foreach ($dirs as $dir) {
                    if (
                        @is_file($file = $dir.\DIRECTORY_SEPARATOR.$name.$suffix)
                        && ('\\' === \DIRECTORY_SEPARATOR || is_executable($file))
                    ) {
                        $results[] = $file;
                    }
                }
            }
        }

        return $results;
    }

    private function findBestBinary(array $paths): ?string
    {
        $fallback = null;
        $sapi = null;

        if ($openBasedir = \ini_get('open_basedir')) {
            $openBasedir = explode(\PATH_SEPARATOR, $openBasedir);
        }

        foreach ($paths as $path) {
            // we only test for is_executable if no open_basedir restrictions are set
            // or the target is within allowed paths. If the path is not within open_basedir
            // we can still execute the binary on the command line and check the version.

            if ((!$openBasedir || $this->isAllowed($path, $openBasedir)) && !is_executable($path)) {
                continue;
            }

            try {
                $info = $this->getServerInfo($path);
            } catch (RuntimeException $e) {
                continue;
            }

            if (!\is_array($info)) {
                continue;
            }

            if ('cli' === $info['sapi'] && version_compare(\PHP_VERSION, $info['version'], 'eq')) {
                return $path;
            }

            $vWeb = \PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION;
            $vCli = vsprintf('%s.%s', explode('.', $info['version']));

            if (
                null === $fallback
                || ('cli' !== $sapi && 'cli' === $info['sapi'] && version_compare($vWeb, $vCli, 'eq'))
            ) {
                $fallback = $path;
                $sapi = $info['sapi'];
            }
        }

        return $fallback;
    }

    /**
     * Tests if the given path is within any of the given directories.
     */
    private function isAllowed(string $path, array $dirs): bool
    {
        foreach ($dirs as $dir) {
            if (str_starts_with($path, $dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds the all binaries for given path to paths array.
     */
    private function includePath(array &$paths, string $path): void
    {
        foreach ($this->names as $name) {
            $paths[] = $path.\DIRECTORY_SEPARATOR.$name;
        }
    }

    private function getPhpVersionPath(string $path): string
    {
        return str_replace(
            [
                '{major}',
                '{minor}',
                '{release}',
                '{extra}',
            ],
            [
                \PHP_MAJOR_VERSION,
                \PHP_MINOR_VERSION,
                \PHP_RELEASE_VERSION,
                \PHP_EXTRA_VERSION,
            ],
            $path
        );
    }
}
