<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\UnionTypes\ContentType;

use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Rebing\GraphQL\Support\UnionType as BaseUnionType;

abstract class AbstractContentUnionType extends BaseUnionType
{
    protected $attributes = [
        'name' => '',
        'description' => 'A union type'
    ];

    public function types(): array
    {
        return $this->getContentTypes();
    }

    public function resolveType(AbstractModel $model)
    {
        return \GraphQL::type(ContentTypeUtil::getFriendlyContentTypeName($model->getContentType()));
    }

    public function getContentTypes(): array
    {
        $availableTypes = config('graphql.types');
        $contentTypes = [];

        foreach ($this->_getContentTypes() as $contentType) {
            $contentType = ContentTypeUtil::getFriendlyContentTypeName($contentType);
            if (!isset($availableTypes[$contentType])) {
                continue;
            }

            $contentTypes[] = \GraphQL::type($contentType);
        }

        return $contentTypes;
    }

    protected abstract function _getContentTypes(): array;

    public function __construct()
    {
        $this->attributes['name'] = class_basename($this);
    }
}