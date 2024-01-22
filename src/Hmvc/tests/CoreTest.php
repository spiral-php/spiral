<?php

declare(strict_types=1);

namespace Spiral\Tests\Core;

use PHPUnit\Framework\TestCase;
use Spiral\Core\Container;
use Spiral\Core\Exception\ControllerException;
use Spiral\Core\Scope;
use Spiral\Framework\ScopeName;
use Spiral\Tests\Core\Fixtures\CleanController;
use Spiral\Tests\Core\Fixtures\DummyController;
use Spiral\Tests\Core\Fixtures\SampleCore;

final class CoreTest extends TestCase
{
    private Container $root;

    protected function setUp(): void
    {
        $this->root = new Container();
    }

    public function testCallAction(): void
    {
        $core = new SampleCore($this->root);

        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('Hello, Antony.', $core->callAction(
                DummyController::class,
                'index',
                ['name' => 'Antony']
            ));
        });
    }

    public function testCallActionDefaultParameter(): void
    {
        $core = new SampleCore($this->root);

        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('Hello, Dave.', $core->callAction(
                DummyController::class,
                'index'
            ));
        });
    }

    public function testCallActionDefaultAction(): void
    {
        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('Hello, Dave.', $core->callAction(
                DummyController::class,
                'index'
            ));
        });
    }

    public function testCallActionDefaultActionWithParameter(): void
    {
        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('Hello, Antony.', $core->callAction(
                DummyController::class,
                'index',
                ['name' => 'Antony']
            ));
        });
    }

    public function testCallActionMissingParameter(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(DummyController::class, 'required');
        });
    }

    public function testCallActionInvalidParameter(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(DummyController::class, 'required', ['id' => null]);
        });
    }

    public function testCallWrongController(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(BadController::class, 'index', ['name' => 'Antony']);
        });
    }

    public function testCallBadAction(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(DummyController::class, 'missing', [
                'name' => 'Antony',
            ]);
        });
    }

    public function testStaticAction(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(DummyController::class, 'inner');
        });
    }

    public function testInheritedAction(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(DummyController::class, 'execute');
        });
    }

    public function testInheritedActionCall(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(DummyController::class, 'call');
        });
    }

    public function testCallNotController(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $core->callAction(SampleCore::class, 'index', [
                'name' => 'Antony',
            ]);
        });
    }

    public function testCleanController(): void
    {
        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('900', $core->callAction(
                CleanController::class,
                'test',
                ['id' => '900']
            ));
        });
    }

    public function testCleanControllerError(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('900', $core->callAction(
                CleanController::class,
                'test',
                ['id' => null]
            ));
        });
    }

    public function testCleanControllerError2(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('900', $core->callAction(
                CleanController::class,
                'test',
                []
            ));
        });
    }

    public function testCleanControllerError3(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('900', $core->callAction(
                CleanController::class,
                'invalid',
                []
            ));
        });
    }

    public function testCleanControllerError4(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('900', $core->callAction(
                CleanController::class,
                'another',
                []
            ));
        });
    }

    public function testMissingDependency(): void
    {
        $this->expectException(ControllerException::class);

        $core = new SampleCore($this->root);
        $this->root->runScope(new Scope(ScopeName::Http), function () use ($core): void {
            $this->assertSame('900', $core->callAction(
                CleanController::class,
                'missing',
                []
            ));
        });
    }
}
