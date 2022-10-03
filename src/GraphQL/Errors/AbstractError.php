<?php

namespace Audentio\LaravelGraphQL\GraphQL\Errors;

use GraphQL\Error\Error;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\ResolveInfo;

class AbstractError extends \Exception
{
    protected $resolveInfo;
    protected $errorType = null;
    protected ?array $extraData;

    public function __construct(string $message, ResolveInfo $info = null, ?array $extraData = null)
    {
        parent::__construct($message);
        $this->resolveInfo = $info;
        $this->extraData = $extraData;
    }

    public function getExtraData(): ?array
    {
        return $this->extraData;
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