<?php

declare(strict_types=1);

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\Enums;

use Audentio\LaravelGraphQL\GraphQL\Support\Enum;
use Audentio\LaravelGraphQL\LaravelGraphQL;

class MutationActionEnum extends Enum
{
    protected $enumObject = true;

    const DEFAULT_VALUES = [
        'create',
        'update',
        'delete',
    ];

    protected $attributes = [
        'name' => 'MutationActionEnum',
        'values' => [],
    ];

    public function __construct()
    {
        $this->attributes['values'] = LaravelGraphQL::getMutationActionEnumValues();
    }
}
