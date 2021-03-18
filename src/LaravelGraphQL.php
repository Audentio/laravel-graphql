<?php

namespace Audentio\LaravelGraphQL;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use Audentio\LaravelGraphQL\GraphQL\Queries\Debug\DebugSqlQueriesQuery;
use Audentio\LaravelGraphQL\GraphQL\Types\DebugSqlQueryType;

class LaravelGraphQL
{
    const ERR_PERMISSION = 'permission';
    const ERR_NOT_FOUND = 'notFound';
    const ERR_VALIDAATION = 'validation';
    const ERR_INVALID_PARAMETER = 'invalidParameter';

    const QUERIES_EXECUTED_DEBUG_IDENTIFIER = 'audentioLaravelGraphQLQueriesExecuted';

    protected static $debugEnabled = false;

    public static function getDefaultSchema(): array
    {
        $schema = [
            'queries' => [],
            'types' => [],
            'mutations' => [],
        ];

        if (self::isDebugEnabled()) {
            $schema['types']['DebugSqlQuery'] = DebugSqlQueryType::class;
            $schema['queries']['debugSqlQueries'] = DebugSqlQueriesQuery::class;
        }

        return $schema;
    }

    public static function isDebugEnabled(): bool
    {
        return config('audentioGraphQL.enableDebug');
    }

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
