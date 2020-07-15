<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\UnionTypes\ContentType;

use Audentio\LaravelBase\Utils\ContentTypeUtil;

class ContentUnionType extends AbstractContentUnionType
{
    protected function _getContentTypes(): array
    {
        return ContentTypeUtil::getContentTypes();
    }
}