<?php

namespace Audentio\LaravelGraphQL;

use Audentio\LaravelGraphQL\GraphQL\Definitions\Enums\MutationActionEnum;
use Audentio\LaravelGraphQL\GraphQL\Definitions\JsonType;
use Audentio\LaravelGraphQL\GraphQL\Definitions\TimestampType;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use Audentio\LaravelGraphQL\GraphQL\Queries\Debug\DebugSqlQueriesQuery;
use Audentio\LaravelGraphQL\GraphQL\Types\DebugSqlQueryType;
use Audentio\LaravelGraphQL\Utils\GraphQLRequestTagUtil;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use Illuminate\Http\Request;

class LaravelGraphQL
{
    const ERR_PERMISSION = 'permission';
    const ERR_NOT_FOUND = 'notFound';
    const ERR_VALIDAATION = 'validation';
    const ERR_INVALID_PARAMETER = 'invalidParameter';

    const QUERIES_EXECUTED_DEBUG_IDENTIFIER = 'audentioLaravelGraphQLQueriesExecuted';

    protected static bool $debugEnabled = false;

    protected static array $registeredSchema = [
        'queries' => [],
        'mutations' => [],
        'types' => [
            'Timestamp' => TimestampType::class,
            'JsonType' => JsonType::class,
            'MutationActionEnum' => MutationActionEnum::class,
        ],
        'additionalMutationActionEnumValues' => [],
    ];

    public static function getMutationActionEnumValues(): array
    {
        return array_merge(self::$registeredSchema['additionalMutationActionEnumValues'],
            MutationActionEnum::DEFAULT_VALUES);
    }

    public static function getTagsForGraphQLRequest(Request $request): array
    {
        $source = $request->input('query') ?? null;
        if ($source) {
            $sourceObj = new Source($source);
            return GraphQLRequestTagUtil::buildTagsForSource($sourceObj);
        }

        return [];
    }

    public static function getOperationNamesForGraphQLRequest(Request $request): array
    {
        $operationNames = [];
        $tags = self::getTagsForGraphQLRequest($request);

        foreach ($tags as $tag) {
            $tagParts = explode(':', $tag, 3);
            $operationName = isset($tagParts[2]) ? lcfirst($tagParts[2]) : null;
            if (!$operationName || in_array($operationName, $operationNames)) {
                continue;
            }
            
            $operationNames[] = $operationName;
        }

        return $operationNames;
    }

    public static function getDefaultSchema(): array
    {
        $schema = [
            'queries' => self::$registeredSchema['queries'],
            'types' => self::$registeredSchema['types'],
            'mutations' => self::$registeredSchema['mutations'],
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

    public static function registerAdditionalMutationActionEnumValues(array $values): void
    {
        foreach ($values as $value) {
            static::registerAdditionalMutationActionEnumValue($value);
        }
    }

    public static function registerAdditionalMutationActionEnumValue(string $value): void
    {
        if (in_array($value, static::$registeredSchema['additionalMutationActionEnumValues'])) {
            return;
        }

        static::$registeredSchema['additionalMutationActionEnumValues'][] = $value;
    }



    public static function registerTypes(array $types): void
    {
        foreach ($types as $typeName => $class) {
            self::registerType($typeName, $class);
        }
    }

    public static function registerType(string $typeName, string $class): void
    {
        static::$registeredSchema['types'][$typeName] = $class;
    }

    public static function registerQueries(array $queries): void
    {
        foreach ($queries as $queryName => $class) {
            self::registerQuery($queryName, $class);
        }
    }

    public static function registerQuery(string $queryName, string $class): void
    {
        static::$registeredSchema['queries'][$queryName] = $class;
    }

    public static function registerMutations(array $mutations): void
    {
        foreach ($mutations as $mutationName => $class) {
            self::registerMutation($mutationName, $class);
        }
    }

    public static function registerMutation(string $mutationName, string $class): void
    {
        static::$registeredSchema['mutations'][$mutationName] = $class;
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
