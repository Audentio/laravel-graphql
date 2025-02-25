<?php

namespace Audentio\LaravelGraphQL\GraphQL\Traits;

use Audentio\LaravelBase\Utils\StrUtil;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use Illuminate\Database\Eloquent\Builder;
use Audentio\LaravelGraphQL\GraphQL\Errors\InvalidParameterError;

trait FilterableQueryTrait
{
    /**
     * @param Builder $builder
     * @param array   $args
     * @param mixed   ...$extraParams
     *
     * @return Builder
     * @throws InvalidParameterError
     */
    public static function applyFilters(Builder $builder, array &$args, ...$extraParams)
    {
        $filterableFields = self::prepareFilters($extraParams);

        $filtersToApply = [];

        foreach ($filterableFields as $field => $filterableField) {
            $filterApplied = false;
            if (isset($args['filter']) && array_key_exists($field, $args['filter'])) {
                $filters = $args['filter'][$field];
                if (!$filterableField['hasOperator']) {
                    $filters = [[
                        'operator' => 'e',
                        'value' => $filters,
                    ]];
                }

                foreach ($filters as $filter) {
                    if (!$filterableField['canFilter']) {
                        continue;
                    }

                    if (isset($filterableField['parseValue'])) {
                        $filter['value'] = $filterableField['parseValue']($filter['value'], $filterableField);
                    }

                    $filter['column'] = $filterableField['column'];
                    $operator = $filter['operator'] ?? '';
                    $filter['operator'] = self::parseOperator($operator);

                    $filterApplied = true;
                    if (isset($filterableField['resolve'])) {
                        $filterableField['resolve']($builder, $filter['operator'], $filter['value']);
                        continue;
                    }

                    $filtersToApply[] = $filter;
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


                    if ($filter['operator'] === '<' || $filter['operator'] === '<=') {
                        $builder->where(function ($query) use ($column, $filter) {
                            $query->where($column, $filter['operator'], $filter['value'])
                                  ->orWhereNull($column);
                        });
                    } else {
                        $builder->where($column, $filter['operator'], $filter['value']);
                    }
                }
            }
        }

        return $builder;
    }

    /**
     * @param array $extraParams
     *
     * @return array
     * @throws InvalidParameterError
     */
    public static function prepareFilters(array $extraParams = []): array
    {
        $filterableFields = call_user_func_array([self::class, 'getFilters'], $extraParams);
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

            $hasOperatorDefaultValue = config('audentioGraphQL.filterDefaultHasOperatorValue', false);

            $filterableField = array_merge([
                'graphQLType' => $graphQLType,
                'canFilter' => $filterableField['canFilter'] ?? true,
                'hasOperator' => $filterableField['hasOperator'] ?? $hasOperatorDefaultValue,
            ], $filterableField);

            $preparedFields[$filterableField['field']] = $filterableField;
        }

        return $preparedFields;
    }

    /**
     * @param       $scope
     * @param array $args
     *
     * @throws InvalidParameterError
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

            if (isset($fieldData['description'])) {
                $fieldObjs[$field]['description'] = $fieldData['description']; // apply description if specified
            }
        }

        if (!empty($fieldObjs)) {
            $args['filter'] = [
                'type' => \GraphQL::newInputObjectType([
                    'name' => 'filter' . $scope,
                    'fields' => $fieldObjs,
                    'description' => 'Filters for ' . $scope,
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
