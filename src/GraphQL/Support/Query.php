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

    public static function getQueryArgs(): array
    {
        throw new \LogicException('Contents of getArgs must be overridden');
    }

    public static function getQueryType(): GraphQLType
    {
        throw new \LogicException('Contents of getType must be overridden');
    }

    public function type(): GraphQLType
    {
        $class = get_called_class();

        return $class::getQueryType();
    }

    public function args(): array
    {
        $class = get_called_class();

        $baseScope = '';
        if (config('audentioGraphQL.enableBaseScopeGeneration')) {
            $baseScope = ucfirst($this->attributes['name']);
        }

        return $class::getQueryArgs($baseScope);
    }
}