<?php declare(strict_types = 1);
namespace noxkiwi\cache\Interfaces;

/**
 * I am the interface, shared by all Cache drivers.
 *
 * @package      noxkiwi\cache\Interfaces
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 noxkiwi
 * @version      1.0.0
 * @link         https://nox.kiwi/
 */
interface CacheInterface
{
    /**
     * I will return the value of the cache element that is identified by the given $group and $key.
     * I may return null if the key cannot be found.
     *
     * @param string $group
     * @param string $key
     *
     * @return       mixed
     */
    public function get(string $group, string $key): mixed;

    /**
     * I will store the given $value on the cache service.
     * It will be accessed by the given $group and $key.
     * It will expire after $timeout seconds.
     *
     * @param string   $group
     * @param string   $key
     * @param mixed    $value
     * @param int|null $timeout
     */
    public function set(string $group, string $key, mixed $value = null, int $timeout = null): void;

    /**
     * I will return true if there is a cache entry identified by $group and $key.
     * Otherwise, I will return false.
     *
     * @param string $group
     * @param string $key
     *
     * @return       bool
     */
    public function exists(string $group, string $key): bool;

    /**
     * I will clear the cache element identified by the given $group and $key.
     *
     * @param string $group
     * @param string $key
     */
    public function clearKey(string $group, string $key): void;

    /**
     * I will clear the given $key on the cache service.
     *
     * @param string $key
     */
    public function clear(string $key): void;

    /**
     * I will a string array of all cached elements that exist on the cache service.
     * @return string[]
     */
    public function getAllKeys(): array;
}
