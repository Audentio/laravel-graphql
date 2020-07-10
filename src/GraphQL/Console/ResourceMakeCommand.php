<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\TypeMakeCommand;
use Illuminate\Console\GeneratorCommand;

class ResourceMakeCommand extends GeneratorCommand
{
    use ExtendConsoleCommandTrait;

    protected $signature = 'make:graphql:resource {name}';
    protected $description = 'Create a new GraphQL resource class';
    protected $type = 'Type';

    protected function getStub()
    {
        return __DIR__.'/stubs/resource.stub';
    }

    protected function qualifyClass($name)
    {
        $name = $this->suffixCommandClass($name, 'Resource');

        return parent::qualifyClass($name);
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\GraphQL\Resources';
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        return $this->replaceGraphqlName($stub);
    }

    protected function replaceGraphqlName(string $stub): string
    {
        $graphqlName = $this->getNameInput();
        $graphqlName = preg_replace('/Type$/', '', $graphqlName);

        return str_replace(
            'DummyGraphqlName',
            $graphqlName,
            $stub
        );
    }
}