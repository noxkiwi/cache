<?php declare(strict_types = 1);
namespace noxkiwi\cache\Cache;

use Exception;
use Memcached;
use noxkiwi\cache\Cache;
use noxkiwi\cache\Constants\CacheEvents;
use noxkiwi\cache\Validator\Structure\ConnectionValidator;
use noxkiwi\core\ErrorHandler;
use noxkiwi\core\Exception\ConfigurationException;
use noxkiwi\core\Exception\SystemComponentException;
use function extension_loaded;
use const E_ERROR;

/**
 * I am the Memcached Cache client.
 * I also use the RuntimeCache since this cache server is centralized and may run on a different machine,
 * which may lead to latency on heavy usage.
 *
 * @package      noxkiwi\cache\Cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2016 - 2021 noxkiwi
 * @version      1.0.1
 * @link         https://nox.kiwi/
 */
final class MemcachedCache extends Cache
{
    /** @var \noxkiwi\cache\Cache\RuntimeCache I will be set during runtime */
    private RuntimeCache $runtimeCache;
    /** @var mixed I am the hostname of the Memcached cache server. */
    private string $host;
    /** @var mixed I am the port number of the Memcached cache server. */
    private int $port;
    /** @var \Memcached I am the PHP memcached Client class instance */
    private Memcached $memcached;

    /**
     * Creates instance and adds the Server
     *
     * @param array $config
     *
     * @throws \noxkiwi\core\Exception\ConfigurationException
     * @throws \noxkiwi\core\Exception\SystemComponentException
     * @throws \noxkiwi\singleton\Exception\SingletonException
     */
    protected function __construct(array $config)
    {
        if (! extension_loaded('memcached')) {
            throw new SystemComponentException('MISSING_EXTENSION_PHP_MEMCACHED', E_ERROR);
        }
        $validator = ConnectionValidator::getInstance();
        $errors    = $validator->validate($config);
        if (! empty ($errors)) {
            throw new ConfigurationException('INVALID_MEMCACHED_SETUP', E_ERROR, $errors);
        }
        $this->host    = $config['host'];
        $this->port    = $config['port'];
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;
        parent::__construct();
    }

    /**
     * @inheritDoc
     * @throws \noxkiwi\singleton\Exception\SingletonException
     */
    protected function init(): void
    {
        parent::init();
        $this->runtimeCache = RuntimeCache::getInstance();
        $this->memcached    = new Memcached();
        $this->memcached->addServer($this->host, $this->port);
    }

    /**
     * @inheritDoc
     */
    public function set(string $group, string $key, mixed $value = null, int $timeout = null): void
    {
        if ($value === null) {
            $this->logDebug("CORE_BACKEND_CLASS_CACHE_MEMCACHED_SET::EMPTY VALUE (group = $group, key= $key)");

            return;
        }
        $this->notify('CACHE_SET');
        if ($timeout === null) {
            $timeout = Cache::DEFAULT_TIMEOUT;
        }
        $this->logDebug("CORE_BACKEND_CLASS_CACHE_MEMCACHED_SET::SETTING (group = $group, key= $key)");
        $this->memcached->set("{$group}_$key", $this->compress($value));
        $this->runtimeCache->set($group, $key, $value, $timeout);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $group, string $key): bool
    {
        return $this->get($group, $key) !== null;
    }

    /**
     * @inheritDoc
     */
    public function get(string $group, string $key): mixed
    {
        if ($this->runtimeCache->exists($group, $key)) {
            return $this->runtimeCache->get($group, $key);
        }
        try {
            $get = $this->memcached->get("{$group}_$key");
            if (! $get) {
                return null;
            }
        } catch (Exception $exception) {
            ErrorHandler::handleException($exception, E_USER_NOTICE);

            return null;
        }
        $data = $this->decompress($get);
        $this->notify(CacheEvents::GET);
        if (empty($data)) {
            $this->notify(CacheEvents::MISS);

            return $data;
        }
        $this->notify(CacheEvents::HIT);
        $this->runtimeCache->set($group, $key, $data);

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function clearKey(string $group, string $key): void
    {
        $this->logDebug("CORE_BACKEND_CLASS_CACHE_MEMCACHED_CLEARKEY::CLEARING (group = $group, key= $key)");
        $this->clear("{$group}_$key");
    }

    /**
     * @inheritDoc
     */
    public function clear(string $key): void
    {
        $this->runtimeCache->clear($key);
        $this->logDebug('CORE_BACKEND_CLASS_CACHE_MEMCACHED_CLEAR::CLEARING {key}', ['key' => $key]);
        $this->memcached->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function getAllKeys(): array
    {
        return $this->memcached->getAllKeys();
    }
}
