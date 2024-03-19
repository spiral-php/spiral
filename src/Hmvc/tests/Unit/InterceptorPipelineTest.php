<?php

declare(strict_types=1);

namespace Spiral\Tests\Core\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Core\Context\Target;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;
use Spiral\Core\HandlerInterface;
use Spiral\Core\InterceptorInterface;
use Spiral\Core\InterceptorPipeline;
use Spiral\Testing\TestCase;
use Spiral\Tests\Core\Unit\Stub\AddAttributeInterceptor;
use Spiral\Tests\Core\Unit\Stub\ExceptionInterceptor;
use Spiral\Tests\Core\Unit\Stub\Legacy\LegacyChangerInterceptor;
use Spiral\Tests\Core\Unit\Stub\Legacy\LegacyStatefulInterceptor;
use Spiral\Tests\Core\Unit\Stub\StatefulInterceptor;

final class InterceptorPipelineTest extends TestCase
{
    public function testCallActionWithoutCore(): void
    {
        $pipeline = $this->createPipeline();

        self::expectExceptionMessage('Unable to invoke pipeline without last handler.');

        $pipeline->callAction('controller', 'action');
    }

    public function testHandleWithoutHandler(): void
    {
        $pipeline = $this->createPipeline();

        self::expectExceptionMessage('Unable to invoke pipeline without last handler.');

        $pipeline->handle(new \Spiral\Core\Context\CallContext(Target::fromPathArray(['controller', 'action'])));
    }

    public function testCrossCompatibility(): void
    {
        $handler = self::createMock(CoreInterface::class);
        $handler->expects(self::once())
            ->method('callAction')
            ->with('controller', 'action')
            ->willReturn('result');

        $pipeline = $this->createPipeline([
            new AddAttributeInterceptor('key', 'value'),
            new LegacyStatefulInterceptor(),
            new AddAttributeInterceptor('foo', 'bar'),
            new LegacyStatefulInterceptor(),
            $state = new StatefulInterceptor(),
        ], $handler);

        $result = $pipeline->callAction('controller', 'action');
        // Attributes won't be lost after legacy interceptor
        self::assertSame(['key' => 'value', 'foo' => 'bar'], $state->context->getAttributes());
        self::assertSame('result', $result);
    }

    public function testLegacyChangesContextPath(): void
    {
        $handler = self::createMock(CoreInterface::class);
        $handler->expects(self::once())
            ->method('callAction')
            ->with('foo', 'bar')
            ->willReturn('result');

        $pipeline = $this->createPipeline([
            new LegacyChangerInterceptor(controller: 'newController', action: 'newAction'),
            $state = new StatefulInterceptor(),
            new LegacyChangerInterceptor(controller: 'foo', action: 'bar'),
        ], $handler);

        $result = $pipeline->callAction('controller', 'action');
        // Attributes won't be lost after legacy interceptor
        self::assertSame(['newController', 'newAction'], $state->context->getTarget()->getPath());
        self::assertSame('result', $result);
    }

    public function testAttributesCompatibilityAttributes(): void
    {
        $pipeline = $this->createPipeline([
            new AddAttributeInterceptor('key', 'value'),
            new LegacyStatefulInterceptor(),
            $state = new StatefulInterceptor(),
            new ExceptionInterceptor(),
        ], self::createMock(CoreInterface::class));

        try {
            $pipeline->callAction('controller', 'action');
        } catch (\RuntimeException) {
            // Attributes won't be lost after legacy interceptor
            self::assertSame(['key' => 'value'], $state->context->getAttributes());
        }
    }

    /**
     * @param array<CoreInterceptorInterface|InterceptorInterface> $interceptors
     */
    private function createPipeline(
        array $interceptors = [],
        CoreInterface|HandlerInterface|null $lastHandler = null,
        EventDispatcherInterface|null $dispatcher = null,
    ): InterceptorPipeline
    {
        $pipeline = new InterceptorPipeline($dispatcher);

        $lastHandler instanceof CoreInterface and $pipeline = $pipeline->withCore($lastHandler);
        $lastHandler instanceof HandlerInterface and $pipeline = $pipeline->withHandler($lastHandler);

        foreach ($interceptors as $interceptor) {
            $pipeline->addInterceptor($interceptor);
        }

        return $pipeline;
    }
}
