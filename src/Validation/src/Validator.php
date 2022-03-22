<?php

declare(strict_types=1);

namespace Spiral\Validation;

final class Validator extends AbstractValidator
{
    public function __construct(
        private array|\ArrayAccess|null $data,
        array $rules,
        mixed $context,
        RulesInterface $ruleProvider
    ) {
        parent::__construct($rules, $context, $ruleProvider);
    }

    /**
     * Destruct the service.
     */
    public function __destruct()
    {
        $this->data = null;
        parent::__destruct();
    }

    /**
     * @param \ArrayAccess|array $data
     */
    public function withData(iterable $data): ValidatorInterface
    {
        $validator = clone $this;
        $validator->data = $data;

        return $validator;
    }

    public function getValue(string $field, mixed $default = null): mixed
    {
        $value = $this->data[$field] ?? $default;

        if (\is_object($value) && \method_exists($value, 'getValue')) {
            return $value->getValue();
        }

        return $value;
    }

    public function hasValue(string $field): bool
    {
        if (\is_array($this->data)) {
            return \array_key_exists($field, $this->data);
        }

        return isset($this->data[$field]);
    }
}
