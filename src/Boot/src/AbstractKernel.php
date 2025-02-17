<?php

declare(strict_types=1);

namespace Spiral\Boot;

use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\Bootloader\BootloaderRegistry;
use Spiral\Boot\Bootloader\BootloaderRegistryInterface;
use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Boot\BootloadManager\AttributeResolver;
use Spiral\Boot\BootloadManager\AttributeResolverRegistryInterface;
use Spiral\Boot\BootloadManager\StrategyBasedBootloadManager;
use Spiral\Boot\BootloadManager\DefaultInvokerStrategy;
use Spiral\Boot\BootloadManager\Initializer;
use Spiral\Boot\BootloadManager\InitializerInterface;
use Spiral\Boot\BootloadManager\InvokerStrategyInterface;
use Spiral\Boot\Event\Bootstrapped;
use Spiral\Boot\Event\DispatcherFound;
use Spiral\Boot\Event\DispatcherNotFound;
use Spiral\Boot\Event\Serving;
use Spiral\Boot\Exception\BootException;
use Spiral\Core\Container\Autowire;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Exceptions\ExceptionHandler;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\ExceptionRendererInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

/**
 * Core responsible for application initialization, bootloading of all required services,
 * environment and directory management, exception handling.
 */
abstract class AbstractKernel implements KernelInterface
{
    /**
     * Defines list of bootloaders to be used for core initialisation and all system components.
     *
     * @deprecated Use {@see defineSystemBootloaders()} method instead. Will be removed in v4.0
     */
    protected const SYSTEM = [CoreBootloader::class];

    /**
     * List of bootloaders to be called on application initialization (before `serve` method).
     * This constant must be redefined in child application.
     *
     * @deprecated Use {@see defineBootloaders()} method instead. Will be removed in v4.0
     */
    protected const LOAD = [];

    protected FinalizerInterface $finalizer;

    /**
     * @internal
     * @var array<class-string<DispatcherInterface>>
     */
    protected array $dispatchers = [];

    /** @var array<\Closure> */
    private array $runningCallbacks = [];

    /** @var array<\Closure> */
    private array $bootingCallbacks = [];

    /** @var array<\Closure> */
    private array $bootedCallbacks = [];

    /** @var array<\Closure>  */
    private array $bootstrappedCallbacks = [];

    /**
     * @throws \Throwable
     */
    protected function __construct(
        protected readonly Container $container,
        protected readonly ExceptionHandlerInterface $exceptionHandler,
        protected readonly BootloadManagerInterface $bootloader,
        array $directories,
    ) {
        $container->bindSingleton(ExceptionHandlerInterface::class, $exceptionHandler);
        $container->bindSingleton(ExceptionRendererInterface::class, $exceptionHandler);
        $container->bindSingleton(ExceptionReporterInterface::class, $exceptionHandler);
        $container->bindSingleton(ExceptionHandler::class, $exceptionHandler);
        $container->bindSingleton(KernelInterface::class, $this);

        $container->bindSingleton(self::class, $this);
        $container->bindSingleton(static::class, $this);

        $container->bindSingleton(
            DirectoriesInterface::class,
            new Directories($this->mapDirectories($directories)),
        );

        $this->finalizer = new Finalizer();
        $container->bindSingleton(FinalizerInterface::class, $this->finalizer);
    }

