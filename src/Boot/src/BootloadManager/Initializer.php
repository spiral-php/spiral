<?php

declare(strict_types=1);

namespace Spiral\Boot\BootloadManager;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Spiral\Boot\Attribute\BootloadConfig;
use Spiral\Boot\Attribute\BootMethod;
use Spiral\Boot\Attribute\InitMethod;
use Spiral\Boot\Bootloader\BootloaderInterface;
use Spiral\Boot\Bootloader\DependedInterface;
use Spiral\Boot\BootloadManager\Checker\BootloaderChecker;
use Spiral\Boot\BootloadManager\Checker\BootloaderCheckerInterface;
use Spiral\Boot\BootloadManager\Checker\CanBootedChecker;
use Spiral\Boot\BootloadManager\Checker\CheckerRegistry;
use Spiral\Boot\BootloadManager\Checker\ClassExistsChecker;
use Spiral\Boot\BootloadManager\Checker\ConfigChecker;
use Spiral\Boot\BootloadManagerInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\BinderInterface;
use Spiral\Core\ResolverInterface;

/**
 * @internal
 * @psalm-import-type TClass from BootloadManagerInterface
 * @psalm-import-type TFullBinding from BootloaderInterface
 */
#[Singleton]
class Initializer implements InitializerInterface
{
    protected ?BootloaderCheckerInterface $checker = null;

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly BinderInterface $binder,
        protected readonly ClassesRegistry $bootloaders = new ClassesRegistry(),
        ?BootloaderCheckerInterface $checker = null,
    ) {}

    /**
     * Instantiate bootloader objects and resolve dependencies
     *
     * @param TClass[]|array<class-string<BootloaderInterface>, array<string,mixed>> $classes
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function init(array $classes, bool $useConfig = true): \Generator
    {
        $this->checker ??= $this->initDefaultChecker();

        foreach ($classes as $bootloader => $options) {
            // default bootload syntax as simple array
            if (\is_string($options) || $options instanceof BootloaderInterface) {
                $bootloader = $options;
                $options = [];
            }
            $options = $useConfig ? $this->getBootloadConfig($bootloader, $options) : [];

            if (!$this->checker->canInitialize($bootloader, $useConfig ? $options : null)) {
                continue;
            }

            $this->bootloaders->register($bootloader instanceof BootloaderInterface ? $bootloader::class : $bootloader);

            if (!$bootloader instanceof BootloaderInterface) {
                $bootloader = $this->container->get($bootloader);
            }

            $initMethods = $this->findMethodsWithPriority(
                $bootloader,
                [0 => [Methods::INIT->value]],
                InitMethod::class,
            );

            $bootMethods = $this->findMethodsWithPriority(
                $bootloader,
                [0 => [Methods::BOOT->value]],
                BootMethod::class,
            );

            yield from $this->resolveDependencies($bootloader, \array_unique([...$initMethods, ...$bootMethods]));

            $this->initBootloader($bootloader);
            yield $bootloader::class => [
                'bootloader' => $bootloader,
                'options' => $options instanceof BootloadConfig ? $options->args : $options,
                'init_methods' => $initMethods,
                'boot_methods' => $bootMethods,
            ];
        }
    }

    public function getRegistry(): ClassesRegistry
    {
        return $this->bootloaders;
    }

    protected function shouldBeBooted(\ReflectionNamedType $type): bool
    {
        /** @var TClass $class */
        $class = $type->getName();

        return $this->isBootloader($class)
            && !$this->bootloaders->isBooted($class);
    }

    /**
     * @psalm-pure
     * @psalm-assert-if-true TClass $class
     */
    protected function isBootloader(string|object $class): bool
    {
        return \is_subclass_of($class, BootloaderInterface::class);
    }

    protected function initDefaultChecker(): BootloaderCheckerInterface
    {
        $registry = new CheckerRegistry();
        $registry->register($this->container->get(ConfigChecker::class));
        $registry->register(new ClassExistsChecker());
        $registry->register(new CanBootedChecker($this->bootloaders));

        return new BootloaderChecker($registry);
    }

    /**
     * Resolve all bootloader dependencies and init bindings
     */
    private function initBootloader(BootloaderInterface $bootloader): void
    {
        foreach ($bootloader->defineBindings() as $alias => $resolver) {
            $this->binder->bind($alias, $resolver);
        }

        foreach ($bootloader->defineSingletons() as $alias => $resolver) {
            $this->binder->bindSingleton($alias, $resolver);
        }

        $this->resolveAttributeBindings($bootloader);
    }

    /**
     * Returns merged config. Attribute config has lower priority.
     *
     * @param class-string<BootloaderInterface>|BootloaderInterface $bootloader
     * @throws \ReflectionException
     */
    private function getBootloadConfig(
        string|BootloaderInterface $bootloader,
        array|callable|BootloadConfig $config,
    ): BootloadConfig {
        if ($config instanceof \Closure) {
            $config = $this->container instanceof ResolverInterface
                ? $config(...$this->container->resolveArguments(new \ReflectionFunction($config)))
                : $config();
        }

        $attr = $this->getBootloadConfigAttribute($bootloader);

        $getArgument = static fn(string $key, bool $override, mixed $default = []): mixed => match (true) {
            $config instanceof BootloadConfig && $override => $config->{$key},
            $config instanceof BootloadConfig && !$override && \is_array($default) =>
                $config->{$key} + ($attr->{$key} ?? []),
            $config instanceof BootloadConfig && !$override && \is_bool($default) => $config->{$key},
            \is_array($config) && $config !== [] && $key === 'args' => $config,
            default => $attr->{$key} ?? $default,
        };

        $override = $config instanceof BootloadConfig ? $config->override : true;

        return new BootloadConfig(
            args: $getArgument('args', $override),
            enabled: $getArgument('enabled', $override, true),
            allowEnv: $getArgument('allowEnv', $override),
            denyEnv: $getArgument('denyEnv', $override),
        );
    }

    /**
     * This method is used to find and instantiate BootloadConfig attribute.
     *
     * @param class-string<BootloaderInterface>|BootloaderInterface $bootloader
     * @throws \ReflectionException
     */
    private function getBootloadConfigAttribute(string|BootloaderInterface $bootloader): ?BootloadConfig
    {
        $attribute = null;
        if ($bootloader instanceof BootloaderInterface || \class_exists($bootloader)) {
            $ref = new \ReflectionClass($bootloader);
            $attribute = $ref->getAttributes(BootloadConfig::class)[0] ?? null;
        }

        if ($attribute === null) {
            return null;
        }

        return $attribute->newInstance();
    }

    /**
     * This method is used to find methods with InitMethod or BootMethod attributes.
     *
     * @param class-string<InitMethod|BootMethod> $attribute
     * @param list<non-empty-string[]> $initialMethods
     * @return list<non-empty-string>
     */
    private function findMethodsWithPriority(
        BootloaderInterface $bootloader,
        array $initialMethods,
        string $attribute,
    ): array {
        $methods = $initialMethods;

        $refl = new \ReflectionClass($bootloader);
        foreach ($refl->getMethods() as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $attrs = $method->getAttributes($attribute);
            if (\count($attrs) === 0) {
                continue;
            }
            /** @var InitMethod|BootMethod $attr */
            $attr = $attrs[0]->newInstance();
            $methods[$attr->priority][] = $method->getName();
        }

        \ksort($methods);

        return \array_merge(...$methods);
    }

    /**
     * This method is used to resolve bindings from attributes.
     *
     * @throws \ReflectionException
     */
    private function resolveAttributeBindings(BootloaderInterface $bootloader): void
    {
        if (!$this->container->has(AttributeResolver::class)) {
            return;
        }

        /** @var AttributeResolver $attributeResolver */
        $attributeResolver = $this->container->get(AttributeResolver::class);

        $availableAttributes = $attributeResolver->getResolvers();

        $refl = new \ReflectionClass($bootloader);
        foreach ($refl->getMethods() as $method) {
            if ($method->isStatic()) {
                continue;
            }

            foreach ($availableAttributes as $attributeClass) {
                $attrs = $method->getAttributes($attributeClass);
                foreach ($attrs as $attr) {
                    $instance = $attr->newInstance();
                    $attributeResolver->resolve($instance, $bootloader, $method);
                }
            }
        }
    }

    /**
     * @param non-empty-string[] $bootloaderMethods
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    private function resolveDependencies(BootloaderInterface $bootloader, array $bootloaderMethods): iterable
    {
        $deps = $this->findDependenciesInMethods($bootloader, $bootloaderMethods);
        if ($bootloader instanceof DependedInterface) {
            $deps = [...$deps, ...$bootloader->defineDependencies()];
        }

        yield from $this->init(\array_values(\array_unique($deps)));
    }

    /**
     * @param non-empty-string[] $methods
     * @return class-string[]
     */
    private function findDependenciesInMethods(BootloaderInterface $bootloader, array $methods): array
    {
        $reflectionClass = new \ReflectionClass($bootloader);

        $methodsDeps = [];

        foreach ($methods as $method) {
            if ($reflectionClass->hasMethod($method)) {
                $methodsDeps[] = $this->findBootloaderClassesInMethod(
                    $reflectionClass->getMethod($method),
                );
            }
        }

        return \array_merge(...$methodsDeps);
    }

    /**
     * @return class-string[]
     */
    private function findBootloaderClassesInMethod(\ReflectionMethod $method): array
    {
        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && $this->shouldBeBooted($type)) {
                $args[] = $type->getName();
            }
        }

        return $args;
    }
}
