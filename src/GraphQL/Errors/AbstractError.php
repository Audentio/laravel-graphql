<?php

namespace Audentio\LaravelGraphQL\GraphQL\Errors;

use GraphQL\Error\Error;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\ResolveInfo;

class AbstractError extends \Exception
{
    protected $resolveInfo;
    protected $errorType = null;

    public function __construct(string $message, ResolveInfo $info = null)
    {
        \Exception::__construct($message);
        $this->resolveInfo = $info;
    }

    public function getResolveInfo()
    {
        return $this->resolveInfo;
    }

    public function getFieldName()
    {
        if ($this->resolveInfo) {
            return $this->resolveInfo->fieldName;
        }

        return null;
    }

    public function getErrorType()
    {
        return $this->errorType;
    }
}