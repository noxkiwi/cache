<?php declare(strict_types = 1);
namespace noxkiwi\cache\Observer;

use noxkiwi\cache\Constants\CacheEvents;
use noxkiwi\observing\Observable\ObservableInterface;
use noxkiwi\observing\Observer;

/**
 * I am the Observer class for all Caches.
 *
 * @package      noxkiwi\cache\Observer
 * @author       Jan Nox <jan.nox@pm.me>
 * @license      https://nox.kiwi/license
 * @copyright    2018 - 2021 noxkiwi
 * @version      1.0.2
 * @link         https://nox.kiwi/
 */
class CacheObserver extends Observer
{
    public const NOTIFY_SET  = CacheEvents::SET;
    public const NOTIFY_GET  = CacheEvents::GET;
    public const NOTIFY_HIT  = CacheEvents::HIT;
    public const NOTIFY_MISS = CacheEvents::MISS;
    /** @var int $countMiss I am the amount of MISS cache calls. */
    public static int $countMiss = 0;
    /** @var int $countHit I am the amount of HIT cache calls. */
    public static int $countHit = 0;
    /** @var int $countSet I am the amount of SET cache calls. */
    public static int $countSet = 0;
    /** @var int $countGet I am the amount of GET cache calls. */
    public static int $countGet = 0;

    /**
     * @inheritDoc
     */
    public function update(ObservableInterface $observable, string $type): void
    {
        switch ($type) {
            case self::NOTIFY_SET:
                static::$countSet++;
                break;
            case self::NOTIFY_GET:
                static::$countGet++;
                break;
            case self::NOTIFY_HIT:
                static::$countHit++;
                break;
            case self::NOTIFY_MISS:
                static::$countMiss++;
                break;
            default:
                break;
        }
    }
}
