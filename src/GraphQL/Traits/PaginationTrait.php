<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use GraphQL\Type\Definition\InputObjectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

trait PaginationTrait
{
    public static function addPaginationArgs($scope, array &$args, ?string $parentObject = null): void
    {
        $paginate = [
            'description' => 'Pagination information',
            'type' => \GraphQL::newInputObjectType([
                'name' => 'Paginate' . $scope,
                'fields' => [
                    'page' => [
                        'description' => 'The page to request',
                        'type' => Type::int(),
                    ],
                    'perPage' => [
                        'description' => 'The number of items to request per page',
                        'type' => Type::int(),
                    ],
                ],
            ]),
        ];

        if ($parentObject) {
            $config = [
                'name' => 'Paginate' . $scope . ucfirst($parentObject),
                'fields' => [],
            ];
            if (array_key_exists($parentObject, $args)) {
                $config = $args[$parentObject]->config;
            }

            $config['fields']['paginate'] = $paginate;
            $args[$parentObject] = \GraphQL::newInputObjectType($config);
        } else {
            $args['paginate'] = $paginate;
        }
    }

    protected static function buildPaginationParams(array $args, $perPage = 20, $maxPerPage = 50, ?string $parentObject = null): array
    {
        $argObject = $args;
        if ($parentObject) {
            $argObject = $args[$parentObject];
        }
        $page = 1;
        if (!empty($argObject['paginate']['page'])) {
            $page = $argObject['paginate']['page'];
        }
        $page = max(1, $page);

        if (!empty($argObject['paginate']['perPage'])) {
            $perPage = $argObject['paginate']['perPage'];
        }
        $perPage = min($perPage, $maxPerPage);

        return [
            $page,
            $perPage
        ];
    }

    public static function paginateResults($root, array $args, $perPage=20, $maxPerPage=50, ?string $parentObject = null): ?LengthAwarePaginator
    {
        list($page, $perPage) = self::buildPaginationParams($args, $perPage, $maxPerPage, $parentObject);

        return $root->paginate($perPage, ['*'], 'page', $page);
    }
}