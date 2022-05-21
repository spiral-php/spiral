<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Logger;

use DateTime;
use Psr\Log\LoggerInterface;
use Spiral\Logger\Event\LogEvent;

/**
 * Routes log information to various listeners.
 */
final class LogFactory implements LogsInterface
{
    private ListenerRegistryInterface $listenedRegistry;

    public function __construct(ListenerRegistryInterface $listenedRegistry)
    {
        $this->listenedRegistry = $listenedRegistry;
    }

    public function getLogger(string $channel): LoggerInterface
    {
        return new NullLogger([$this, 'log'], $channel);
    }

    /**
     * @param string $channel
     * @param mixed  $level
     * @param string $message
     */
    public function log($channel, $level, $message, array $context = []): void
    {
        $e = new LogEvent(
            new DateTime(),
            $channel,
            $level,
            $message,
            $context
        );

        foreach ($this->listenedRegistry->getListeners() as $listener) {
            call_user_func($listener, $e);
        }
    }
}
