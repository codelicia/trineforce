<?php

declare(strict_types=1);

namespace Codelicia\Soql;

use Assert\Assertion;
use BadMethodCallException;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use function implode;
use function sprintf;

final class QueryBuilder extends DbalQueryBuilder
{
    /** {@inheritDoc} */
    public function join($table, $columns, $where = '', $extra = null) : self
    {
        Assertion::notEmpty($table);
        Assertion::isArray($columns);

        $query = sprintf(
            '(SELECT %s FROM %s%s%s)',
            implode(', ', $columns),
            $table,
            $where ? ' WHERE ' . $where : '',
            $extra ? ' ' . $extra : ''
        );

        return $this->addSelect($query);
    }

    /** {@inheritDoc} */
    public function leftJoin($fromAlias, $join, $alias, $condition = null) : void
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
