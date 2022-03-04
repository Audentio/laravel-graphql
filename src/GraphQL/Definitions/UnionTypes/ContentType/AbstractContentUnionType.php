<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\UnionTypes\ContentType;

use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use GraphQL\GraphQL;
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
            $graphQLType = ContentTypeUtil::getContentTypeField('graphQLType')[$contentType] ?? null;
            if (!empty($graphQLType)) {
                $contentTypes[] = \GraphQL::type($graphQLType);
                continue;
            }

            $contentType = ContentTypeUtil::getFriendlyContentTypeName($contentType);
            if (isset($availableTypes[$contentType])) {
                $contentTypes[] = \GraphQL::type($contentType);
            }
        }

        return $contentTypes;
    }

    protected abstract function _getContentTypes(): array;

    public function __construct()
    {
        $this->attributes['name'] = class_basename($this);
    }
}
