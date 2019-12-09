<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console;

use Audentio\LaravelGraphQL\Rebing\GraphQL\Console\TypeMakeCommand;

class EnumMakeCommand extends TypeMakeCommand
{
    protected $signature = 'make:graphql:enum {name}';

    protected function getStub()
    {
        return __DIR__ . '/stubs/enum.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\GraphQL\Enums';
    }

    protected function qualifyClassSuffix($name)
    {
        return $this->suffixCommandClass($name, 'Enum');
    }
}