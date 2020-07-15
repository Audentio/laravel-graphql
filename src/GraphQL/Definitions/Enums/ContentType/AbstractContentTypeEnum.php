<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\Enums\ContentType;

use Audentio\LaravelGraphQL\GraphQL\Definitions\ContentTypeEnumType;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Audentio\LaravelGraphQL\GraphQL\Support\Enum;
use GraphQL\Type\Definition\Type as GraphQLType;

abstract class AbstractContentTypeEnum extends Enum
{
    protected $enumObject = true;

    protected $attributes = [
        'name' => 'AbstractContentTypeEnum',
        'description' => 'An enum type',
        'values' => [],
    ];

    public function toType(): GraphqlType
    {
        return new ContentTypeEnumType($this->toArray());
    }

    public function getContentTypes(): array
    {
        $contentTypes = [];
        foreach ($this->_getContentTypes() as $contentType) {
            $contentTypes[] = ContentTypeUtil::getFriendlyContentTypeName($contentType);
        }

        return $contentTypes;
    }

    protected abstract function _getContentTypes(): array;

    public function __construct()
    {
        $className = get_class($this);
        $classParts = explode('\\', $className);
        $this->attributes['name'] = end($classParts);
        $this->attributes['values'] = $this->getContentTypes();
    }
}