<?php

namespace Audentio\LaravelGraphQL\GraphQL\Definitions\Enums\ContentType;

use Audentio\LaravelBase\Utils\ContentTypeUtil;

class ContentTypeEnum extends AbstractContentTypeEnum
{
    protected function _getContentTypes(): array
    {
        return ContentTypeUtil::getContentTypes(true);
    }
}