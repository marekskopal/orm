<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Where;

use BackedEnum;
use DateTimeInterface;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Select;
use Ramsey\Uuid\UuidInterface;

/**
 * @phpstan-type WhereValues scalar|DateTimeInterface|UuidInterface|BackedEnum|Select<covariant object>|array<scalar|DateTimeInterface|UuidInterface|BackedEnum>|null
 * @phpstan-type WhereList array<string,WhereValues>
 * @phpstan-type WhereParams array{0: string|RawExpression, 1: string, 2: WhereValues}
 * @phpstan-type WhereListParams list<WhereParams>
 * @phpstan-type WhereBuilderCallable callable(WhereBuilder $builder): mixed
 * @phpstan-type Where WhereList|WhereParams|WhereListParams|WhereBuilderCallable
 */
class WhereBuilder
{
    private const array AllowedOperators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    /** @var list<WhereParams|WhereBuilder> */
    private array $where = [];

    /** @var list<WhereBuilder> */
    private array $orWhere = [];

    /** @param Select<covariant object> $select */
    public function __construct(private readonly Select $select,)
    {
    }

    /** @param Where $params */
    public function where(array|callable $params): self
    {
        if (is_callable($params)) {
            /** @phpstan-var WhereBuilderCallable $params */
            $builder = new WhereBuilder($this->select);
            // The callable configures the builder in place; its return value is irrelevant,
            // so closures that do not return anything work too.
            $params($builder);
            $this->where[] = $builder;
            return $this;
        }

        if (count($params) === 0) {
            return $this;
        }

        if (
            count($params) === 3
            && (
                is_string($params[0] ?? null)
                || ($params[0] ?? null) instanceof RawExpression
            )
            && is_string($params[1] ?? null)
            && array_key_exists(2, $params)
        ) {
            /** @phpstan-var WhereParams $params */
            $this->where[] = $params;
            return $this;
        }

        /** @phpstan-var WhereList|WhereListParams $params */
        foreach ($params as $column => $param) {
            if (is_array($param)) {
                /** @phpstan-var WhereParams $param */

                $this->where[] = $param;
                continue;
            }

            /**
             * @phpstan-var string $column
             * @phpstan-var WhereValues $param
             * @phpstan-ignore-next-line varTag.nativeType
             */
            $this->where[] = [$column, '=', $param];
        }

        return $this;
    }

    /** @param Where $params */
    public function orWhere(array|callable $params): self
    {
        $this->orWhere[] = new WhereBuilder($this->select)->where($params);

        return $this;
    }

    public function build(): string
    {
        $parts = [];
        if (count($this->where) > 0) {
            $parts[] = $this->buildWhere($this->where);
        }

        foreach ($this->orWhere as $builder) {
            $part = $builder->build();
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' OR ', $parts);
    }

    /** @return list<string|int|float> */
    public function getParams(): array
    {
        $values = [];
        $this->collectParamsValues($this->where, $values);
        $this->collectParamsValues($this->orWhere, $values);
        return $values;
    }

    /** @param list<WhereParams|WhereBuilder> $where */
    private function buildWhere(array $where): string
    {
        $query = [];

        foreach ($where as $condition) {
            if ($condition instanceof WhereBuilder) {
                $query[] = '(' . $condition->build() . ')';
                continue;
            }

            $column = $this->select->parseColumn($condition[0]);
            $operator = $this->normalizeOperator($condition[1]);

            if ($condition[2] === null) {
                $query[] = $column . ' ' . match ($operator) {
                    '=' => 'IS NULL',
                    '!=', '<>' => 'IS NOT NULL',
                    default => throw new \InvalidArgumentException(
                        sprintf('Operator "%s" cannot be used with a null value; use "=" or "!=".', $operator),
                    ),
                };
                continue;
            }

            if ($operator === 'IN' || $operator === 'NOT IN') {
                if (is_array($condition[2])) {
                    // "IN ()" is a syntax error on MySQL and PostgreSQL. An empty list can
                    // never match, so IN is always false and NOT IN is always true.
                    if ($condition[2] === []) {
                        $query[] = $operator === 'IN' ? '1=0' : '1=1';
                        continue;
                    }

                    $query[] = $column . ' ' . $operator . ' (' . implode(
                        ',',
                        array_map(fn($value): string => '?', $condition[2]),
                    ) . ')';
                    continue;
                }

                if ($condition[2] instanceof Select) {
                    $query[] = $column . ' ' . $operator . ' (' . $condition[2]->getSql() . ')';
                    continue;
                }

                throw new \InvalidArgumentException('IN condition must have array or Select as value');
            }

            if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                $query[] = $column . ' ' . $operator . ' ?';
                continue;
            }

            $query[] = $column . $operator . '?';
        }

        return implode(' AND ', $query);
    }

    private function normalizeOperator(string $operator): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtoupper(trim($operator)));

        if ($normalized === null || !in_array($normalized, self::AllowedOperators, true)) {
            throw new \InvalidArgumentException(sprintf('Operator "%s" is not allowed in where condition', $operator));
        }

        return $normalized;
    }

    /**
     * @param list<WhereParams|WhereBuilder> $params
     * @param list<string|int|float> $values
     * @param-out list<string|int|float> $values
     */
    private function collectParamsValues(array $params, array &$values): void
    {
        foreach ($params as $condition) {
            if ($condition instanceof WhereBuilder) {
                array_push($values, ...$condition->getParams());
                continue;
            }

            // Null is rendered as IS NULL / IS NOT NULL and binds no parameter.
            if ($condition[2] === null) {
                continue;
            }

            $conditionValue = $this->getScalarParamsValues($condition[2]);

            if (is_array($conditionValue)) {
                array_push($values, ...array_values($conditionValue));
                continue;
            }

            $values[] = $conditionValue;
        }
    }

    /**
     * @param scalar|DateTimeInterface|UuidInterface|BackedEnum|Select<covariant object>|array<scalar|DateTimeInterface|UuidInterface|BackedEnum> $conditionValue
     * @return string|int|float|array<string|int|float>
     */
    private function getScalarParamsValues(string|int|float|bool|object|array $conditionValue): string|int|float|array
    {
        if (is_array($conditionValue)) {
            return array_map(
                // @phpstan-ignore-next-line return.type
                fn (string|int|float|bool|object $conditionValueItem): string|int|float => $this->getScalarParamsValues(
                    $conditionValueItem,
                ),
                $conditionValue,
            );
        }

        // PDO binds false as an empty string, which never matches an integer column.
        if (is_bool($conditionValue)) {
            return (int) $conditionValue;
        }

        if ($conditionValue instanceof Select) {
            return $conditionValue->getWhereBuilder()->getParams();
        }

        if ($conditionValue instanceof DateTimeInterface) {
            return $conditionValue->format('Y-m-d H:i:s');
        }

        if ($conditionValue instanceof UuidInterface) {
            return $conditionValue->toString();
        }

        if ($conditionValue instanceof BackedEnum) {
            return $conditionValue->value;
        }

        return $conditionValue;
    }
}
