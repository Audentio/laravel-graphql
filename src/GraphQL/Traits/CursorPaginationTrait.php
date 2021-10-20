<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use GraphQL\Type\Definition\InputObjectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

trait CursorPaginationTrait
{
    public static function addCursorPaginationArgs($scope, array &$args)
    {
        $args['paginate'] = [
            'description' => 'Pagination information',
            'type' => new InputObjectType([
                'name' => 'Paginate' . $scope,
                'fields' => [
                    'cursor' => [
                        'description' => 'The cursor to use to determine the position for this request',
                        'type' => Type::string(),
                    ],
                    'perPage' => [
                        'description' => 'The number of items to request per page',
                        'type' => Type::int(),
                    ],
                ],
            ]),
        ];
    }

    protected static function buildCursorPaginationParams(array $args, $perPage = 20, $maxPerPage = 50): array
    {
        $cursor = null;
        if (!empty($args['paginate']['cursor'])) {
            $cursor = $args['paginate']['cursor'];
        }

        if (!empty($args['paginate']['perPage'])) {
            $perPage = $args['paginate']['perPage'];
        }
        $perPage = min($perPage, $maxPerPage);

        return [
            $cursor,
            $perPage
        ];
    }

    public static function cursorPaginateResults($root, array $args, $perPage=20, $maxPerPage=50): ?CursorPaginator
    {
        list($cursor, $perPage) = self::buildCursorPaginationParams($args, $perPage, $maxPerPage);

        return $root->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}