<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use function dirname;

use function file_exists;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_writable;
use Psr\SimpleCache\CacheInterface;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function var_export;

class FastRouteCache implements CacheInterface
{
    /**
     * Template used when generating the cache file.
     */
    public const CACHE_TEMPLATE = <<< 'EOT'
<?php
return %s;
EOT;
    /**
     * Cache file path relative to the project directory.
     *
     * @var string
     */
    private string $cacheFile;

    public function __construct(string $cacheFilePath)
    {
        $this->cacheFile = $cacheFilePath;
    }

    public function get($key, $default = null): array
    {
        set_error_handler(
            static function () {
            },
            E_WARNING
        ); // suppress php warnings
        $data = include $this->cacheFile;
        restore_error_handler();

        // Cache file does not exist
        if (false === $data) {
            throw new \RuntimeException(
                sprintf(
                    'File "%s"; doesn\'t exist',
                    $this->cacheFile
                )
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                sprintf(
                    'Invalid cache file "%s"; cache file MUST return an array',
                    $this->cacheFile
                )
            );
        }

        return $data;
    }

    public function set($key, $value, $ttl = null): void
    {
        $cacheDir = dirname($this->cacheFile);

        $this->checkDirectoryExists($cacheDir);
        $this->checkDirectoryWritable($cacheDir);
        $this->checkFileExistsAndWritable($this->cacheFile);
        $result = file_put_contents(
            $this->cacheFile,
            sprintf(self::CACHE_TEMPLATE, var_export($value, true)),
            LOCK_EX
        );

        if ($result === false) {
            throw new \RuntimeException(
                sprintf(
                    'Can\'t write file "%s"',
                    $this->cacheFile
                )
            );
        }
    }

    public function delete($key): bool
    {
        return unlink($this->cacheFile);
    }

    public function clear(): bool
    {
        return unlink($this->cacheFile);
    }

    public function has($key): bool
    {
        return file_exists($this->cacheFile);
    }

    public function setMultiple($values, $ttl = null)
    {
        throw new \RuntimeException('Method is not implemented');
    }

    public function getMultiple($keys, $default = null)
    {
        throw new \RuntimeException('Method is not implemented');
    }

    public function deleteMultiple($keys)
    {
        throw new \RuntimeException('Method is not implemented');
    }

    private function checkDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache directory "%s" does not exist',
                    $dir
                )
            );
        }
    }

    private function checkDirectoryWritable(string $dir): void
    {
        if (!is_writable($dir)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache directory "%s" is not writable',
                    $dir
                )
            );
        }
    }

    private function checkFileExistsAndWritable(string $file): void
    {
        if (file_exists($file) && !is_writable($file)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache file %s is not writable',
                    $file
                )
            );
        }
    }
}
