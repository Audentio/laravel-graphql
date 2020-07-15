<?php

namespace Audentio\LaravelGraphQL\GraphQL\Enums\Filter;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
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