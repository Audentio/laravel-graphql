<?php

namespace Audentio\LaravelGraphQL\GraphQL\Errors;

use GraphQL\Type\Definition\ResolveInfo;

class NotFoundError extends AbstractError
{
    protected $errorType = 'notFound';

    public function __construct(string $message = null, ResolveInfo $info = null, ?array $extraData = null)
    {
        if (empty($message)) {
            $message = __('global.errors.notFound');
        }

        parent::__construct($message, $info, $extraData);
    }
}