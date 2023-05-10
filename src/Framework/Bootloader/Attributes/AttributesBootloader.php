<?php

declare(strict_types=1);

namespace Spiral\Bootloader\Attributes;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Doctrine\Common\Annotations\Reader as DoctrineReaderInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\Exception\InitializationException;
use Spiral\Attributes\Internal\Instantiator\Facade;
use Spiral\Attributes\Internal\Instantiator\InstantiatorInterface;
use Spiral\Attributes\Internal\Instantiator\NamedArgumentsInstantiator;
use Spiral\Attributes\Psr16CachedReader;
use Spiral\Attributes\ReaderInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;

class AttributesBootloader extends Bootloader
{
    protected const SINGLETONS = [
        ReaderInterface::class => [self::class, 'initReader'],
        InstantiatorInterface::class => [self::class, 'initInstantiator'],
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {
    }

    public function init(): void
    {
        $this->config->setDefaults(
            AttributesConfig::CONFIG,
            [
                'annotations' => [
                    'support' => true,
                ],
            ],
        );
    }

    private function initInstantiator(AttributesConfig $config): InstantiatorInterface
    {
        if ($config->isAnnotationsReaderEnabled()) {
            return new Facade();
        }

        /** @psalm-suppress InternalClass */
        return new NamedArgumentsInstantiator();
    }

    private function initReader(
        ContainerInterface $container,
        InstantiatorInterface $instantiator,
        AttributesConfig $config,
    ): ReaderInterface {
        $reader = new AttributeReader($instantiator);

        if ($container->has(CacheInterface::class)) {
            $cache = $container->get(CacheInterface::class);
            \assert($cache instanceof CacheInterface);

            $reader = new Psr16CachedReader($reader, $cache);
        }

        if ($config->isAnnotationsReaderEnabled()) {
            if (!\interface_exists(DoctrineReaderInterface::class)) {
                throw new InitializationException(
                    'Doctrine annotations reader is not available, please install "doctrine/annotations" package',
                );
            }

            $reader = new SelectiveReader([
                $reader,
                new AnnotationReader(new DoctrineAnnotationReader()),
            ]);
        }

        return $reader;
    }
}
