<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Definitions\CursorPaginationType;
use Audentio\LaravelGraphQL\GraphQL\Definitions\PaginationType;
use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
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

    protected static function handleFields(array $queryArgs, array $requestedFields, GraphqlType $parentType,
                                           array &$select, array &$with, $ctx): void
    {
        parent::handleFields($queryArgs, $requestedFields, $parentType, $select, $with, $ctx);
        $parentTable = static::getTableNameFromParentType($parentType);
        $select = [$parentTable ? ($parentTable . '.' . '*') : '*'];

        $thisType = $parentType;
        if ($thisType instanceof ListOfType) {
            $thisType = $thisType->getOfType();
        }

        if ($thisType instanceof CursorPaginationType
            || $thisType instanceof PaginationType) {
            return;
        }

        self::recurseTypeForWith($thisType, '', $requestedFields['fields'], $with);
    }

    protected static function recurseTypeForWith(ObjectType $type, string $objectTree, array $fields, array &$with): void
    {
        foreach ($fields as $key => $field) {
            // Ignore __typename, as it's a special case
            if ('__typename' === $key) {
                continue;
            }

            // If field doesn't exist on definition we don't select it
            try {
                if (method_exists($type, 'getField')) {
                    /** @var FieldDefinition $fieldDefinition */
                    $fieldDefinition = $type->getField($key);
                } else {
                    continue;
                }
            } catch (InvariantViolation $e) {
                continue;
            }

            if (array_key_exists('with', $fieldDefinition->config)) {
                $fieldWith = $fieldDefinition->config['with'];
                if (!is_array($fieldWith)) {
                    $fieldWith = [$fieldWith];
                }

                foreach ($fieldWith as $item) {
                    if (!in_array($item, $with)) {
                        $with[] = $objectTree . $item;
                    }
                }
            }

            $subType = static::getObjectTypeForField($fieldDefinition);
            if ($subType && !empty($field['fields'])) {
                self::recurseTypeForWith($subType, $objectTree . $key . '.', $field['fields'], $with);
            }
        }
    }

    protected static function getObjectTypeForField(FieldDefinition $fieldDefinition): ?ObjectType
    {
        /** @var ObjectType $type */
        $type = $fieldDefinition->getType();
        if (method_exists($type, 'getOfType')) {
            $type = $type->getOfType();
        }

        if (method_exists($type, 'getFields')) {
            return $type;
        }

        return null;
    }
}
