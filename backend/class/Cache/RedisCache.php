<?php declare(strict_types = 1);
namespace noxkiwi\cache\Cache;

use noxkiwi\cache\Cache;
use noxkiwi\cache\Observer\CacheObserver;
use noxkiwi\cache\Validator\Structure\ConnectionValidator;
use noxkiwi\core\Exception\ConfigurationException;
use noxkiwi\core\Exception\SystemComponentException;
use Redis;
use function extension_loaded;
use function str_replace;
use function strtoupper;
use const E_ERROR;

/**
 * I am the PHP Redis Cache client.
 *
 * @package      noxkiwi\cache\Cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 - 2022 noxkiwi
 * @version      1.0.1
 * @link         https://nox.kiwi/
 */
final class RedisCache extends Cache
{
    /** @var \noxkiwi\cache\Cache\RuntimeCache|null I will be set during runtime */
    private ?RuntimeCache $runtimeCache;
    /** @var string I am the hostname of the Memcached cache server. */
    private string $host;
    /** @var int I am the port number of the Memcached cache server. */
    private int $port;
    /** @var \Redis I am the PHP memcached Client class instance */
    private Redis $redis;

    /**
     * @param array $config
     *
     * @throws \noxkiwi\core\Exception\SystemComponentException If the APCU Extension is not loaded.
     * @throws \noxkiwi\singleton\Exception\SingletonException
     * @throws \noxkiwi\core\Exception\ConfigurationException
     */
    protected function __construct(array $config)
    {
        if (! extension_loaded('redis')) {
            throw new SystemComponentException('MISSING_EXTENSION_PHP_REDIS', E_ERROR);
        }
        $validator = ConnectionValidator::getInstance();
        $errors    = $validator->validate($config);
        if (! empty ($errors)) {
            throw new ConfigurationException('INVALID_MEMCACHE_SETUP', E_ERROR, $errors);
        }
        $this->runtimeCache = null;
        if ((bool)($config['runtimeCache'] ?? false) === true) {
            $this->runtimeCache = RuntimeCache::getInstance();
        }
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->port    = $config['port'];
        $this->host    = $config['host'];
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function init(): void
    {
        parent::init();
        $this->redis = new Redis();
        $this->redis->connect($this->host, $this->port);
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
        $this->redis->set(self::getKeyName($group, $key), $this->compress($value), $timeout ?? Cache::DEFAULT_TIMEOUT);
        $this->runtimeCache?->set($group, $key, $value, $timeout);
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
        return (bool)$this->redis->exists(self::getKeyName($group, $key));
    }

    /**
     * @inheritDoc
     */
    public function get(string $group, string $key): mixed
    {
        if ($this->runtimeCache?->exists($group, $key)) {
            return $this->runtimeCache?->get($group, $key);
        }
        $get = $this->redis->get(self::getKeyName($group, $key));
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
        $this->redis->del($key);
        $this->runtimeCache?->clear($key);
    }

    /**
     * @inheritDoc
     */
    public function getAllKeys(): array
    {
        return [];
    }
}
