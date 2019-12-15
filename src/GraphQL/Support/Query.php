<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Traits\ErrorTrait;
use Audentio\LaravelGraphQL\GraphQL\Traits\PaginationTrait;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Query as BaseQuery;
use Rebing\GraphQL\Support\SelectFields;

abstract class Query extends BaseQuery
{
    use ErrorTrait, PaginationTrait;

    public static function getArgs(): array
    {
        throw new \LogicException('Contents of getArgs must be overridden');
    }

    public static function getType(): GraphQLType
    {
        throw new \LogicException('Contents of getType must be overridden');
    }

    public function type(): GraphQLType
    {
        $class = get_called_class();

        return $class::getType();
    }

    public function args(): array
    {
        $class = get_called_class();

        return $class::getArgs();
    }
}