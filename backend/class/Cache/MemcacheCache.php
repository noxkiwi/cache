<?php declare(strict_types = 1);
namespace noxkiwi\cache\Cache;

use Exception;
use Memcache;
use noxkiwi\cache\Cache;
use noxkiwi\cache\Constants\CacheEvents;
use noxkiwi\cache\Validator\Structure\ConnectionValidator;
use noxkiwi\core\ErrorHandler;
use noxkiwi\core\Exception\ConfigurationException;
use noxkiwi\core\Exception\SystemComponentException;
use noxkiwi\core\Request;
use function explode;
use function extension_loaded;
use function fclose;
use function feof;
use function fgets;
use function fsockopen;
use function fwrite;
use function in_array;
use function preg_match;
use function preg_match_all;
use function str_contains;
use const E_ERROR;
use const E_USER_NOTICE;

/**
 * I am the Memcached Cache client.
 * I also use the RuntimeCache since this cache server is centralized and may run on a different machine,
 * which may lead to latency on heavy usage.
 *
 * @package      noxkiwi\cache\Cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 - 2022 noxkiwi
 * @version      1.0.2
 * @link         https://nox.kiwi/
 */
final class MemcacheCache extends Cache
{
    /** @var \noxkiwi\cache\Cache I will be set during runtime */
    private Cache $runtimeCache;
    /** @var mixed I am the hostname of the Memcached cache server. */
    private string $host;
    /** @var mixed I am the port number of the Memcached cache server. */
    private int $port;
    /** @var \Memcache I am the PHP memcached Client class instance */
    private Memcache $memcache;

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
        if (! extension_loaded('memcache')) {
            throw new SystemComponentException('MISSING_EXTENSION_PHP_MEMCACHED', E_ERROR);
        }
        $validator = ConnectionValidator::getInstance();
        $errors    = $validator->validate($config);
        if (! empty ($errors)) {
            throw new ConfigurationException('INVALID_MEMCACHE_SETUP', E_ERROR, $errors);
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
        $this->memcache     = new Memcache();
        $this->memcache->addServer($this->host, $this->port);
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
        $this->notify('CACHE_SET');
        if ($timeout === null) {
            $timeout = Cache::DEFAULT_TIMEOUT;
        }
        $this->logDebug("CORE_BACKEND_CLASS_CACHE_MEMCACHED_SET::SETTING (group = $group, key= $key)");
        $this->memcache->set("{$group}_$key", $this->compress($value));
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
        if (! Request::isCli() && $this->runtimeCache->exists($group, $key)) {
            return $this->runtimeCache->get($group, $key);
        }
        try {
            $get = $this->memcache->get("{$group}_$key");
            if (! $get) {
                return null;
            }
        } catch (Exception $exception) {
            ErrorHandler::handleException($exception, E_USER_NOTICE);

            return null;
        }
        $data = $this->decompress($get);
        $this->notify(CacheEvents::GET);
        if ($data === null) {
            $this->notify(CacheEvents::MISS);

            return null;
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
        $this->memcache->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function getAllKeys(): array
    {
        /**
         * Taken directly from memcache PECL source
         * http://pecl.php.net/package/memcache
         */
        $string   = $this->sendCommand('stats items');
        $lines    = explode("\r\n", $string);
        $slabs    = [];
        $elements = [];
        foreach ($lines as $line) {
            if (! preg_match('/STAT items:([\d]+):number ([\d]+)/', $line, $matches)) {
                continue;
            }
            if (isset($matches[1]) && ! in_array($matches[1], $slabs, true)) {
                $slabs[] = $matches[1];
                $string  = $this->sendCommand('stats cachedump ' . $matches[1] . ' ' . $matches[2]);
                preg_match_all('/ITEM (.*?) /', $string, $matches);
                $elements[] = $matches[1];
            }
        }

        $return = [];
        foreach($elements as $element) {
            foreach($element as $datum) {
                $return[] = $datum;
            }
        }
        return $return;
    }

    /**
     * I will send the given $command to the current memcached Server
     * <br />I will also return the command's output as string
     *
     * @param string $command
     *
     * @return string
     */
    private function sendCommand(string $command): string
    {
        $memcachedSocket = fsockopen($this->host, $this->port);
        if (! $memcachedSocket) {
            return '';
        }
        fwrite($memcachedSocket, $command . "\r\n");
        $buf = '';
        while (! feof($memcachedSocket)) {
            $buf .= fgets($memcachedSocket, 256);
            if (str_contains($buf, "END\r\n")) {
                break;
            }
            if (str_contains($buf, "DELETED\r\n") || str_contains($buf, "NOT_FOUND\r\n")) {
                break;
            }
            if (str_contains($buf, "OK\r\n")) {
                break;
            }
        }
        fclose($memcachedSocket);

        return $buf;
    }
}
