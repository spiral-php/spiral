<?php

declare(strict_types=1);

namespace Framework\Framework;

use Spiral\App\TestApp;
use Spiral\Tests\Framework\BaseTestCase;

final class KernelTest extends BaseTestCase
{
    public function testAppBootingCallbacks(): void
    {
        $kernel = $this->createAppInstance();

        $kernel->appBooting(static function (TestApp $core): void {
            $core->getContainer()->bind('abc', 'foo');
        });

        $kernel->appBooting(static function (TestApp $core): void {
            $core->getContainer()->bind('bcd', 'foo');
        });

        $kernel->appBooted(static function (TestApp $core): void {
            $core->getContainer()->bind('cde', 'foo');
        });

        $kernel->appBooted(static function (TestApp $core): void {
            $core->getContainer()->bind('def', 'foo');
        });

        $kernel->run();

        $this->assertTrue($kernel->getContainer()->has('abc'));
        $this->assertTrue($kernel->getContainer()->has('bcd'));
        $this->assertTrue($kernel->getContainer()->has('cde'));
        $this->assertTrue($kernel->getContainer()->has('def'));
    }
}
