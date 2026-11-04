<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Pagination\LengthAwarePaginator;
use Rebing\GraphQL\Support\Contracts\WrapType;
use Rebing\GraphQL\Support\Facades\GraphQL;

/**
 * Implements WrapType so SelectFields (rebing/graphql-laravel-select-fields) treats it as a
 * pagination wrapper, as rebing 9 did via its `graphql.pagination_type` config check.
 * CursorPaginationType deliberately does not: rebing 9 never matched it either.
 */
class PaginationType extends ObjectType implements WrapType
{
    public function __construct($typeName, $customName = null)
    {
        $name = $customName ?: $typeName . 'Pagination';

        $config = [
            'name'  => $name,
            'fields' => $this->getPaginationFields($typeName)
        ];

        parent::__construct($config);
    }

    protected function getPaginationFields($typeName): array
    {
        return [
            'data' => [
                'type'          => GraphQLType::nonNull(GraphQLType::listOf(GraphQLType::nonNull(GraphQL::type($typeName)))),
                'description'   => 'List of items on the current page',
                'resolve'       => function(LengthAwarePaginator $data) { return $data->getCollection();  },
            ],
            'total' => [
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'Number of total items selected by the query',
                'resolve'       => function(LengthAwarePaginator $data) { return $data->total(); },
                'selectable'    => false,
            ],
            'perPage' => [
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'Number of items returned per page',
                'resolve'       => function(LengthAwarePaginator $data) { return $data->perPage(); },
                'selectable'    => false,
            ],
            'page' => [
                'type'          => GraphQLType::nonNull(GraphQLType::int()),
                'description'   => 'Current page of the cursor',
                'resolve'       => function(LengthAwarePaginator $data) { return $data->currentPage(); },
                'selectable'    => false,
            ],
            'from' => [
                'type'          => GraphQLType::int(),
                'description'   => 'Number of the first item returned',
                'resolve'       => function(LengthAwarePaginator $data) { return $data->firstItem(); },
                'selectable'    => false,
            ],
            'to' => [
                'type'          => GraphQLType::int(),
                'description'   => 'Number of the last item returned',
                'resolve'       => function(LengthAwarePaginator $data) { return $data->lastItem(); },
                'selectable'    => false,
            ],
        ];
    }
}