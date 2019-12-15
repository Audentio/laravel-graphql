<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use GraphQL\Type\Definition\InputObjectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

trait PaginationTrait
{
    public static function addPaginationArgs($scope, array &$args)
    {
        $args['paginate'] = [
            'description' => 'Pagination information',
            'type' => new InputObjectType([
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
    }

    protected static function buildPaginationParams(array $args, $perPage = 20, $maxPerPage = 50): array
    {
        $page = 1;
        if (!empty($args['paginate']['page'])) {
            $page = $args['paginate']['page'];
        }
        $page = max(1, $page);

        if (!empty($args['paginate']['perPage'])) {
            $perPage = $args['paginate']['perPage'];
        }
        $perPage = min($perPage, $maxPerPage);

        return [
            $page,
            $perPage
        ];
    }

    public static function paginateResults($root, array $args, $perPage=20, $maxPerPage=50): ?LengthAwarePaginator
    {
        list($page, $perPage) = self::buildPaginationParams($args, $perPage, $maxPerPage);

        return $root->paginate($perPage, ['*'], 'page', $page);
    }
}