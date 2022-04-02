<?php

declare(strict_types=1);

namespace Spiral\Console;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Console\Logger\DebugListener;
use Spiral\Exceptions\ConsoleHandler;
use Spiral\Snapshots\SnapshotterInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Manages Console commands and exception. Lazy loads console service.
 */
final class ConsoleDispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly EnvironmentInterface $env,
        private readonly FinalizerInterface $finalizer,
        private readonly ContainerInterface $container
    ) {
    }

    public function canServe(): bool
    {
        // only run in pure CLI more, ignore under RoadRunner
        return (PHP_SAPI === 'cli' && $this->env->get('RR') === null);
    }

    public function serve(InputInterface $input = null, OutputInterface $output = null): int
    {
        // On demand to save some memory.

        $output ??= new ConsoleOutput();

        /** @var DebugListener $listener */
        $listener = $this->container->get(DebugListener::class);
        $listener = $listener->withOutput($output)->enable();

        /** @var Console $console */
        $console = $this->container->get(Console::class);

        try {
            return $console->start($input ?? new ArgvInput(), $output);
        } catch (Throwable $e) {
            $this->handleException($e, $output);

            return 255;
        } finally {
            $listener->disable();
            $this->finalizer->finalize(false);
        }
    }

    protected function handleException(Throwable $e, OutputInterface $output): void
    {
        try {
            $this->container->get(SnapshotterInterface::class)->register($e);
        } catch (Throwable | ContainerExceptionInterface) {
            // no need to notify when unable to register an exception
        }

        // Explaining exception to the user
        $handler = new ConsoleHandler(STDERR);
        $output->write($handler->renderException($e, $this->mapVerbosity($output)));
    }

    private function mapVerbosity(OutputInterface $output): int
    {
        return match (true) {
            $output->isDebug() => ConsoleHandler::VERBOSITY_DEBUG,
            $output->isVeryVerbose() => ConsoleHandler::VERBOSITY_VERBOSE,
            default => ConsoleHandler::VERBOSITY_BASIC
        };
    }
}
