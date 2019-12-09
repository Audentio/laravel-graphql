<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\Traits\GraphQLConsoleTrait;

class TypeMakeCommand extends \Rebing\GraphQL\Console\TypeMakeCommand
{
    use ExtendConsoleCommandTrait, GraphQLConsoleTrait;

    protected function getStub()
    {
        return __DIR__ . '/stubs/type.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\GraphQL\Types';
    }

    protected function qualifyClass($name)
    {
        $name = $this->qualifyClassSuffix($name);

        return parent::qualifyClass($name);
    }

    protected function qualifyClassSuffix($name)
    {
        return $this->suffixCommandClass($name, 'Type');
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);
        $stub = $this->replaceModelFields($stub, $name);
        $stub = $this->replaceTypeClass($stub);

        return $stub;
    }

    protected function replaceModelFields($stub, $name)
    {
        $dataType = $this->getDataType($name, 'Type');
        $modelClass = 'App\Models\\' . $dataType;
        $indent = '            ';

        $replaceItems = [
            'modelInclude' => '',
            'modelGraphQLFields' => '',
        ];
        if (class_exists($modelClass)) {
            $replaceItems['modelInclude'] = "use {$modelClass};\n";
            if (method_exists($modelClass, 'getOutputFields')) {
                $replaceItems['modelGraphQLFields'] .= "\n" . $indent . $dataType . '::getOutputFields(),';
            }

            if (method_exists($modelClass, 'getCommonFields')) {
                $replaceItems['modelGraphQLFields'] .= "\n" . $indent . $dataType . '::getCommonFields(),';
            }
        }

        foreach ($replaceItems as $find => $replace) {
            $stub = str_replace(
                '{' . $find . '}',
                $replace,
                $stub
            );
        }

        return $stub;
    }

    public function handle()
    {
        $return = parent::handle();

        $this->call('config:graphql');

        return $return;
    }
}