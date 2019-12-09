<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use GraphQL\Type\Definition\InputObjectType;

class Type extends \GraphQL\Type\Definition\Type
{
    public static function json()
    {
        return JsonType::type();
    }

    public static function timestamp()
    {
        return TimestampType::type();
    }

    public static function sortField($name)
    {
        return new InputObjectType([
            'name' => 'sort' . $name,
            'fields' => [
                'execution_order' => ['type' => Type::int()],
                'direction' => ['type' => \GraphQL::type('SortDirectionEnum')],
            ],
        ]);
    }
    public static function filterField($name, $graphQLType)
    {
        return Type::listOf(new InputObjectType([
            'name' => 'filter' . $name,
            'fields' => [
                'operator' => ['type' => \GraphQL::type('FilterOperatorEnum')],
                'value' => ['type' => $graphQLType],
            ],
        ]));
    }

}