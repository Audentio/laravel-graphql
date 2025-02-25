<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelBase\Utils\StrUtil;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use Illuminate\Database\Eloquent\Builder;

trait SortableQueryTrait
{
    /**
     * @param Builder $query
     * @param array $args
     * @param string $table
     *
     * @return Builder
     *
     * Can't use return type hinting here because of how Laravel handles relations it's not always necessarily going
     * to return type Builder, but the phpDoc hinting will allow IDE helper functions to work as the related
     * functions are the same for all query handlers
     */
    public static function applySorts($query, array $args, $table = '')
    {
        if (!isset($args['sort'])) {
            return $query;
        }

        $sort = $args['sort'];
        foreach ($sort as $field => &$fieldOptions) {
            $fieldOptions['column'] = $field;
        }

        usort($sort, function($a, $b) {
            if (!isset($a['execution_order'])) $a['execution_order'] = 10;
            if (!isset($b['execution_order'])) $b['execution_order'] = 10;

            return $a['execution_order'] <=> $b['execution_order'];
        });

        foreach ($sort as $opts) {
            if (!isset($opts['direction'])) {
                $opts['direction'] = 'desc';
            }

            $column = $opts['column'];

            if ($table) {
                $column = $table . '_' . $column;
            }

            $query->orderBy($column, $opts['direction']);
        }

        return $query;
    }

    public static function addSortArgs($scope, array &$args, array $sortableFields): void
    {
        $fieldObjs = [];
        foreach ($sortableFields as $field) {
            $fieldPasc = StrUtil::convertUnderscoresToPascalCase($field);
            $fieldObjs[$field] = Type::sortField('sort' . $scope . $fieldPasc);
        }

        if (!empty($fieldObjs)) {
            $args['sort'] = [
                'type' => \GraphQL::newInputObjectType([
                    'name' => 'sort' . $scope,
                    'fields' => $fieldObjs,
                ]),
            ];
        }
    }
}