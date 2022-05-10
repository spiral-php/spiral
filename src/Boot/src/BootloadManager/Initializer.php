<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\Bootloader\DependedInterface;
use Spiral\Boot\Exception\ClassNotFoundException;
use Spiral\Core\Container;

/**
 * @internal
 */
final class Initializer implements Container\SingletonInterface
{
    public function __construct(
        private readonly Container $container,
        private ClassesRegistry $bootloaders = new ClassesRegistry()
    ) {
    }

    /**
     * Instantiate bootloader objects and resolve dependencies
     *
     * @param array<class-string>|array<class-string, array<string,mixed>> $classes
     */
    public function init(array $classes): \Generator
    {
        foreach ($classes as $class => $options) {
            // default bootload syntax as simple array
            if (\is_string($options)) {
                $class = $options;
                $options = [];
            }

            // Replace class aliases with source classes
            try {
                $class = (new \ReflectionClass($class))->getName();
            } catch (\ReflectionException) {
                throw new ClassNotFoundException(
                    \sprintf('Bootloader class `%s` is not exist.', $class)
                );
            }

            if ($this->bootloaders->isBooted($class)) {
                continue;
            }

            $this->bootloaders->register($class);
            $bootloader = $this->container->get($class);

            if (!$this->isBootloader($bootloader)) {
                continue;
            }

            yield from $this->initBootloader($bootloader);
            yield $class => \compact('bootloader', 'options');
        }
    }

    public function getRegistry(): ClassesRegistry
    {
        return $this->bootloaders;
    }

    /**
     * Resolve all bootloader dependencies and init bindings
     */
    private function initBootloader(BootloaderInterface $bootloader): iterable
    {
        if ($bootloader instanceof DependedInterface) {
            yield from $this->init($this->getDependencies($bootloader));
        }

        $this->initBindings(
            $bootloader->defineBindings(),
            $bootloader->defineSingletons()
        );
    }

    /**
     * Bind declared bindings.
     */
    private function initBindings(array $bindings, array $singletons): void
    {
        foreach ($bindings as $aliases => $resolver) {
            $this->container->bind($aliases, $resolver);
        }

        foreach ($singletons as $aliases => $resolver) {
            $this->container->bindSingleton($aliases, $resolver);
        }
    }

    private function getDependencies(DependedInterface $bootloader): array
    {
        $deps = $bootloader->defineDependencies();

        $reflectionClass = new \ReflectionClass($bootloader);

        $methodsDeps = [];

        foreach (Methods::cases() as $method) {
            if ($reflectionClass->hasMethod($method->value)) {
                $methodsDeps[] = $this->findBootloaderClassesInMethod(
                    $reflectionClass->getMethod($method->value)
                );
            }
        }

        return \array_values(\array_unique(\array_merge($deps, ...$methodsDeps)));
    }

    private function findBootloaderClassesInMethod(\ReflectionMethod $method): array
    {
        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($this->shouldBeBooted($type)) {
                $args[] = $type->getName();
            }
        }

        return $args;
    }

    private function shouldBeBooted(?\ReflectionType $type): bool
    {
        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        return $this->isBootloader($type->getName()) && !$this->bootloaders->isBooted($type->getName());
    }

    /**
     * @psalm-param class-string|object $class
     */
    private function isBootloader(string|object $class): bool
    {
        if (\is_object($class)) {
            return $class instanceof BootloaderInterface;
        }

        return \class_exists($class) && \is_subclass_of($class, BootloaderInterface::class);
    }
}
