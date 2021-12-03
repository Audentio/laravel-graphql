<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Rebing\GraphQL\Support\Facades\GraphQL;

class CursorPaginationType extends ObjectType
{
    private string $typeName;

    public function __construct($typeName, $customName = null)
    {
        $name = $customName ?: $typeName . 'CursorPagination';
        $this->typeName = $name;

        $config = [
            'name'  => $name,
            'fields' => $this->getCursorPaginationFields($typeName)
        ];

        $underlyingType = GraphQL::type($typeName);

        if (isset($underlyingType->config['model'])) {
            $config['model'] = $underlyingType->config['model'];
        }

        parent::__construct($config);
    }

    protected function getCursorPaginationFields($typeName)
    {
        return [
            'data' => [
                'type' => GraphQLType::listOf(GraphQL::type($typeName)),
                'description' => 'List of items on the current page',
                'resolve' => function(CursorPaginator $data) { return $data->getCollection();  },
            ],
            'cursor' => [
                'description' => 'Details about the cursor',
                'selectable' => false,
                'type' => new ObjectType([
                    'name' => $this->typeName . 'Cursor',
                    'fields' => [
                        'perPage' => [
                            'type' => GraphQLType::nonNull(GraphQLType::int()),
                            'description' => 'Number of items returned per page',
                            'selectable' => false,
                        ],
                        'previous' => [
                            'type' => GraphQLType::string(),
                            'description' => 'Previous page of the cursor',
                            'selectable' => false,
                        ],
                        'current' => [
                            'type' => GraphQLType::string(),
                            'description' => 'Current page of the cursor',
                            'selectable' => false,
                        ],
                        'next' => [
                            'type' => GraphQLType::string(),
                            'description' => 'Next page of the cursor',
                            'selectable' => false,
                        ],
                    ],
                ]),
                'resolve' => function (CursorPaginator $data) {
                    $return = [
                        'perPage' => $data->perPage(),
                        'previous' => $data->previousCursor(),
                        'current' => $data->cursor(),
                        'next' => $data->nextCursor()
                    ];

                    foreach ($return as $key => $value) {
                        if ($value instanceof Cursor) {
                            $return[$key] = $value->encode();
                        }
                    }

                    return $return;
                }
            ],
        ];
    }
}