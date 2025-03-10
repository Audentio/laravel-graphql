<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\SelectFields;

class Type extends GraphQLType
{
    public static function json()
    {
        return JsonType::type();
    }

    public static function timestamp()
    {
        return TimestampType::type();
    }

    public static function contentField($type = 'Content'): array
    {
        return [
            'type' => \GraphQL::type($type),
            'description' => 'Associated content',
        ];
    }

    public static function paginate(string $typeName, string $customName = null): GraphQLType
    {
        return GraphQL::paginate($typeName, $customName);
    }

    public static function cursorPaginate(string $typeName, string $customName = null): GraphQLType
    {
        $name = $customName ?: $typeName . 'CursorPagination';

        $paginationType = config('graphql.cursor_pagination_type', CursorPaginationType::class);
        return GraphQL::wrapType($typeName, $name, $paginationType);
    }

    public static function contentTypeField($name, $type = 'ContentTypeEnum', array $fieldExtra = []): array
    {
        return array_merge([
            'type' => \GraphQL::type($type),
            'description' => 'The type of associated content',
            'resolve' => function($root, $args, $fields, $info) use ($name) {
                $attribute = $name . '_type';
                $contentType = $root->{$attribute};

                return ContentTypeUtil::getFriendlyContentTypeName($contentType) ?: null;
            }
        ], $fieldExtra);
    }

    public static function methodValue($graphQLType, $method, array $fieldExtra = []): array
    {
        return array_merge([
            'type' => $graphQLType,
            'selectable' => false,
            'resolve' => function(AbstractModel $root) use ($method) {
                return $root->$method();
            }
        ], $fieldExtra);
    }

    public static function attributeIfMethodReturnsTrue($graphQLType, $attribute, $method, $returnIfFalse = null): array
    {
        return [
            'type' => $graphQLType,
            'selectable' => false,
            'resolve' => function(AbstractModel $root) use ($attribute, $method, $returnIfFalse) {
                if ($root->$method()) {
                    return $root->$attribute;
                }

                return $returnIfFalse;
            }
        ];
    }

    /**
     * @deprecated 1.1.8 No longer used by internal code and not recommended.
     */
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
        return \GraphQL::newInputObjectType([
            'name' => 'sort' . $name,
            'description' => '',
            'fields' => [
                'execution_order' => ['type' => Type::int()],
                'direction' => ['type' => \GraphQL::type('SortDirectionEnum')],
            ],
        ]);
    }
    
    public static function filterField($name, $graphQLType)
    {
        return Type::listOf(\GraphQL::newInputObjectType([
            'name' => 'filter' . $name,
            'description' => '',
            'fields' => [
                'operator' => ['type' => \GraphQL::type('FilterOperatorEnum')],
                'value' => ['type' => $graphQLType],
            ],
        ]));
    }

    public function toString(): string
    {
        throw new \LogicException('Type cannot be converted to string');
    }
}