    /**
     * Create an application instance.
     *
     * @param class-string<ExceptionHandlerInterface>|ExceptionHandlerInterface $exceptionHandler
     *
     * @throws \Throwable
     */
    final public static function create(
        array $directories,
        bool $handleErrors = true,
        ExceptionHandlerInterface|string|null $exceptionHandler = null,
        Container $container = new Container(),
        BootloadManagerInterface|Autowire|null $bootloadManager = null,
    ): static {
        $exceptionHandler ??= ExceptionHandler::class;

        if (\is_string($exceptionHandler)) {
            $exceptionHandler = $container->make($exceptionHandler);
        }

        if ($handleErrors) {
            $exceptionHandler->register();
        }

        $container->bind(AttributeResolverRegistryInterface::class, AttributeResolver::class);

        if (!$container->has(InitializerInterface::class)) {
            $container->bind(InitializerInterface::class, Initializer::class);
        }

        if (!$container->has(InvokerStrategyInterface::class)) {
            $container->bind(InvokerStrategyInterface::class, DefaultInvokerStrategy::class);
        }

        if ($bootloadManager instanceof Autowire) {
            $bootloadManager = $bootloadManager->resolve($container);
        }
        $bootloadManager ??= $container->make(StrategyBasedBootloadManager::class);
        \assert($bootloadManager instanceof BootloadManagerInterface);
        $container->bind(BootloadManagerInterface::class, $bootloadManager);

        if (!$container->has(BootloaderRegistryInterface::class)) {
            /** @psalm-suppress InvalidArgument */
            $container->bindSingleton(BootloaderRegistryInterface::class, [self::class, 'initBootloaderRegistry']);
        }

        return new static(
            $container,
            $exceptionHandler,
            $bootloadManager,
            $directories,
        );
    }

    /**
     * Run the application with given Environment
     *
     *      $app = App::create([...]);
     *      $app->booting(...);
     *      $app->booted(...);
     *      $app->bootstrapped(...);
     *      $app->run(new Environment([
     *          'APP_ENV' => 'production'
     *      ]));
     */
    public function run(?EnvironmentInterface $environment = null): ?self
    {
        $environment ??= new Environment();
        $this->container->bindSingleton(EnvironmentInterface::class, $environment);

        try {
            // will protect any against env overwrite action
            $this->container->runScope(
                [EnvironmentInterface::class => $environment],
                function (Container $container): void {
                    $registry = $container->get(BootloaderRegistryInterface::class);

                    /** @psalm-suppress TooManyArguments */
                    $this->bootloader->bootload($registry->getSystemBootloaders(), [], [], false);
                    $this->fireCallbacks($this->runningCallbacks);

                    $this->bootload($registry->getBootloaders());
                    $this->bootstrap();

                    $this->fireCallbacks($this->bootstrappedCallbacks);
                },
            );
        } catch (\Throwable $e) {
            $this->exceptionHandler->handleGlobalException($e);

            return null;
        }

        $this->getEventDispatcher()?->dispatch(new Bootstrapped($this));

        return $this;
    }

    /**
     * Register a new callback, that will be fired before framework run.
     * (After SYSTEM bootloaders, before bootloaders in LOAD section)
     *
     * $kernel->running(static function(KernelInterface $kernel) {
     *     $kernel->getContainer()->...
     * });
     */
    public function running(\Closure ...$callbacks): void
    {
        foreach ($callbacks as $callback) {
            $this->runningCallbacks[] = $callback;
        }
    }

    /**
     * Register a new callback, that will be fired before framework bootloaders boot.
     * (Before all framework bootloaders in LOAD section will be booted)
     *
     * $kernel->booting(static function(KernelInterface $kernel) {
     *     $kernel->getContainer()->...
     * });
     */
    public function booting(\Closure ...$callbacks): void
    {
        foreach ($callbacks as $callback) {
            $this->bootingCallbacks[] = $callback;
        }
    }

    /**
     * Register a new callback, that will be fired after framework bootloaders booted.
     * (After booting all framework bootloaders in LOAD section)
     *
     * $kernel->booted(static function(KernelInterface $kernel) {
     *     $kernel->getContainer()->...
     * });
     */
    public function booted(\Closure ...$callbacks): void
    {
        foreach ($callbacks as $callback) {
            $this->bootedCallbacks[] = $callback;
        }
    }

    /**
     * Register a new callback, that will be fired after framework bootstrapped.
     * (Before serving)
     *
     * $kernel->bootstrapped(static function(KernelInterface $kernel) {
     *     $kernel->getContainer()->...
     * });
     */
    public function bootstrapped(\Closure ...$callbacks): void
    {
        foreach ($callbacks as $callback) {
            $this->bootstrappedCallbacks[] = $callback;
        }
    }

