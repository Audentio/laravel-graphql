<?php

namespace Audentio\LaravelGraphQL;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;

class LaravelGraphQL
{
    const ERR_PERMISSION = 'permission';
    const ERR_NOT_FOUND = 'notFound';
    const ERR_VALIDAATION = 'validation';
    const ERR_INVALID_PARAMETER = 'invalidParameter';

    public static function addContentTypeMorphFields(array &$fields, $name = 'content', bool $withRelation = true,
                                                     bool $withTypeAndId = true, $graphQLContentType = 'Content',
                                                     string $graphQLContentTypeEnum = 'ContentTypeEnum'): void
    {
        if ($withRelation) {
            $fields[$name] = Type::contentField($graphQLContentType);
        }

        if ($withTypeAndId) {
            $fields[$name . '_type'] = Type::contentTypeField($name, $graphQLContentTypeEnum);
            $fields[$name . '_id'] = [
                'type' => Type::id(),
                'description' => 'The associated content\'s ID',
            ];
        }
    }
}
