<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelBase\Utils\StrUtil;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use GraphQL\Type\Definition\InputObjectType;
use Illuminate\Database\Eloquent\Builder;
use Audentio\LaravelGraphQL\GraphQL\Errors\InvalidParameterError;

trait FilterableQueryTrait
{
    /**
     * @param Builder $builder
     * @param array   $args
     * @param mixed   ...$extraParams
     *
     * @return mixed
     * @throws \Audentio\LaravelGraphQL\GraphQL\Errors\InvalidParameterError
     */
    public static function applyFilters(Builder $builder, array &$args, ...$extraParams)
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
                    if (!is_array($filterableField['default'])) {
                        $filterableField['default'] = [
                            'value' => $filterableField['default'],
                        ];
                    }
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

    /**
     * @param array $extraParams
     *
     * @return array
     * @throws \Audentio\LaravelGraphQL\GraphQL\Errors\InvalidParameterError
     */
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

            if (isset($filterableField['graphQLType'])) {
                $graphQLType = $filterableField['graphQLType'];
            }

            if (isset($filterableField['type'])) {
                $graphQLType = $filterableField['type'];
            }

            if (!isset($graphQLType)) {
                throw new InvalidParameterError('The ' . $key . ' filter needs to have a type.');
            }

            if (!Type::isInputType($graphQLType)) {
                throw new InvalidParameterError('The ' . $key . ' filter is not an input type.');
            }

            $filterableField = array_merge([
                'graphQLType' => $graphQLType,
                'canFilter' => isset($filterableField['canFilter']) ? $filterableField['canFilter'] : true,
                'hasOperator' => isset($filterableField['hasOperator']) ? $filterableField['hasOperator'] : true,
            ], $filterableField);

            $preparedFields[$filterableField['field']] = $filterableField;
        }

        return $preparedFields;
    }

    /**
     * @param       $scope
     * @param array $args
     *
     * @throws \Audentio\LaravelGraphQL\GraphQL\Errors\InvalidParameterError
     */
    public static function addFilterArgs($scope, array &$args)
    {
        $filterableFields = self::prepareFilters();

        $fieldObjs = [];
        foreach ($filterableFields as $fieldData) {
            $field = $fieldData['field'];
            $graphQLType = $fieldData['graphQLType'];

            $fieldPasc = StrUtil::convertUnderscoresToPascalCase($field);

            if ($fieldData['hasOperator']) {
                $fieldObjs[$field] = [
                    'type' => Type::filterField($scope . $fieldPasc, $graphQLType),
                    'rules' => $fieldData['rules'] ?? [],
                ];
            } else {
                $fieldObjs[$field] = [
                    'type' => $graphQLType,
                    'rules' => $fieldData['rules'] ?? [],
                ];
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
