<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Support;

use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type as GraphqlType;
use GraphQL\Type\Definition\WrappingType;
use Rebing\GraphQL\Support\SelectFields as SelectFieldsBase;

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

        return function ($query) use ($with, $select, $customQuery, $requestedFields, $ctx): void {
            if ($customQuery) {
                $query = $customQuery($requestedFields['args'], $query, $ctx);
            }

//            $query->select($select);
            $query->with($with);
        };
    }

    protected static function handleFields(array $queryArgs, array $requestedFields, GraphqlType $parentType, array &$select, array &$with, $ctx): void
    {
        parent::handleFields($queryArgs, $requestedFields, $parentType, $select, $with, $ctx);
        $parentTable = static::getTableNameFromParentType($parentType);
        $select = [$parentTable ? ($parentTable . '.' . '*') : '*'];

        foreach ($requestedFields['fields'] as $key => $field) {
            // Ignore __typename, as it's a special case
            if ('__typename' === $key) {
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

            if (array_key_exists('with', $fieldObject->config)) {
                $fieldWith = $fieldObject->config['with'];
                if (!is_array($fieldWith)) {
                    $fieldWith = [$fieldWith];
                }

                foreach ($fieldWith as $item) {
                    if (!in_array($item, $with)) {
                        $with[] = $item;
                    }
                }
            }
        }
    }

}
