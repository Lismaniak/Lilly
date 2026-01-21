<?php
declare(strict_types=1);

namespace Lilly\Validation;

use InvalidArgumentException;

final class ArrayValidator
{
    /**
     * @param list<mixed> $items
     * @param array<string, array{value: callable|string, rules: list<string|callable>}> $schema
     * @return list<array<string, mixed>>
     */
    public static function mapListWithSchema(array $items, array $schema): array
    {
        return array_map(
            static function (mixed $item) use ($schema): array {
                $data = [];

                foreach ($schema as $field => $definition) {
                    $data[$field] = self::resolveValue($item, $definition['value']);
                }

                $rules = array_map(
                    static fn (array $definition): array => $definition['rules'],
                    $schema
                );

                self::validate($data, $rules);

                return $data;
            },
            $items
        );
    }

    private static function resolveValue(mixed $item, callable|string $value): mixed
    {
        if (is_callable($value)) {
            return $value($item);
        }

        if (is_array($item)) {
            if (!array_key_exists($value, $item)) {
                throw new InvalidArgumentException("Field source '{$value}' not found on array item.");
            }

            return $item[$value];
        }

        if (is_object($item)) {
            if (!property_exists($item, $value)) {
                throw new InvalidArgumentException("Field source '{$value}' not found on object item.");
            }

            return $item->{$value};
        }

        throw new InvalidArgumentException('Unable to resolve schema value from non-array/object item.');
    }

    /**
     * @param list<mixed> $items
     * @param callable $mapper
     * @param array<string, list<string|callable>> $rules
     * @return list<array<string, mixed>>
     */
    public static function mapList(array $items, callable $mapper, array $rules): array
    {
        return array_map(
            static function (mixed $item) use ($mapper, $rules): array {
                $data = $mapper($item);
                if (!is_array($data)) {
                    throw new InvalidArgumentException('Mapped list item must be an array.');
                }

                self::validate($data, $rules);
                return $data;
            },
            $items
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, list<string|callable>> $rules
     */
    public static function validate(array $data, array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $isNullable = in_array('nullable', $fieldRules, true);

            if ($value === null && $isNullable) {
                continue;
            }

            foreach ($fieldRules as $rule) {
                if (is_callable($rule)) {
                    $rule($value, $field, $data);
                    continue;
                }

                if ($rule === 'nullable') {
                    continue;
                }

                if ($rule === 'required' && $value === null) {
                    throw new InvalidArgumentException("Field '{$field}' is required.");
                }

                if ($value === null) {
                    continue;
                }

                if ($rule === 'int' && !is_int($value)) {
                    throw new InvalidArgumentException("Field '{$field}' must be an int.");
                }

                if ($rule === 'string' && !is_string($value)) {
                    throw new InvalidArgumentException("Field '{$field}' must be a string.");
                }

                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        throw new InvalidArgumentException("Field '{$field}' must be at most {$max} characters.");
                    }
                }

                if (is_string($rule) && str_starts_with($rule, 'starts_with:')) {
                    $prefix = substr($rule, 12);
                    if (is_string($value) && $prefix !== '' && !str_starts_with($value, $prefix)) {
                        throw new InvalidArgumentException("Field '{$field}' must start with '{$prefix}'.");
                    }
                }
            }
        }
    }
}
