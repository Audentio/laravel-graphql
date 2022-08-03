<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Support;

use App\Models\UserGroup;
use Audentio\LaravelBase\Foundation\AbstractPivot;
use Audentio\LaravelGraphQL\GraphQL\Definitions\CursorPaginationType;
use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphqlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use Illuminate\Support\Facades\Config;
use Rebing\GraphQL\Support\SelectFields as SelectFieldsBase;
use Rebing\GraphQL\Support\SimplePaginationType;

class SelectFields extends SelectFieldsBase
{
    public static function getSelectableFieldsAndRelations(array $queryArgs, array $requestedFields, GraphqlType $parentType, ?Closure $customQuery = null, bool $topLevel = true, $ctx = null)
    {
        $select = [];
        $with = [];

        if ($parentType instanceof WrappingType) {
            $parentType = $parentType->getWrappedType(true);
        }
        $parentTable = static::getTableNameFromParentType($parentType);
        $primaryKey = static::getPrimaryKeyFromParentType($parentType);

        static::handleFields($queryArgs, $requestedFields, $parentType, $select, $with, $ctx);

        // If a primary key is given, but not in the selects, add it
        if (null !== $primaryKey) {
            $primaryKey = $parentTable ? ($parentTable . '.' . $primaryKey) : $primaryKey;
            $alternateKey = $parentTable ? ($parentTable . '.' . '*') : '*';

            if (!in_array($primaryKey, $select) && !in_array($alternateKey, $select)) {
                $select[] = $primaryKey;
            }
        }

        if ($topLevel) {
            return [$select, $with];
        }

        return function ($query) use ($with, $select, $customQuery, $requestedFields, $parentType, $ctx): void {
            if ($customQuery) {
                $query = $customQuery($requestedFields['args'], $query, $ctx) ?? $query;
            }

            foreach ($requestedFields['fields'] as $key => $field) {
                if (!is_array($field)) {
                    continue;
                }

                static::recurseFieldForWith($key, $field, $parentType, $with);
            }

//            $query->select($select);
            $query->with($with);
        };
    }

    protected static function recurseFieldForWith(string $key, array $fieldData, GraphqlType $parentType, array &$with): void
    {
        if ($key === '__typename') {
            return;
        }

        // Temporary fix for union types
        if (!method_exists($parentType, 'getField')) {
            if($parentType instanceof UnionType) {
                foreach ($parentType->getTypes() as $unionType) {
                    try {
                        self::recurseFieldForWith($key, $fieldData, $unionType, $with);
                    } catch (InvariantViolation $e) {
                        // Ignore invalid field errors for subtype
                    }
                }
            }
            return;
        }
        /** @var FieldDefinition $field */
        $field = $parentType->getField($key);

        $fieldConfig = $field->config ?? [];
        if (!empty($fieldConfig['with'])) {
            if (!is_array($fieldConfig['with'])) {
                $fieldConfig['with'] = [$fieldConfig['with']];
            }

            foreach ($fieldConfig['with'] as $item) {
                if (!in_array($item, $with)) {
                    $with[] = $item;
                }
            }
        }
    }

    protected static function handleFields(
        array $queryArgs,
        array $requestedFields,
        GraphqlType $parentType,
        array &$select,
        array &$with,
        $ctx
    ): void {
        $parentTable = static::isMongodbInstance($parentType) ? null : static::getTableNameFromParentType($parentType);

        foreach ($requestedFields['fields'] as $key => $field) {
            // Ignore __typename, as it's a special case
            if ('__typename' === $key) {
                continue;
            }

            // Always select foreign key
            if ($field === static::ALWAYS_RELATION_KEY) {
                static::addFieldToSelect($key, $select, $parentTable, false);

                continue;
            }

            // If field doesn't exist on definition we don't select it
            try {
                if (method_exists($parentType, 'getField')) {
                    $fieldObject = $parentType->getField($key);
                } else {
                    continue;
                }
            } catch (InvariantViolation $e) {
                continue;
            }

            $parentTypeUnwrapped = $parentType;

            if ($parentTypeUnwrapped instanceof WrappingType) {
                $parentTypeUnwrapped = $parentTypeUnwrapped->getWrappedType(true);
            }

            // First check if the field is even accessible
            $canSelect = static::validateField($fieldObject, $queryArgs, $ctx);

            if (true === $canSelect) {
                // Add a query, if it exists
                $customQuery = $fieldObject->config['query'] ?? null;

                // Check if the field is a relation that needs to be requested from the DB
                $queryable = static::isQueryable($fieldObject->config);

                // Pagination
                if (is_a($parentType, Config::get('graphql.pagination_type', \Rebing\GraphQL\Support\PaginationType::class)) ||
                    is_a($parentType, Config::get('graphql.simple_pagination_type', SimplePaginationType::class)) ||
                    is_a($parentType, CursorPaginationType::class)
                ) {
                    /* @var GraphqlType $fieldType */
                    $fieldType = $fieldObject->config['type'];
                    static::handleFields(
                        $queryArgs,
                        $field,
                        $fieldType->getWrappedType(),
                        $select,
                        $with,
                        $ctx
                    );
                }
                // With
                elseif (\is_array($field['fields']) && !empty($field['fields']) && $queryable) {
                    if (isset($parentType->config['model'])) {
                        // Get the next parent type, so that 'with' queries could be made
                        // Both keys for the relation are required (e.g 'id' <-> 'user_id')
                        $relationsKey = $fieldObject->config['alias'] ?? $key;
                        $relation = call_user_func([app($parentType->config['model']), $relationsKey]);

                        static::handleRelation($select, $relation, $parentTable, $field);

                        // New parent type, which is the relation
                        $newParentType = $parentType->getField($key)->config['type'];

                        static::addAlwaysFields($fieldObject, $field, $parentTable, true);

                        $with[$relationsKey] = static::getSelectableFieldsAndRelations(
                            $queryArgs,
                            $field,
                            $newParentType,
                            $customQuery,
                            false,
                            $ctx
                        );
                    } elseif (is_a($parentTypeUnwrapped, \GraphQL\Type\Definition\InterfaceType::class)) {
                        static::handleInterfaceFields(
                            $queryArgs,
                            $field,
                            $parentTypeUnwrapped,
                            $select,
                            $with,
                            $ctx,
                            $fieldObject,
                            $key,
                            $customQuery
                        );
                    } else {
                        static::handleFields($queryArgs, $field, $fieldObject->config['type'], $select, $with, $ctx);
                    }
                }
                // Select
                else {
                    $key = $fieldObject->config['alias']
                        ?? $key;
                    $key = $key instanceof Closure ? $key() : $key;

                    static::addFieldToSelect($key, $select, $parentTable, false);

                    static::addAlwaysFields($fieldObject, $select, $parentTable);
                }
            }
            // If privacy does not allow the field, return it as null
            elseif (null === $canSelect) {
                $fieldObject->resolveFn = function (): void {
                };
            }
            // If allowed field, but not selectable
            elseif (false === $canSelect) {
                static::recurseFieldForWith($key, $field, $parentType, $with);
                static::addAlwaysFields($fieldObject, $select, $parentTable);
            }
        }

        // If parent type is an union or interface we select all fields
        // because we don't know which other fields are required
        if (is_a($parentType, UnionType::class) || is_a($parentType, \GraphQL\Type\Definition\InterfaceType::class)) {
            $select = ['*'];
        }
    }
}
