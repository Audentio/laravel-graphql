<?php

namespace Audentio\LaravelGraphQL\GraphQL\Errors;

use GraphQL\Error\Error;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\ResolveInfo;

class PermissionError extends AbstractError
{
    protected $errorType = 'permission';

    public function __construct(string $message, ResolveInfo $info = null)
    {
        if (empty($message)) {
            $message = __('global.errors.unauthorized');
        }
        parent::__construct($message, $info);
    }
}