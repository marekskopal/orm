<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query\Where;

use BackedEnum;
use DateTimeInterface;
use MarekSkopal\ORM\Query\Select;
use Ramsey\Uuid\UuidInterface;

/**
 * @phpstan-type WhereValues scalar|DateTimeInterface|UuidInterface|BackedEnum|Select<covariant object>|array<scalar|DateTimeInterface|UuidInterface|BackedEnum>
 * @phpstan-type WhereList array<string,WhereValues>
 * @phpstan-type WhereParams array{0: string, 1: string, 2: WhereValues}
 * @phpstan-type WhereListParams list<WhereParams>
 * @phpstan-type WhereBuilderCallable callable(WhereBuilder $builder):WhereBuilder
 * @phpstan-type Where WhereList|WhereParams|WhereListParams|WhereBuilderCallable
 */
class WhereBuilder
{
    /** @var list<WhereParams|WhereBuilder> */
    private array $where = [];

    /** @var list<WhereBuilder> */
    private array $orWhere = [];

    /** @param Select<object> $select */
    public function __construct(private readonly Select $select,)
    {
    }

    /** @param Where $params */
    public function where(array|callable $params): self
    {
        if (is_callable($params)) {
            /** @var WhereBuilderCallable $params */
            $this->where[] = $params(new WhereBuilder($this->select));
            return $this;
        }

        if (count($params) === 0) {
            return $this;
        }

        if (
            count($params) === 3
            && is_string($params[0] ?? null)
            && is_string($params[1] ?? null)
            && !is_null($params[2] ?? null)
        ) {
            /** @var WhereParams $params */
            $this->where[] = $params;
            return $this;
        }

        /** @var WhereList|WhereListParams $params */
        foreach ($params as $column => $param) {
            if (is_array($param)) {
                /** @var WhereParams $param */

                $this->where[] = $param;
                continue;
            }

            /**
             * @var string $column
             * @var WhereValues $param
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
        $where = '';
        if (count($this->where) > 0) {
            $where = $this->buildWhere($this->where);
        }

        if (count($this->orWhere) > 0) {
            $where = $where !== '' ? $where : '1';

            $where .= ' OR ' . implode(' OR ', array_map(fn (WhereBuilder $builder): string => $builder->build(), $this->orWhere));
        }

        return $where;
    }

    /** @return list<scalar> */
    public function getParams(): array
    {
        return array_merge(
            $this->getParamsValues($this->where),
            $this->getParamsValues($this->orWhere),
        );
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

            if (strtolower($condition[1]) === 'in') {
                if (is_array($condition[2])) {
                    $query[] = $column . ' ' . $condition[1] . ' (' . implode(
                        ',',
                        array_map(fn($value): string => '?', $condition[2]),
                    ) . ')';
                    continue;
                }

                if ($condition[2] instanceof Select) {
                    $query[] = $column . ' ' . $condition[1] . ' (' . $condition[2]->getSql() . ')';
                    continue;
                }

                throw new \InvalidArgumentException('IN condition must have array or Select as value');
            }

            $query[] = $column . $condition[1] . '?';
        }

        return implode(' AND ', $query);
    }

    /**
     * @param list<WhereParams|WhereBuilder> $params
     * @return list<scalar>
     */
    private function getParamsValues(array $params): array
    {
        $values = [];

        foreach ($params as $condition) {
            if ($condition instanceof WhereBuilder) {
                $values = array_merge($values, $condition->getParams());
                continue;
            }

            $conditionValue = $this->getScalarParamsValues($condition[2]);

            if (is_array($conditionValue)) {
                $values = array_merge($values, array_values($conditionValue));
                continue;
            }

            $values[] = $conditionValue;
        }

        return $values;
    }

    /**
     * @param BackedEnum $conditionValue
     * @return scalar|array<scalar>
     */
    private function getScalarParamsValues(string|int|float|bool|object|array $conditionValue): string|int|float|bool|array
    {
        if (is_array($conditionValue)) {
            return array_map(
                fn (string|int|float|bool|object $conditionValueItem): string|int|float|bool => $this->getScalarParamsValues(
                    $conditionValueItem,
                ),
                $conditionValue,
            );
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
