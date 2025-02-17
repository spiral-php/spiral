<?php

declare(strict_types=1);

namespace Spiral\Console\Configurator\Attribute;

use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\ReaderInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\AsInput;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Spiral\Console\Configurator\CommandDefinition;
use Spiral\Console\Exception\ConfiguratorException;
use Symfony\Component\Console\Attribute\AsCommand as SymfonyAsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
final class Parser
{
    public function __construct(
        private readonly ReaderInterface $reader = new AttributeReader(),
    ) {}

    public function hasCommandAttribute(\ReflectionClass $reflection): bool
    {
        return $this->reader->firstClassMetadata($reflection, AsCommand::class) !== null ||
            $reflection->getAttributes(SymfonyAsCommand::class) !== [];
    }

    public function parse(\ReflectionClass $reflection): CommandDefinition
    {
        $attribute = $this->reader->firstClassMetadata($reflection, AsCommand::class);

        if ($attribute === null) {
            $attribute = $reflection->getAttributes(SymfonyAsCommand::class)[0]->newInstance();
        }

        $parseSourceReflection = $this->getParseSource($reflection);

        return new CommandDefinition(
            name: $attribute->name,
            arguments: $this->parseArguments($parseSourceReflection),
            options: $this->parseOptions($parseSourceReflection),
            description: $attribute->description,
            help: $attribute instanceof AsCommand ? $attribute->help : null,
        );
    }

    public function fillProperties(Command $command, InputInterface $input): void
    {
        $reflection = new \ReflectionClass($command);

        foreach ($reflection->getProperties() as $property) {
            $attribute = $this->reader->firstPropertyMetadata($property, Argument::class);
            if ($attribute === null) {
                continue;
            }

            if ($input->hasArgument($attribute->name ?? $property->getName())) {
                $property->setValue(
                    $command,
                    $this->typecast($input->getArgument($attribute->name ?? $property->getName()), $property),
                );
            }
        }

        foreach ($reflection->getProperties() as $property) {
            $attribute = $this->reader->firstPropertyMetadata($property, Option::class);
            if ($attribute === null) {
                continue;
            }

            if ($input->hasOption($attribute->name ?? $property->getName())) {
                $value = $this->typecast($input->getOption($attribute->name ?? $property->getName()), $property);

                if ($value !== null || $this->getPropertyType($property)->allowsNull()) {
                    $property->setValue($command, $value);
                }
            }
        }
    }

    /**
     * Get the method that should be used to parse the command.
     *
     * This either can be the command itself, of the `#[AsInput]` parameter of the `perform` or `__invoke` method.
     */
    private function getParseSource(\ReflectionClass $reflection): \ReflectionClass
    {
        $method = $reflection->hasMethod('perform')
            ? $reflection->getMethod('perform')
            : ($reflection->hasMethod('__invoke')
                ? $reflection->getMethod('__invoke')
                : null);

        if ($method === null) {
            return $reflection;
        }

        $parameter = null;
        foreach ($method->getParameters() as $param) {
            $attribute = $this->reader->firstParameterMetadata($param, AsInput::class);
            if ($attribute !== null) {
                $parameter = $param;
                break;
            }
        }

        if ($parameter === null) {
            return $reflection;
        }

        return new \ReflectionClass($parameter->getType()->getName());
    }

    private function parseArguments(\ReflectionClass $reflection): array
    {
        $result = [];
        $arrayArgument = null;
        foreach ($reflection->getProperties() as $property) {
            $attribute = $this->reader->firstPropertyMetadata($property, Argument::class);
            if ($attribute === null) {
                continue;
            }

            $type = $this->getPropertyType($property);

            $isOptional = $property->hasDefaultValue() || $type->allowsNull();
            $isArray = $type->getName() === 'array';
            $mode = match (true) {
                $isArray && !$isOptional => InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                $isArray && $isOptional => InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                $isOptional => InputArgument::OPTIONAL,
                default => InputArgument::REQUIRED,
            };

            $argument = new InputArgument(
                name: $attribute->name ?? $property->getName(),
                mode: $mode,
                description: (string) $attribute->description,
                default: $property->hasDefaultValue() ? $property->getDefaultValue() : null,
                suggestedValues: $attribute->suggestedValues,
            );

            if ($arrayArgument !== null && $isArray) {
                throw new ConfiguratorException('There must be only one array argument!');
            }

            // It must be used at the end of the argument list.
            if ($isArray) {
                $arrayArgument = $argument;
                continue;
            }
            $result[] = $argument;
        }

        if ($arrayArgument !== null) {
            $result[] = $arrayArgument;
        }

        return $result;
    }

