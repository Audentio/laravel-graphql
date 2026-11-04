<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Support;

use Audentio\LaravelGraphQL\GraphQL\Definitions\CursorPaginationType;
use Audentio\LaravelGraphQL\GraphQL\Definitions\PaginationType;
use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphqlType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Rebing\GraphQL\Support\SelectFields as SelectFieldsBase;
use Rebing\GraphQL\Support\SimplePaginationType;

class SelectFields extends SelectFieldsBase
{
    protected static function handleFields(
        array $queryArgs,
        array $requestedFields,
        GraphqlType $parentType,
        array &$select,
        array &$with,
        $ctx,
    ): void {
        $unwrappedType = $parentType instanceof WrappingType
            ? $parentType->getInnermostType()
            : $parentType;

        // Union types don't have fields directly — fields come from member types
        // via inline fragments. Process each requested field against the member types.
        if ($unwrappedType instanceof UnionType && config('audentioGraphQL.unionEagerLoad')) {
            static::handleAudentioUnionFields($queryArgs, $requestedFields, $unwrappedType, $select, $with, $ctx);
            $select = ['*'];
            return;
        }

        $model = isset($unwrappedType->config['model'])
            ? app($unwrappedType->config['model'])
            : null;

        $parentTable = $model?->getTable();

        // Pre-process: remove fields that have a custom 'with' config but don't correspond
        // to an actual Eloquent relation on the model. This prevents the base class from
        // calling e.g. GroupUser::content() which doesn't exist — the real eager load path
        // is specified in the 'with' config (e.g. 'group.content').
        $customWithFields = [];
        $filteredRequestedFields = $requestedFields;

        foreach ($requestedFields['fields'] as $key => $field) {
            if ($key === '__typename' || $field === static::ALWAYS_RELATION_KEY) {
                continue;
            }

            try {
                if (!method_exists($unwrappedType, 'getField')) {
                    continue;
                }
                $fieldObject = $unwrappedType->getField($key);
            } catch (InvariantViolation $e) {
                continue;
            }

            if (!empty($fieldObject->config['with']) && $model && !method_exists($model, $key)) {
                $customWithFields[$key] = $fieldObject;
                unset($filteredRequestedFields['fields'][$key]);
            }
        }

        parent::handleFields($queryArgs, $filteredRequestedFields, $parentType, $select, $with, $ctx);

        // Process 'with' configs from all requested fields
        foreach ($requestedFields['fields'] as $key => $field) {
            if ($key === '__typename') {
                continue;
            }

            try {
                if (!method_exists($unwrappedType, 'getField')) {
                    continue;
                }
                $fieldObject = $unwrappedType->getField($key);
            } catch (InvariantViolation $e) {
                continue;
            }

            if (!empty($fieldObject->config['with'])) {
                $fieldWith = $fieldObject->config['with'];
                if (is_string($fieldWith)) {
                    $fieldWith = [$fieldWith];
                }

                foreach ($fieldWith as $withKey => $withValue) {
                    if (is_int($withKey)) {
                        if (!isset($with[$withValue])) {
                            $with[$withValue] = static function ($query) {
                            };
                        }
                    } else {
                        if (!isset($with[$withKey])) {
                            $with[$withKey] = $withValue;
                        }
                    }
                }
            }

            // Handle always fields for fields we stripped from parent processing
            if (isset($customWithFields[$key])) {
                static::addAlwaysFields($customWithFields[$key], $select, $parentTable);
            }
        }

        $select = ['*'];
    }

    /**
     * select-fields 1.0 resolves fields that only exist on a union member / interface
     * implementor and eager-loads their relations. rebing 9 skipped them, and union eager
     * loading here is opt-in (`audentioGraphQL.unionEagerLoad`, handled by
     * handleAudentioUnionFields), so keep the rebing 9 behaviour.
     */
    protected static function findFieldInConcreteTypes(GraphqlType $interfaceType, string $fieldName): ?FieldDefinition
    {
        return null;
    }

    protected static function findFieldInConcreteUnionTypes(UnionType $unionType, string $fieldName): ?FieldDefinition
    {
        return null;
    }

    /**
     * Named so it doesn't override select-fields' own handleUnionFields(), which has a
     * different signature and is called from the parent's handleFields().
     */
    protected static function handleAudentioUnionFields(
        array $queryArgs,
        array $requestedFields,
        UnionType $unionType,
        array &$select,
        array &$with,
        $ctx,
    ): void {
        $memberTypes = $unionType->getTypes();

        foreach ($requestedFields['fields'] as $key => $field) {
            if ($key === '__typename' || $field === static::ALWAYS_RELATION_KEY) {
                continue;
            }

            if (!is_array($field['fields'] ?? null) || empty($field['fields'])) {
                continue;
            }

            // Find the first member type that has this field
            foreach ($memberTypes as $memberType) {
                try {
                    $fieldObject = $memberType->getField($key);
                } catch (InvariantViolation $e) {
                    continue;
                }

                if (!isset($memberType->config['model'])) {
                    continue;
                }

                $relationsKey = $fieldObject->config['alias'] ?? $key;
                $model = app($memberType->config['model']);

                if (!method_exists($model, $relationsKey)) {
                    // Not a relation — recurse into the field's type to handle nested fields
                    static::handleFields($queryArgs, $field, $fieldObject->config['type'], $select, $with, $ctx);
                    break;
                }

                $customQuery = $fieldObject->config['query'] ?? null;
                $newParentType = $fieldObject->config['type'];

                $with[$relationsKey] = static::getSelectableFieldsAndRelations(
                    $queryArgs,
                    $field,
                    $newParentType,
                    $customQuery,
                    false,
                    $ctx,
                );

                // Process custom 'with' config on the field
                if (!empty($fieldObject->config['with'])) {
                    $fieldWith = $fieldObject->config['with'];
                    if (is_string($fieldWith)) {
                        $fieldWith = [$fieldWith];
                    }

                    foreach ($fieldWith as $withKey => $withValue) {
                        if (is_int($withKey)) {
                            if (!isset($with[$withValue])) {
                                $with[$withValue] = static function ($query) {
                                };
                            }
                        } else {
                            if (!isset($with[$withKey])) {
                                $with[$withKey] = $withValue;
                            }
                        }
                    }
                }

                break;
            }
        }
    }
}