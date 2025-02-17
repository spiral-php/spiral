<?php

declare(strict_types=1);

namespace Spiral\Tests\Boot\BootloadManager;

use Spiral\Boot\BootloadManager\Initializer;
use Spiral\Tests\Boot\TestCase;

abstract class InitializerTestCase extends TestCase
{
    protected Initializer $initializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializer = new Initializer(
            $this->container,
            $this->container,
        );
    }
}
