<?php

declare(strict_types=1);

namespace Spiral\Tests\Framework\Views;

use Spiral\Tests\Framework\BaseTestCase;
use Spiral\Views\GlobalVariablesInterface;
use Spiral\Views\ViewsInterface;

final class RenderTest extends BaseTestCase
{
    public function testWithNullVariable(): void
    {
        $out = $this->getContainer()->get(ViewsInterface::class)
            ->render('stempler:null', ['var' => null, 'users' => []]);

        // any exceptions threw
        self::assertIsString($out);
    }

    public function testWithNullVariableExression(): void
    {
        $out = $this->getContainer()->get(ViewsInterface::class)->render(
            'stempler:null',
            ['var' => null, 'users' => ['foo']]
        );

        // any exceptions threw
        self::assertIsString($out);
    }

    public function testWithGlobalVariable(): void
    {
        $global = $this->getContainer()->get(GlobalVariablesInterface::class);

        $global->set('bodyClass', 'global-body');
        $global->set('replaced', 'replaced-foo');

        $out = $this->getContainer()
            ->get(ViewsInterface::class)
            ->render('stempler:globalVariables', ['replaced' => 'replaced-bar']);

        self::assertStringContainsString('bar', $out);
        self::assertStringContainsString('global-body', $out);
        self::assertStringContainsString('replaced-bar', $out);
        self::assertStringNotContainsString('replaced-foo', $out);
    }
}
