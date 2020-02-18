<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use Psr\SimpleCache\CacheInterface;

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
    private string $cacheFile = __DIR__ . '/../../../../runtime/cache/fastroute.php.cache';

    public function __construct($cacheFilePath = null)
    {
        if ($cacheFilePath !== null) {
            $this->cacheFile = $cacheFilePath;
        }
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

        if (!is_dir($cacheDir)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache directory "%s" does not exist',
                    $cacheDir
                )
            );
        }

        if (!is_writable($cacheDir)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache directory "%s" is not writable',
                    $cacheDir
                )
            );
        }

        if (file_exists($this->cacheFile) && !is_writable($this->cacheFile)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache file %s is not writable',
                    $this->cacheFile
                )
            );
        }

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
}
