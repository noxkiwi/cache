<?php declare(strict_types = 1);
namespace noxkiwi\cache;

use noxkiwi\cache\Interfaces\CacheInterface;
use noxkiwi\cache\Observer\CacheObserver;
use noxkiwi\log\Traits\LogTrait;
use noxkiwi\observing\Observable\ObservableInterface;
use noxkiwi\observing\Traits\ObservableTrait;
use noxkiwi\singleton\Singleton;
use function unserialize;

/**
 * I am the base Cache class.
 *
 * @package      noxkiwi\cache
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 noxkiwi
 * @version      1.0.0
 * @link         https://nox.kiwi/
 */
abstract class Cache extends Singleton implements CacheInterface, ObservableInterface
{
    use LogTrait;
    use ObservableTrait;

    public const    DEFAULT_PREFIX  = 'CACHE_';
    public const    DEFAULT_TIMEOUT = 3600;
    protected const USE_DRIVER      = true;

    /** @var int $timeout I am the timeout in seconds until the cache drivers forget the key. */
    protected int $timeout;

    /**
     * Creates instance and adds the Server
     */
    protected function __construct()
    {
        parent::__construct();
        $this->init();
    }

    /**
     * I will prepare the cache object.
     */
    protected function init(): void
    {
        $this->attach(new CacheObserver());
    }

    /**
     * I will compress the given $data into a JSON object, making it possible to cache objects and arrays.
     *
     * @param mixed $data
     *
     * @return       string
     */
    final protected function compress(mixed $data): string
    {
        return serialize($data);
    }

    /**
     * I will decompress the given $data string into an array.
     *
     * @param string $data
     *
     * @return       mixed
     */
    final protected function decompress(string $data): mixed
    {
        return unserialize($data);
    }
}
