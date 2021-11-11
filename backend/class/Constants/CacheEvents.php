<?php declare(strict_types = 1);
namespace noxkiwi\cache\Constants;

/**
 * I am the base Cache class.
 *
 * @package      noxkiwi\cache\Constants
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2021 noxkiwi
 * @version      1.0.0
 * @link         https://nox.kiwi/
 */
abstract class CacheEvents
{
    public const SET  = 'CACHE_SET';
    public const GET  = 'CACHE_GET';
    public const HIT  = 'CACHE_HIT';
    public const MISS = 'CACHE_MISS';
}
