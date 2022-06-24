<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelGraphQL\Rebing\GraphQL\GraphQL;
use Illuminate\Console\Command;

class ClearSchemaCacheCommand extends Command
{
    protected $signature = 'graphql:clear-schema-cache {schemaName?}';

    protected $description = 'Clear the existing schema cache.';

    public function handle(): int
    {
        GraphQL::clearLaravelSchemaCache($this->argument('schemaName'));
        return 0;
    }
}