    private function parseOptions(\ReflectionClass $reflection): array
    {
        $result = [];
        foreach ($reflection->getProperties() as $property) {
            $attribute = $this->reader->firstPropertyMetadata($property, Option::class);
            if ($attribute === null) {
                continue;
            }

            $type = $this->getPropertyType($property);
            $mode = $attribute->mode;

            if ($mode === null) {
                $mode = $this->guessOptionMode($type, $property);
            }

            if ($mode === InputOption::VALUE_NONE || $mode === InputOption::VALUE_NEGATABLE) {
                if ($type->getName() !== 'bool') {
                    throw new ConfiguratorException(
                        'Options properties with mode `VALUE_NONE` or `VALUE_NEGATABLE` must be bool!',
                    );
                }
            }

            $hasDefaultValue = $attribute->mode !== InputOption::VALUE_NONE && $property->hasDefaultValue();

            $result[] = new InputOption(
                name: $attribute->name ?? $property->getName(),
                shortcut: $attribute->shortcut,
                mode: $mode,
                description: (string) $attribute->description,
                default: $hasDefaultValue ? $property->getDefaultValue() : null,
                suggestedValues: $attribute->suggestedValues,
            );
        }

        return $result;
    }

    private function typecast(mixed $value, \ReflectionProperty $property): mixed
    {
        $type = $property->hasType() ? $property->getType() : null;

        if (!$type instanceof \ReflectionNamedType || $value === null) {
            return $value;
        }

        if (!$type->isBuiltin() && \enum_exists($type->getName())) {
            try {
                /** @var class-string<\BackedEnum> $enum */
                $enum = $type->getName();

                return $enum::from($value);
            } catch (\Throwable) {
                throw new ConfiguratorException(\sprintf('Wrong option value. Allowed options: `%s`.', \implode(
                    '`, `',
                    \array_map(static fn(\BackedEnum $item): string => (string) $item->value, $enum::cases()),
                )));
            }
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'float' => (float) $value,
            'array' => (array) $value,
            default => $value,
        };
    }

    private function getPropertyType(\ReflectionProperty $property): \ReflectionNamedType
    {
        if (!$property->hasType()) {
            throw new ConfiguratorException(
                \sprintf('Please, specify the type for the `%s` property!', $property->getName()),
            );
        }

        $type = $property->getType();

        if ($type instanceof \ReflectionIntersectionType) {
            throw new ConfiguratorException(\sprintf('Invalid type for the `%s` property.', $property->getName()));
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $type) {
                if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                    return $type;
                }
            }
        }

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && \enum_exists($type->getName())) {
            return $type;
        }

        if ($type instanceof \ReflectionNamedType && $type->isBuiltin() && $type->getName() !== 'object') {
            return $type;
        }

        throw new ConfiguratorException(\sprintf('Invalid type for the `%s` property.', $property->getName()));
    }

    /**
     * @return int<0, 31>
     */
    private function guessOptionMode(\ReflectionNamedType $type, \ReflectionProperty $property): int
    {
        $isOptional = $type->allowsNull() || $property->hasDefaultValue();

        return match (true) {
            $type->getName() === 'bool' => InputOption::VALUE_NEGATABLE,
            $type->getName() === 'array' && $isOptional => InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            $type->getName() === 'array' && !$isOptional => InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            $type->allowsNull() || $property->hasDefaultValue() => InputOption::VALUE_OPTIONAL,
            default => InputOption::VALUE_REQUIRED,
        };
    }
}
