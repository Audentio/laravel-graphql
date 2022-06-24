<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelGraphQL\Rebing\GraphQL\GraphQL;
use Illuminate\Console\Command;

class BuildSchemaCacheCommand extends Command
{
    protected $signature = 'graphql:build-schema-cache {schemaName?} {duration?}';

    protected $description = 'Build a fresh version of the persistent schema cache.';

    public function handle(): int
    {
        GraphQL::buildLaravelSchemaCache($this->argument('schemaName'), $this->argument('duration') ?: null);
        return 0;
    }
}