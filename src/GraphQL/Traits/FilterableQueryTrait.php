<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelBase\Utils\StrUtil;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use GraphQL\Type\Definition\InputObjectType;

trait FilterableQueryTrait
{
    /**
     * @param Builder $builder
     * @param array $args
     * @param mixed ...$extraParams
     * @return mixed
     */
    public static function applyFilters($builder, array &$args, ...$extraParams)
    {
        $filterableFields = self::prepareFilters($extraParams);

        $filtersToApply = [];

        foreach ($filterableFields as $field => $filterableField) {
            $filterApplied = false;
            if (isset($args['filter']) && array_key_exists($field, $args['filter'])) {
                $filters = $args['filter'][$field];
                if (!is_array($filters)) {
                    $filters = [[
                        'operator' => 'e',
                        'value' => $filters,
                    ]];
                }

                foreach ($filters as $filter) {
                    if (!$filterableField['canFilter']) {
                        continue;
                    }

                    $filter['column'] = $filterableField['column'];
                    $operator = isset($filter['operator']) ? $filter['operator'] : '';
                    $filter['operator'] = self::parseOperator($operator);

                    if (isset($filterableField['resolve'])) {
                        $filterApplied = true;
                        $filterableField['resolve']($builder, $filter['operator'], $filter['value']);
                        continue;
                    }

                    $filterApplied = true;
                    $filtersToApply[] = $filter;
                    continue;
                }
            }

            if (!$filterApplied) {
                if (isset($filterableField['default'])) {
                    $filter = array_merge([
                        'operator' => '=',
                        'value' => '',
                        'column' => $filterableField['column'],
                    ], $filterableField['default']);

                    if (isset($filterableField['resolve'])) {
                        $filterableField['resolve']($builder, $filter['operator'], $filter['value']);
                        continue;
                    }

                    $filtersToApply[] = $filter;
                }
            }
        }

        foreach ($filtersToApply as $filter) {
            $column = $filter['column'];

            if ($filter['operator'] === 'like') {
                $builder->where($column, $filter['operator'], '%' . $filter['value'] . '%');
            } else {
                if (is_array($filter['value'])) {
                    if ($filter['operator'] === '=' || $filter['operator'] === 'like') {
                        $builder->whereIn($column, $filter['value']);
                    } else {
                        $builder->whereNotIn($column, $filter['value']);
                    }
                } else {
                    $builder->where($column, $filter['operator'], $filter['value']);
                }
            }
        }

        return $builder;
    }

    public static function prepareFilters(array $extraParams = []): array
    {
        $filterableFields = call_user_func_array(['self', 'getFilters'], $extraParams);
        $preparedFields = [];

        foreach ($filterableFields as $key => $filterableField) {
            if (is_array($filterableField)) {
                $filterableField['field'] = $key;
                if (!isset($filterableField['column'])) {
                    $filterableField['column'] = $key;
                }
            } else {
                $filterableField = [
                    'field' => $filterableField,
                    'column' => $filterableField,
                ];
            }

            $filterableField = array_merge([
                'graphQLType' => Type::string(),
                'canFilter' => true,
                'hasOperator' => true,
            ], $filterableField);

            $preparedFields[$filterableField['field']] = $filterableField;
        }

        return $preparedFields;
    }

    public static function addFilterArgs($scope, array &$args)
    {
        $filterableFields = self::prepareFilters();

        $fieldObjs = [];
        foreach ($filterableFields as $fieldData) {
            $field = $fieldData['field'];
            $graphQlType = $fieldData['graphQLType'];

            $fieldPasc = StrUtil::convertUnderscoresToPascalCase($field);

            if ($fieldData['hasOperator']) {
                $fieldObjs[$field] = Type::filterField('filter' . $scope . $fieldPasc, $graphQlType);
            } else {
                $fieldObjs[$field] = $graphQlType;
            }
        }

        if (!empty($fieldObjs)) {
            $args['filter'] = [
                'type' => new InputObjectType([
                    'name' => 'filter' . $scope,
                    'fields' => $fieldObjs,
                ]),
            ];
        }
    }

    protected static function parseOperator($operator)
    {
        switch ($operator) {
            case 'gt':
                return '>';
            case 'lt':
                return '<';
            case 'gte':
                return '>=';
            case 'lte':
                return '<=';
            case 'ne':
                return '!=';
            case 'like':
                return 'like';
            case 'e':
            default:
                return '=';
        }
    }
}