<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot;

use Spiral\Boot\BootloadManager\AttributeResolver;
use Spiral\Boot\BootloadManager\InitializerInterface;
use Spiral\Boot\BootloadManager\InvokerStrategyInterface;
use Spiral\Boot\BootloadManager\StrategyBasedBootloadManager;
use Spiral\Boot\BootloadManager\DefaultInvokerStrategy;
use Spiral\Boot\BootloadManager\Initializer;
use Spiral\Boot\Environment;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\Container;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected Container $container;

    public function getBootloadManager(): StrategyBasedBootloadManager
    {
        $this->container->bind(AttributeResolver::class, AttributeResolver::class);
        $this->container->bind(
            InitializerInterface::class,
            $initializer = new Initializer($this->container, $this->container),
        );

        $this->container->bind(
            InvokerStrategyInterface::class,
            $invoker = new DefaultInvokerStrategy($initializer, $this->container, $this->container),
        );

        return new StrategyBasedBootloadManager($invoker, $this->container, $initializer);
    }

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->bindSingleton(EnvironmentInterface::class, Environment::class, true);
    }
}
