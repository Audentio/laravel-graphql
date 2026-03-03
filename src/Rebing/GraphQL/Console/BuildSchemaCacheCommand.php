<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelGraphQL\Rebing\GraphQL\GraphQL;
use Illuminate\Console\Command;
use Rebing\GraphQL\GraphQL as BaseGraphQL;

class BuildSchemaCacheCommand extends Command
{
    protected $signature = 'graphql:build-schema-cache {schema? : The schema name to cache (default: default)}';

    protected $description = 'Build and persist a file-based GraphQL schema cache for faster worker startup';

    public function handle(): int
    {
        /** @var GraphQL $graphql */
        $graphql = app(BaseGraphQL::class);

        $schemaName = $this->argument('schema') ?? $this->laravel['config']->get('graphql.default_schema', 'default');

        $this->info("Building GraphQL schema cache for schema: {$schemaName}");

        $path = $graphql->buildAndStoreSchemaCache($schemaName);

        $this->info("Schema cache written to: {$path}");

        return self::SUCCESS;
    }
}
