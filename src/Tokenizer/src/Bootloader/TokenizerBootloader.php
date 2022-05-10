<?php

declare(strict_types=1);

namespace Spiral\Tokenizer\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Tokenizer\ClassesInterface;
use Spiral\Tokenizer\ClassLocator;
use Spiral\Tokenizer\Config\TokenizerConfig;
use Spiral\Tokenizer\InvocationLocator;
use Spiral\Tokenizer\InvocationsInterface;
use Spiral\Tokenizer\ScopedClassesInterface;
use Spiral\Tokenizer\ScopedClassLocator;
use Spiral\Tokenizer\Tokenizer;

final class TokenizerBootloader extends Bootloader implements SingletonInterface
{
    protected const BINDINGS = [
        ScopedClassesInterface::class => ScopedClassLocator::class,
        ClassesInterface::class => ClassLocator::class,
        InvocationsInterface::class => InvocationLocator::class,
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config
    ) {
    }

    public function init(Container $container, DirectoriesInterface $dirs): void
    {
        $container->bindInjector(ClassLocator::class, Tokenizer::class);
        $container->bindInjector(InvocationLocator::class, Tokenizer::class);

        $this->config->setDefaults(
            TokenizerConfig::CONFIG,
            [
                'directories' => [$dirs->get('app')],
                'exclude'     => [
                    $dirs->get('resources'),
                    $dirs->get('config'),
                    'tests',
                    'migrations',
                ],
            ]
        );
    }

    /**
     * Add directory for indexation.
     */
    public function addDirectory(string $directory): void
    {
        $this->config->modify(TokenizerConfig::CONFIG, new Append('directories', null, $directory));
    }
}
