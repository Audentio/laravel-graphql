<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\Traits\GraphQLConsoleTrait;

class EnumMakeCommand extends \Rebing\GraphQL\Console\EnumMakeCommand
{
    use ExtendConsoleCommandTrait, GraphQLConsoleTrait;

    protected function getStub()
    {
        return __DIR__ . '/stubs/enum.stub';
    }

    protected function qualifyClass($name)
    {
        $name = $this->normalizeTypeName($name, 'Enum');

        return parent::qualifyClass($name);
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);
        $stub = $this->replaceModelFields($stub, $name);
        $stub = $this->replaceTypeClass($stub);


        return $stub;
    }

    public function handle()
    {
        $return = parent::handle();

        $this->call('config:graphql');

        return $return;
    }
}