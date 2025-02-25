<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\Traits\GraphQLConsoleTrait;

class EnumMakeCommand extends \Rebing\GraphQL\Console\EnumMakeCommand
{
    use ExtendConsoleCommandTrait, GraphQLConsoleTrait;

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/enum.stub';
    }

    protected function qualifyClass($name)
    {
        $name = $this->normalizeTypeName($name, 'Enum');

        return parent::qualifyClass($name);
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        return $this->replaceTypeClass($stub);
    }

    public function handle()
    {
        $return = parent::handle();

        $this->call('config:graphql');

        return $return;
    }
}
