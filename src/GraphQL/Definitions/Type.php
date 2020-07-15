<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use Audentio\LaravelBase\Foundation\AbstractModel;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use Rebing\GraphQL\Support\SelectFields;

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

    public static function methodValue($graphQLType, $method): array
    {
        return [
            'type' => $graphQLType,
            'resolve' => function(AbstractModel $root) use ($method) {
                return $root->$method();
            }
        ];
    }

    public static function attributeIfMethodReturnsTrue($graphQLType, $attribute, $method, $returnIfFalse = null): array
    {
        return [
            'type' => $graphQLType,
            'resolve' => function(AbstractModel $root) use ($attribute, $method, $returnIfFalse) {
                if ($root->$method()) {
                    return $root->$attribute;
                }

                return $returnIfFalse;
            }
        ];
    }

    public static function relationQuery($graphQLType, $queryClass, $relationMethod, $scope = '', \Closure $query = null): array
    {
        return [
            'type' => $graphQLType,
            'args' => $queryClass::getQueryArgs($scope),
            'resolve' => function($root, $args, $context, ResolveInfo $info) use ($queryClass, $relationMethod, $graphQLType, $query) {
                $root = $root->$relationMethod();
                if ($query) {
                    $query($root);
                }

                $getSelectFields = function(int $depth = null) use ($root, $args, $context, $info, $graphQLType) {
                    $ctx = $root ?? null;

                    return new SelectFields($graphQLType, $args, $ctx);
                };

                return $queryClass::getResolve($root, $args, $context, $info, $getSelectFields);
            }
        ];
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