<?php

declare(strict_types=1);

namespace Audentio\LaravelGraphQL\GraphQL\Queries\Debug;

use Audentio\LaravelGraphQL\GraphQL\Debugger\QueriesExecutedDebugger;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use Audentio\LaravelGraphQL\GraphQL\Support\Query;
use Audentio\LaravelGraphQL\GraphQL\Traits\ErrorTrait;
use Audentio\LaravelGraphQL\LaravelGraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\Uuid;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\SelectFields;

class DebugSqlQueriesQuery extends Query
{
    /** @var DebugSqlQueriesQuery */
    protected static $instance;

    protected $attributes = [
        'name' => 'DebugSqlQueriesQuery',
        'description' => '[DEBUG] Query to load details about executed SQL queries.',
    ];

    public static function getQueryType(): GraphQLType
    {
        return \GraphQL::newObjectType([
            'name' => 'DebugSqlQueries',
            'fields' => [
                'id' => ['type' => Type::id()],
                'time' => ['type' => Type::float()],
                'count' => ['type' => Type::int()],
                'queries' => [
                    'name' => 'queries',
                    'type' => Type::listOf(GraphQL::type('DebugSqlQuery'))
                ],
            ],
        ]);
    }

    public static function getQueryArgs($scope = ''): array
    {
        return [];
    }

    /**
     * @param Builder $root
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @param \Closure $getSelectFields
     *
     * @return mixed
     */
    public static function getResolve($root, $args, $context, ResolveInfo $info, \Closure $getSelectFields)
    {
        if (!LaravelGraphQL::isDebugEnabled()) {
            return null;
        }

        /** @var QueriesExecutedDebugger $obj */
        $obj = app(QueriesExecutedDebugger::class);
        if (!$obj) {
            return null;
        }

        return [
            'id' => Uuid::uuid4(),
            'time' => $obj->time(),
            'count' => $obj->count(),
            'queries' => $obj->all(),
        ];
    }

    public function resolve($root, $args, $context, ResolveInfo $info, \Closure $getSelectFields)
    {
        return self::getResolve(null, $args, $context, $info, $getSelectFields);
    }

    public function __construct()
    {
        self::$instance = $this;
    }
}
