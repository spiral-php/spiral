<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Core\InvokerInterface;
use Spiral\Core\ResolverInterface;

final class DefaultInvokerStrategy implements InvokerStrategyInterface
{
    public function __construct(
        private readonly InitializerInterface $initializer,
        private readonly InvokerInterface $invoker,
        private readonly ResolverInterface $resolver,
    ) {}

    public function invokeBootloaders(
        array $classes,
        array $bootingCallbacks,
        array $bootedCallbacks,
        bool $useConfig = true,
    ): void {
        /** @psalm-suppress TooManyArguments */
        $bootloaders = \iterator_to_array($this->initializer->init($classes, $useConfig));

        foreach ($bootloaders as $data) {
            foreach ($data['init_methods'] as $methodName) {
                $this->invokeBootloader($data['bootloader'], $methodName, $data['options']);
            }
        }

        $this->fireCallbacks($bootingCallbacks);

        foreach ($bootloaders as $data) {
            foreach ($data['boot_methods'] as $methodName) {
                $this->invokeBootloader($data['bootloader'], $methodName, $data['options']);
            }
        }

        $this->fireCallbacks($bootedCallbacks);
    }

    private function invokeBootloader(BootloaderInterface $bootloader, string $method, array $options): void
    {
        $refl = new \ReflectionClass($bootloader);
        if (!$refl->hasMethod($method)) {
            return;
        }

        $method = $refl->getMethod($method);

        $args = $this->resolver->resolveArguments($method);
        if (!isset($args['boot'])) {
            $args['boot'] = $options;
        }

        $method->invokeArgs($bootloader, \array_values($args));
    }

    /**
     * @param array<\Closure> $callbacks
     */
    private function fireCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            $this->invoker->invoke($callback);
        }
    }
}
