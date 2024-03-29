<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use BadMethodCallException;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;

use function implode;
use function is_array;
use function is_string;
use function Psl\invariant;
use function sprintf;
use function urlencode;

final class QueryBuilder extends DbalQueryBuilder
{
    /**
     * {@inheritDoc}
     *
     * @param string|string[] $columns
     */
    public function join($table, $columns, $where = '', $extra = null): self
    {
        invariant(! empty($table), '$table should not be empty.');
        invariant(is_array($columns), '$columns must be an array.');

        $query = sprintf(
            '(SELECT %s FROM %s%s%s)',
            implode(', ', $columns),
            $table,
            $where ? ' WHERE ' . $where : '',
            $extra ? ' ' . $extra : '',
        );

        return $this->addSelect($query);
    }

    /** {@inheritDoc} */
    public function setParameter($key, $value, $type = null)
    {
        if (is_string($value)) {
            $value = urlencode($value);
        }

        return parent::setParameter($key, $value, $type);
    }

    /** {@inheritDoc} */
    public function leftJoin($fromAlias, $join, $alias, $condition = null): void
    {
        throw new BadMethodCallException(sprintf('"%s" method call is not allowed, use "join" instead.', __METHOD__));
    }

    /** {@inheritDoc} */
    public function rightJoin($fromAlias, $join, $alias, $condition = null)
    {
        throw new BadMethodCallException(sprintf('"%s" method call is not allowed, use "join" instead.', __METHOD__));
    }

    /** {@inheritDoc} */
    public function innerJoin($fromAlias, $join, $alias, $condition = null)
    {
        throw new BadMethodCallException(sprintf('"%s" method call is not allowed, use "join" instead.', __METHOD__));
    }
}
