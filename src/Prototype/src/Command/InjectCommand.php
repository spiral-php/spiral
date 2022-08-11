<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Prototype\Command;

use Spiral\Prototype\Exception\ClassNotDeclaredException;
use Spiral\Prototype\Injector;
use Spiral\Prototype\NodeExtractor;
use Spiral\Prototype\PrototypeLocator;
use Spiral\Prototype\PrototypeRegistry;
use Symfony\Component\Console\Input\InputOption;

final class InjectCommand extends AbstractCommand
{
    public const NAME        = 'prototype:inject';
    public const DESCRIPTION = 'Inject all prototype dependencies';
    public const OPTIONS     = [
        ['remove', 'r', InputOption::VALUE_NONE, 'Remove PrototypeTrait'],
        ['typedProperties', 't', InputOption::VALUE_NONE, 'Use PHP7.4 Typed Properties'],
        [
            'no-phpdoc',
            '',
            InputOption::VALUE_NONE,
            'Omit PhpDoc for Properties, can be omitted only for typed properties',
        ],
    ];

    /** @var Injector */
    private $injector;

    public function __construct(PrototypeLocator $locator, NodeExtractor $extractor, PrototypeRegistry $registry)
    {
        parent::__construct($locator, $extractor, $registry);
        $this->injector = new Injector();
    }

    /**
     * Perform command.
     *
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function perform(): void
    {
        $prototyped = $this->locator->getTargetClasses();
        if ($prototyped === []) {
            $this->writeln('<comment>No prototyped classes found.</comment>');

            return;
        }

        $targets = [];

        foreach ($prototyped as $class) {
            $proto = $this->getPrototypeProperties($class, $prototyped);
            if (empty($proto)) {
                $modified = $this->modify($class, $proto);
                if ($modified !== null) {
                    $targets[] = $modified;
                }
                continue;
            }

            foreach ($proto as $target) {
                if ($target instanceof \Throwable) {
                    $targets[] = [
                        $class->getName(),
                        $target->getMessage(),
                        "{$target->getFile()}:L{$target->getLine()}",
                    ];
                    continue 2;
                }

                if ($target === null) {
                    continue 2;
                }
            }

            $targets[] = [$class->getName(), $this->mergeNames($proto), $this->mergeTargets($proto)];

            $modified = $this->modify($class, $proto);
            if ($modified !== null) {
                $targets[] = $modified;
            }
        }

        if (!empty($targets)) {
            $grid = $this->table(['Class:', 'Property:', 'Target:']);
            foreach ($targets as $target) {
                $grid->addRow($target);
            }

            $grid->render();
        }
    }

    private function modify(\ReflectionClass $class, array $proto): ?array
    {
        $classDefinition = $this->extractor->extract($class->getFilename(), $proto);
        try {
            $modified = $this->injector->injectDependencies(
                file_get_contents($class->getFileName()),
                $classDefinition,
                $this->option('remove'),
                $this->option('typedProperties'),
                $this->option('no-phpdoc')
            );

            file_put_contents($class->getFileName(), $modified);
            return null;
        } catch (\Throwable $e) {
            return [$class->getName(), $e->getMessage(), "{$e->getFile()}:L{$e->getLine()}"];
        }
    }
}
