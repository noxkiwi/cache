<?php declare(strict_types = 1);
namespace noxkiwi\cache\Cache;

use JetBrains\PhpStorm\Pure;
use noxkiwi\cache\Cache;
use noxkiwi\core\Request;
use function array_keys;

/**
 * I am the Runtime Cache client.
 * I will use the request's non-persistent scope until the end of the PHP process' life.
 *
 * On CLI requests, however, I will be disabled to enforce loading of data when using consumers, etc.
 *
 * @package      noxkiwi\cache\Cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 noxkiwi
 * @version      1.0.2
 * @link         https://nox.kiwi/
 */
final class RuntimeCache extends Cache
{
    protected const USE_DRIVER = false;
    /** @var array I am the list of cached entries as a key-value storage. */
    private array $runtimeCache = [];

    /**
     * @inheritDoc
     */
    public function set(string $group, string $key, mixed $value = null, int $timeout = null): void
    {
        if ($value === null) {
            $this->logDebug("CORE_BACKEND_CLASS_CACHE_RUNTIMECACHE_SET::EMPTY_VALUE (group = $group, key=$key)");

            return;
        }
        $this->notify('CACHE_SET');
        $this->logDebug("CORE_BACKEND_CLASS_CACHE_RUNTIMECACHE_SET::SETTING (group = $group, key=$key)");
        $this->runtimeCache["{$group}_$key"] = $value;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $group, string $key): bool
    {
        return isset($this->runtimeCache["{$group}_$key"]);
    }

    /**
     * @inheritDoc
     * If the Request came via CLI, this cache is disabled.
     */
    #[Pure] public function get(string $group, string $key): mixed
    {
        if (Request::isCli()) {
            return null;
        }

        return $this->runtimeCache["{$group}_$key"] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function clearKey(string $group, string $key): void
    {
        $this->clear("{$group}_$key");
    }

    /**
     * @inheritDoc
     */
    public function clear(string $key): void
    {
        if (! isset($this->runtimeCache[$key])) {
            return;
        }
        unset($this->runtimeCache[$key]);
    }

    /**
     * @inheritDoc
     */
    public function getAllKeys(): array
    {
        return array_keys($this->runtimeCache);
    }
}
