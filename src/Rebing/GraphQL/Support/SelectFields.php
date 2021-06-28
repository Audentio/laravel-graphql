<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Support;

use Closure;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type as GraphqlType;
use Rebing\GraphQL\Support\SelectFields as SelectFieldsBase;

class SelectFields extends SelectFieldsBase
{
    protected static function handleFields(array $queryArgs, array $requestedFields, GraphqlType $parentType, array &$select, array &$with, $ctx): void
    {
        parent::handleFields($queryArgs, $requestedFields, $parentType, $select, $with, $ctx);

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