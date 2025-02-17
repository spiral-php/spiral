<?php

declare(strict_types=1);

namespace Spiral\Boot\Bootloader;

use Spiral\Core\Config\Binding;

/**
 * Similar to laravel service provider, however allowed only to define bindings in a simple form so
 * they can be cached.
 *
 * To make class bootable (using method boot() with method injections) declare constant BOOT = true;
 *
 * @psalm-type TStaticBindingValue = class-string|non-empty-string|array{class-string, non-empty-string}
 * @psalm-type TContainerBindingValue = TStaticBindingValue|object|callable|Binding
 * @psalm-type TConstantBinding = array<class-string|non-empty-string, TStaticBindingValue>
 * @psalm-type TFullBinding = array<class-string|non-empty-string, TContainerBindingValue>
 */
interface BootloaderInterface
{
    /**
     * Bindings in string/array form, example:
     *
     * [
     *      'interface' => 'class',
     *      'class' => [self::class, 'createMethod']
     * ]
     *
     * @return TFullBinding
     */
    public function defineBindings(): array;

    /**
     * Singletons in string/array form, example:
     *
     * [
     *      'class' => 'otherClass',
     *      'class' => [self::class, 'createMethod']
     * ]
     *
     * You don't need to bind classes which are declared with SINGLETON constant here, spiral will
     * resolve them as singleton automatically.
     *
     * @return TFullBinding
     */
    public function defineSingletons(): array;
}
