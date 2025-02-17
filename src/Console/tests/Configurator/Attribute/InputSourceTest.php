<?php

namespace Spiral\Tests\Console\Configurator\Attribute;

use PHPUnit\Framework\TestCase;
use Spiral\Attributes\Factory;
use Spiral\Console\Configurator\Attribute\Parser;
use Spiral\Tests\Console\Fixtures\Attribute\WithInvokeInputParameterCommand;
use Spiral\Tests\Console\Fixtures\Attribute\WithPerformInputParameterCommand;

class InputSourceTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser((new Factory())->create());
    }

    public function testWithInvokeMethod(): void
    {
        $result = $this->parser->parse(new \ReflectionClass(WithInvokeInputParameterCommand::class));

        self::assertNotEmpty($result->arguments);
        self::assertNotEmpty($result->options);
    }

    public function testWithPerformMethod(): void
    {
        $result = $this->parser->parse(new \ReflectionClass(WithPerformInputParameterCommand::class));

        self::assertNotEmpty($result->arguments);
        self::assertNotEmpty($result->options);
    }
}