    /**
     * Add new dispatcher. This method must only be called before method `serve`
     * will be invoked.
     *
     * @param class-string<DispatcherInterface>|DispatcherInterface $dispatcher The class name or instance
     * of the dispatcher. Since v4.0, it will only accept the class name.
     */
    public function addDispatcher(string|DispatcherInterface $dispatcher): self
    {
        if (\is_object($dispatcher)) {
            $dispatcher = $dispatcher::class;
        }

        $this->dispatchers[] = $dispatcher;

        return $this;
    }

    /**
     * Start application and serve user requests using selected dispatcher or throw
     * an exception.
     *
     * @throws BootException
     * @throws \Throwable
     */
    public function serve(): mixed
    {
        $eventDispatcher = $this->getEventDispatcher();
        $eventDispatcher?->dispatch(new Serving());

        $serving = $servingScope = null;
        foreach ($this->dispatchers as $dispatcher) {
            $reflection = new \ReflectionClass($dispatcher);

            $scope = ($reflection->getAttributes(DispatcherScope::class)[0] ?? null)?->newInstance()->scope;
            $this->container->getBinder($scope)->bind($dispatcher, $dispatcher);

            if ($serving === null && $this->canServe($reflection)) {
                $serving = $dispatcher;
                $servingScope = $scope;
            }
        }

        if ($serving === null) {
            $eventDispatcher?->dispatch(new DispatcherNotFound());
            throw new BootException('Unable to locate active dispatcher.');
        }

        return $this->container->runScope(
            new Scope(name: $servingScope, bindings: [DispatcherInterface::class => $serving]),
            static function (DispatcherInterface $dispatcher) use ($eventDispatcher): mixed {
                $eventDispatcher?->dispatch(new DispatcherFound($dispatcher));
                return $dispatcher->serve();
            },
        );
    }

    /**
     * Terminate the application.
     */
    public function __destruct()
    {
        $this->finalizer->finalize(true);
    }

    /**
     * Bootstrap application. Must be executed before serve method.
     */
    abstract protected function bootstrap(): void;

    /**
     * Normalizes directory list and adds all required aliases.
     */
    abstract protected function mapDirectories(array $directories): array;

    /**
     * Get list of defined system bootloaders
     *
     * @return array<int, class-string>|array<class-string, array<non-empty-string, mixed>>
     */
    protected function defineSystemBootloaders(): array
    {
        return static::SYSTEM;
    }

    /**
     * Get list of defined kernel bootloaders
     *
     * @return array<int, class-string>|array<class-string, array<non-empty-string, mixed>>
     */
    protected function defineBootloaders(): array
    {
        return static::LOAD;
    }

    /**
     * Call the registered booting callbacks.
     */
    protected function fireCallbacks(array &$callbacks): void
    {
        if ($callbacks === []) {
            return;
        }

        do {
            $this->container->invoke(\current($callbacks));
        } while (\next($callbacks));

        $callbacks = [];
    }

    /**
     * Bootload all registered classes using BootloadManager.
     */
    private function bootload(array $bootloaders = []): void
    {
        $self = $this;
        $this->bootloader->bootload(
            $bootloaders,
            [
                static function () use ($self): void {
                    $self->fireCallbacks($self->bootingCallbacks);
                },
            ],
        );

        $this->fireCallbacks($this->bootedCallbacks);
    }

    private function getEventDispatcher(): ?EventDispatcherInterface
    {
        return $this->container->has(EventDispatcherInterface::class)
            ? $this->container->get(EventDispatcherInterface::class)
            : null;
    }

    private function initBootloaderRegistry(): BootloaderRegistryInterface
    {
        return new BootloaderRegistry($this->defineSystemBootloaders(), $this->defineBootloaders());
    }

    /**
     * @throws BootException
     */
    private function canServe(\ReflectionClass $reflection): bool
    {
        if (!$reflection->hasMethod('canServe')) {
            throw new BootException('Dispatcher must implement static `canServe` method.');
        }

        return $this->container->invoke([$reflection->getName(), 'canServe']);
    }
}
