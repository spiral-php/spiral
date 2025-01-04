<?php

declare(strict_types=1);

namespace Framework\Bootloader\Tokenizer;

use Spiral\App\Tokenizer\TestInterface;
use Spiral\Testing\Attribute\Env;
use Spiral\Tests\Framework\BaseTestCase;
use Spiral\Tokenizer\Bootloader\TokenizerBootloader;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
use Spiral\Tokenizer\Listener\CachedClassesLoader;
use Spiral\Tokenizer\Listener\ClassesLoaderInterface;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Spiral\Tokenizer\TokenizerListenerRegistryInterface;

final class TokenizerListenerBootloaderTest extends BaseTestCase
{
    protected array $classes = [];
    protected bool $finalized = false;

    protected function setUp(): void
    {
        $this->beforeBooting(function (TokenizerListenerBootloader $bootloader): void {
            $bootloader->addListener(
                new class($this->classes, $this->finalized) implements TokenizationListenerInterface {

                    public function __construct(
                        private array &$classes,
                        private bool &$finalized
                    ) {
                    }

                    public function listen(\ReflectionClass $class): void
                    {
                        if ($class->implementsInterface(TestInterface::class)) {
                            $this->classes[] = $class->name;
                        }
                    }

                    public function finalize(): void
                    {
                        $this->finalized = true;
                    }
                }
            );
        });

        parent::setUp();
    }

    public function testDependencies(): void
    {
        $this->assertBootloaderRegistered(TokenizerBootloader::class);
    }

    public function testTokenizerListenerRegistryBinding(): void
    {
        $this->assertContainerBoundAsSingleton(
            TokenizerListenerRegistryInterface::class,
            TokenizerListenerBootloader::class
        );
    }

    public function testCachedClassesLoaderBinding(): void
    {
        $this->assertContainerBoundAsSingleton(
            ClassesLoaderInterface::class,
            CachedClassesLoader::class
        );
    }

    public function testListenerShouldBeListen(): void
    {
        $this->assertCount(2, $this->classes);

        $this->assertContains(\Spiral\App\Tokenizer\A::class, $this->classes);
        $this->assertContains(\Spiral\App\Tokenizer\B::class, $this->classes);
        $this->assertTrue($this->finalized);
    }

    #[Env('TOKENIZER_LOAD_ENUMS', true)]
    #[Env('TOKENIZER_LOAD_INTERFACES', true)]
    public function testListenerShouldBeListenWithEnumsAndInterfaces(): void
    {
        $this->assertCount(4, $this->classes);

        $this->assertContains(\Spiral\App\Tokenizer\A::class, $this->classes);
        $this->assertContains(\Spiral\App\Tokenizer\B::class, $this->classes);
        $this->assertContains(\Spiral\App\Tokenizer\TestEnum::class, $this->classes);
        $this->assertContains(\Spiral\App\Tokenizer\TestInterface::class, $this->classes);
        $this->assertTrue($this->finalized);
    }
}
