<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Query;

use BackedEnum;
use DateTimeInterface;

/**
 * @phpstan-type WhereValues scalar|DateTimeInterface|BackedEnum|Select<object>|array<scalar|DateTimeInterface|BackedEnum>
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

    /** @param Where $params */
    public function where(array|callable $params): self
    {
        if (is_callable($params)) {
            /** @var WhereBuilderCallable $params */
            $this->where[] = $params(new WhereBuilder());
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
        $this->orWhere[] = new WhereBuilder()->where($params);

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

    /** @return list<WhereValues> */
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

            if (strtolower($condition[1]) === 'in' && is_array($condition[2])) {
                $query[] = $condition[0] . ' ' . $condition[1] . ' (' . implode(
                    ',',
                    array_map(fn ($value): string => '?', $condition[2]),
                ) . ')';
                continue;
            }

            $query[] = $condition[0] . $condition[1] . '?';
        }

        return implode(' AND ', $query);
    }

    /**
     * @param list<WhereParams|WhereBuilder> $params
     * @return list<WhereValues>
     */
    private function getParamsValues(array $params): array
    {
        $values = [];

        foreach ($params as $condition) {
            if ($condition instanceof WhereBuilder) {
                $values = array_merge($values, $condition->getParams());
                continue;
            }

            $conditionValue = $condition[2];

            if (is_array($conditionValue)) {
                $values = array_merge($values, array_values($conditionValue));
                continue;
            }

            if ($conditionValue instanceof Select) {
                $values = array_merge($values, $conditionValue->getWhereBuilder()->getParams());
                continue;
            }

            if ($conditionValue instanceof DateTimeInterface) {
                $values[] = $conditionValue->format('Y-m-d H:i:s');
                continue;
            }

            $values[] = $conditionValue;
        }

        return $values;
    }
}
