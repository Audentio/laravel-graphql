<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\Traits\GraphQLConsoleTrait;
use Illuminate\Support\Str;

class QueryMakeCommand extends \Rebing\GraphQL\Console\QueryMakeCommand
{
    use ExtendConsoleCommandTrait, GraphQLConsoleTrait;

    protected function getStub()
    {
        return __DIR__ . '/stubs/query.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\GraphQL\Queries';
    }

    protected function qualifyClass($name)
    {
        $name = $this->suffixCommandClass($name, 'Query');

        return parent::qualifyClass($name);
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $stub = $this->replaceModelFields($stub, $name);
        $stub = $this->replaceGraphQLType($stub, $name);
        $stub = $this->replaceArgs($stub, $name);
        $stub = $this->replaceRootDefinition($stub, $name);


        return $stub;
    }

    protected function replaceRootDefinition($stub, $name)
    {
        $replace = '';
        $indent = '        ';

        $dataType = $this->getQueryDataType($name, true);
        $modelClass = 'App\Models\\' . $dataType;
        if (class_exists($modelClass)) {
            $replace = "\n" . $indent . '$root = ' . $dataType . '::query();';
        }

        return str_replace('{rootDef}', $replace, $stub);
    }

    protected function replaceArgs($stub, $name)
    {
        $args = '';

        $indent = '            ';

        if ($this->isDataTypeSingular($name)) {
            $args = "\n" . $indent . '\'id\' => [\'type\' => Type::id(), \'rules\' => [\'required\']],';
        }

        $stub = str_replace('{args}', $args, $stub);
        return $stub;
    }

    protected function replaceGraphQLType($stub, $name)
    {
        $dataType = $this->getQueryDataType($name, true);
        $dataTypeSingular = $this->isDataTypeSingular($name);

        $modelClass = 'App\Models\\' . $dataType;

        $replace = 'Type::listOf(Type::string())';

        if (class_exists($modelClass)) {
            $replace = 'GraphQL::type(\'' . $dataType . '\')';

            if (!$dataTypeSingular) {
                $replace = 'Type::listOf(' . $replace . ')';
            }
        }

        $stub = str_replace('{graphQLTypeStatement}', $replace, $stub);

        return $stub;
    }

    protected function replaceModelFields($stub, $name)
    {
        $dataType = $this->getQueryDataType($name, true);
        $modelClass = 'App\Models\\' . $dataType;

        $replaceItems = [
            'modelInclude' => '',
            'modelGraphQLFields' => '',
        ];
        if (class_exists($modelClass)) {
            $replaceItems['modelInclude'] = "use {$modelClass};\n";
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

    protected function isDataTypeSingular($name)
    {
        $dataType = $this->getQueryDataType($name);

        if (Str::singular($dataType) !== $dataType) {
            return false;
        }

        return true;
    }

    protected function getQueryDataType($name, bool $forceSingular = false)
    {
        $dataType = $this->getDataType($name, 'Query');

        if ($forceSingular) {
            $dataType = Str::singular($dataType);
        }

        return $dataType;
    }

    public function handle()
    {
        $return = parent::handle();

        $this->call('config:graphql');

        return $return;
    }
}