<?php

declare(strict_types=1);

namespace Spiral\Storage\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Exception\EnvironmentException;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container;
use Spiral\Core\Exception\Container\NotFoundException;
use Spiral\Distribution\Bootloader\DistributionBootloader;
use Spiral\Distribution\DistributionInterface as CdnInterface;
use Spiral\Storage\Bucket;
use Spiral\Storage\BucketFactory;
use Spiral\Storage\BucketFactoryInterface;
use Spiral\Storage\BucketInterface;
use Spiral\Storage\Config\StorageConfig;
use Spiral\Storage\Storage;
use Spiral\Storage\StorageInterface;

class StorageBootloader extends Bootloader
{
    /** @var array<string, class-string|callable> */
    protected const SINGLETONS = [
        BucketFactoryInterface::class => BucketFactory::class,
    ];

    public function boot(Container $app, ConfiguratorInterface $config): void
    {
        $config->setDefaults(StorageConfig::CONFIG, [
            'default' => Storage::DEFAULT_STORAGE,
            'servers' => [],
            'buckets' => [],
        ]);

        $app->bindInjector(StorageConfig::class, ConfiguratorInterface::class);

        $app->bindSingleton(StorageInterface::class, static function (
            BucketFactoryInterface $bucketFactory,
            StorageConfig $config,
            Container $app
        ) {
            $manager = new Storage($config->getDefaultBucket());

            $distributions = $config->getDistributions();

            foreach ($config->getAdapters() as $name => $adapter) {
                $resolver = null;

                if (isset($distributions[$name])) {
                    try {
                        $cdn = $app->make(CdnInterface::class);
                    } catch (NotFoundException $e) {
                        $message = 'Unable to create distribution for bucket "%s". '
                            . 'Please make sure that bootloader %s is added in your application';
                        $message = \sprintf($message, $name, DistributionBootloader::class);

                        throw new EnvironmentException($message, $e->getCode(), $e);
                    }

                    $resolver = $cdn->resolver($distributions[$name]);
                }

                $manager->add($name, $bucketFactory->createFromAdapter($adapter, $name, $resolver));
            }

            return $manager;
        });

        $app->bindSingleton(Storage::class, static fn (StorageInterface $manager) => $manager);

        $app->bindSingleton(BucketInterface::class, static fn (StorageInterface $manager) => $manager->bucket());

        $app->bindSingleton(Bucket::class, static fn (BucketInterface $storage) => $storage);
    }
}
