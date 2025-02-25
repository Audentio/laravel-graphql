<?php

namespace Audentio\LaravelGraphQL\GraphQL\Errors;

use GraphQL\Type\Definition\ResolveInfo;

class InvalidParameterError extends AbstractError
{
    public function __construct(string $message, ResolveInfo $info = null, ?array $extraData = null)
    {
        if (empty($message)) {
            $message = __('global.errors.invalidParameter');
        }

        parent::__construct($message, $info, $extraData);
    }
}