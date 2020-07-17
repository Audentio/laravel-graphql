<?php

namespace Audentio\LaravelGraphQL\Illuminate\Foundation\Console;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ModelMakeCommand extends \Audentio\LaravelBase\Illuminate\Foundation\Console\ModelMakeCommand
{
    public function handle()
    {
        $return = parent::handle();

        if ($return === false) {
            return false;
        }

        if ($this->option('graphql')) {
            $this->createGraphQLSchema();
        }
    }

    protected function createGraphQLSchema(): void
    {
        $modelName = Str::studly(class_basename($this->argument('name')));
        $modelsName = Str::pluralStudly(class_basename($this->argument('name')));

        $this->call('make:graphql:query', [
            'name' => $modelName . '/' . $modelName,
        ]);

        $this->call('make:graphql:query', [
            'name' => $modelName . '/' . $modelsName,
        ]);

        $this->call('make:graphql:resource', [
            'name' => $modelName,
        ]);

        $this->call('make:graphql:type', [
            'name' => $modelName,
        ]);

        $this->call('make:graphql:mutation', [
            'name' => $modelName. '/Create' . $modelName,
        ]);

        $this->call('make:graphql:mutation', [
            'name' => $modelName . '/Update' . $modelName,
        ]);
    }

    protected function getOptions()
    {
        $options = parent::getOptions();

        $options[] = ['graphql', null, InputOption::VALUE_NONE, 'Generate GraphQL classes for model'];

        return $options;
    }
}