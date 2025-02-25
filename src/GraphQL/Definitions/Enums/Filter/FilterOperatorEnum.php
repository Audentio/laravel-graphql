<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\Enums\Filter;

use Audentio\LaravelGraphQL\GraphQL\Support\Enum;

class FilterOperatorEnum extends Enum
{
    protected $enumObject = true;

    protected $attributes = [
        'name' => 'FilterOperatorEnum',
        'description' => 'An enum type',
        'values' => [
            'e', 'ne', 'gt', 'lt', 'gte', 'lte', 'like'
        ],
    ];
}