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
}