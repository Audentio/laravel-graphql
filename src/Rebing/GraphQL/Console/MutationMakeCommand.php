<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\Traits\GraphQLConsoleTrait;

class MutationMakeCommand extends \Rebing\GraphQL\Console\MutationMakeCommand
{
    use ExtendConsoleCommandTrait, GraphQLConsoleTrait;

    protected function getStub()
    {
        return __DIR__ . '/stubs/mutation.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\GraphQL\Mutations';
    }

    protected function qualifyClass($name)
    {
        $name = $this->suffixCommandClass($name, 'Mutation');

        return parent::qualifyClass($name);
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $stub = $this->replaceModelFields($stub, $name);
        $stub = $this->replaceGraphQLType($stub, $name);
        $stub = $this->replaceActionDataType($stub, $name);
        $stub = $this->replaceDataType($stub, $name);
        $stub = $this->replaceTypeClass($stub);

        return $stub;
    }

    protected function replaceModelFields($stub, $name)
    {
        $dataType = $this->getDataType($name, 'Mutation');
        $actionName = $this->guessActionName($name);
        preg_match_all('/((?:^|[A-Z])[a-z]+)/',$actionName,$matches);
        $action = lcfirst(reset($matches[1]));
        $modelClass = 'App\Models\\' . $dataType;
        $indent = '                        ';
        $replaceItems = [
            'modelInclude' => '',
            'modelGraphQLFields' => '',
        ];
        if (class_exists($modelClass)) {
            $replaceItems['modelInclude'] = "use {$modelClass};\n";
            if (method_exists($modelClass, 'getCommonFields')) {
                $replaceItems['modelGraphQLFields'] .= "\n" . $indent . $dataType . '::getCommonFields(' . ($action === 'create' ? '' : 'true') . '),';
            }
            if (method_exists($modelClass, 'getInputFields')) {
                $replaceItems['modelGraphQLFields'] .= "\n" . $indent . $dataType . '::getInputFields(' . ($action === 'create' ? '' : 'true') . '),';
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

    protected function replaceGraphQLType($stub, $name)
    {
        $graphQLType = $this->getDataType($name, 'Mutation');

        return str_replace(
            '{GraphQLType}',
            $graphQLType,
            $stub
        );
    }

    protected function replaceActionDataType($stub, $name)
    {
        $actionName = $this->guessActionName($name);

        return str_replace(
            'actionDataType',
            $actionName,
            $stub
        );
    }

    protected function replaceDataType($stub, $name)
    {
        $actionName = $this->guessActionName($name);
        preg_match_all('/((?:^|[A-Z])[a-z]+)/',$actionName,$matches);

        $dataType = lcfirst(end($matches[1]));

        return str_replace(
            'dataType',
            $dataType,
            $stub
        );
    }

    protected function guessActionName($name)
    {
        preg_match('/([^\\\]+)$/', $name, $matches);
        return lcfirst(substr($matches[1], 0, -8));
    }
}