<?php declare(strict_types = 1);
namespace noxkiwi\cache\Cache;

use noxkiwi\cache\Cache;
use noxkiwi\cache\Observer\CacheObserver;
use noxkiwi\core\Exception\SystemComponentException;
use function apcu_delete;
use function apcu_exists;
use function apcu_fetch;
use function apcu_store;
use function extension_loaded;
use function str_replace;
use function strtoupper;
use const E_ERROR;

/**
 * I am the PHP APCU Cache client.
 *
 * @package      noxkiwi\cache\Cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 - 2022 noxkiwi
 * @version      1.0.1
 * @link         https://nox.kiwi/
 */
final class ApcuCache extends Cache
{
    protected const USE_DRIVER = false;

    /**
     * @throws \noxkiwi\core\Exception\SystemComponentException If the APCU Extension is not loaded.
     */
    protected function init(): void
    {
        if (! extension_loaded('apcu')) {
            throw new SystemComponentException('MISSING_EXTENSION_PHP_APCU', E_ERROR);
        }
        parent::init();
    }

    /**
     * @inheritDoc
     */
    public function set(string $group, string $key, mixed $value = null, int $timeout = null): void
    {
        if ($value === null) {
            $this->clearKey($group, $key);

            return;
        }
        $this->notify(CacheObserver::NOTIFY_SET);
        apcu_store(self::getKeyName($group, $key), $this->compress($value), $timeout ?? Cache::DEFAULT_TIMEOUT);
    }

    /**
     * I will normalize the given $group and $key into a string.
     *
     * @param string $group
     * @param string $key
     *
     * @return string
     */
    private static function getKeyName(string $group, string $key): string
    {
        return strtoupper(str_replace('\\', '_', "{$group}_$key"));
    }

    /**
     * @inheritDoc
     */
    public function exists(string $group, string $key): bool
    {
        return apcu_exists(self::getKeyName($group, $key));
    }

    /**
     * @inheritDoc
     */
    public function get(string $group, string $key): mixed
    {
        $get = apcu_fetch(self::getKeyName($group, $key));
        if ($get === false) {
            $this->notify(CacheObserver::NOTIFY_MISS);

            return null;
        }
        $data = $this->decompress((string)$get);
        $this->notify(CacheObserver::NOTIFY_HIT);

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function clearKey(string $group, string $key): void
    {
        $this->clear(self::getKeyName($group, $key));
    }

    /**
     * @inheritDoc
     */
    public function clear(string $key): void
    {
        apcu_delete($key);
    }

    /**
     * @inheritDoc
     */
    public function getAllKeys(): array
    {
        return [];
    }
}
