<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelGraphQL\Rebing\GraphQL\GraphQL;
use Illuminate\Console\Command;
use Rebing\GraphQL\GraphQL as BaseGraphQL;

class ClearSchemaCacheCommand extends Command
{
    protected $signature = 'graphql:clear-schema-cache {schema? : The schema name to clear (default: default)}';

    protected $description = 'Clear the persisted GraphQL schema cache';

    public function handle(): int
    {
        /** @var GraphQL $graphql */
        $graphql = app(BaseGraphQL::class);

        $schemaName = $this->argument('schema') ?? $this->laravel['config']->get('graphql.default_schema', 'default');

        $this->info("Clearing GraphQL schema cache for schema: {$schemaName}");

        $graphql->clearSchemaCache($schemaName);

        $this->info('Schema cache cleared.');

        return self::SUCCESS;
    }
}
