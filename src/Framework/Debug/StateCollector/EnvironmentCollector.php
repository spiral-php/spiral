<?php

declare(strict_types=1);

namespace Spiral\Debug\StateCollector;

use Psr\Container\ContainerInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Debug\StateCollectorInterface;
use Spiral\Debug\StateInterface;
use Spiral\Http\SapiDispatcher;

final class EnvironmentCollector implements StateCollectorInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EnvironmentInterface $env
    ) {
    }

    public function populate(StateInterface $state): void
    {
        $state->setTag('php', \phpversion());

        if (
            $this->container->has(DispatcherInterface::class) &&
            $this->container->get(DispatcherInterface::class) instanceof SapiDispatcher
        ) {
            $state->setTag('dispatcher', 'sapi');
        }

        $state->setVariable('environment', $this->env->getAll());
    }
}
