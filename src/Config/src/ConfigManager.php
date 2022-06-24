<?php

declare(strict_types=1);

namespace Spiral\Config;

use Spiral\Config\Exception\ConfigDeliveredException;
use Spiral\Config\Exception\PatchException;
use Spiral\Config\Patch\Traits\DotTrait;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exception\ConfiguratorException;

/**
 * Load config files, provides container injection and modifies config data on
 * bootloading.
 */
final class ConfigManager implements ConfiguratorInterface, SingletonInterface
{
    use DotTrait;

    private array $data = [];
    private array $defaults = [];
    private array $instances = [];

    public function __construct(
        private readonly LoaderInterface $loader,
        private readonly bool $strict = true
    ) {
    }

    /**
     * Clone state will reset both data and instance cache.
     */
    public function __clone()
    {
        $this->data = [];
        $this->defaults = [];
        $this->instances = [];
    }

    public function exists(string $config): bool
    {
        return isset($this->defaults[$config]) || isset($this->data[$config]) || $this->loader->has($config);
    }

    public function existsSection(string $config, string $section): bool
    {
        if (!$this->exists($config)) {
            return false;
        }

        $data = $this->defaults[$config] ?? $this->data[$config] ?? $this->loader->load($config);

        return $this->dotExists($data, $section);
    }

    public function setDefaults(string $section, array $data): void
    {
        if (isset($this->defaults[$section])) {
            throw new ConfiguratorException(\sprintf('Unable to set default config `%s` more than once.', $section));
        }

        if (isset($this->data[$section])) {
            throw new ConfigDeliveredException(
                \sprintf('Unable to set default config `%s`, config has been loaded.', $section)
            );
        }

        $this->defaults[$section] = $data;
    }

    public function modify(string $section, PatchInterface $patch): array
    {
        if (isset($this->instances[$section])) {
            if ($this->strict) {
                throw new ConfigDeliveredException(
                    \sprintf('Unable to patch config `%s`, config object has already been delivered.', $section)
                );
            }

            unset($this->instances[$section]);
        }

        $data = $this->getConfig($section);

        try {
            return $this->data[$section] = $patch->patch($data);
        } catch (PatchException $e) {
            throw new PatchException(\sprintf('Unable to modify config `%s`.', $section), $e->getCode(), $e);
        }
    }

    public function getConfig(string $section = null): array
    {
        if (isset($this->data[$section])) {
            return $this->data[$section];
        }

        if (isset($this->defaults[$section])) {
            $data = [];
            if ($this->loader->has($section)) {
                $data = $this->loader->load($section);
            }

            $data = \array_merge($this->defaults[$section], $data);
        } else {
            $data = $this->loader->load($section);
        }

        return $this->data[$section] = $data;
    }

    public function createInjection(\ReflectionClass $class, string $context = null): object
    {
        $config = $class->getConstant('CONFIG');
        if (isset($this->instances[$config])) {
            return $this->instances[$config];
        }

        return $this->instances[$config] = $class->newInstance($this->getConfig($config));
    }
}
