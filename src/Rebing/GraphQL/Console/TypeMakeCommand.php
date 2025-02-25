<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL\Console;

use Audentio\LaravelBase\Traits\ExtendConsoleCommandTrait;
use Audentio\LaravelGraphQL\GraphQL\Console\Traits\GraphQLConsoleTrait;

class TypeMakeCommand extends \Rebing\GraphQL\Console\TypeMakeCommand
{
    use ExtendConsoleCommandTrait, GraphQLConsoleTrait;

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/type.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\GraphQL\Types';
    }

    protected function qualifyClass($name)
    {
        $name = $this->normalizeTypeName($name, 'Type');

        return parent::qualifyClass($name);
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $stub = $this->replaceModelFields($stub, $name);
        $stub = $this->replaceTypeClass($stub);
        return $this->replaceResourceClass($stub, $name);
    }

    protected function replaceModelFields($stub, $name)
    {
        $nameParts = explode('\\', $name);
        $typeName = array_pop($nameParts);
        $dataType = $this->getDataType($name, 'Type');
        $modelClass = 'App\Models\\' . $dataType;
        $indent = '            ';

        $replaceItems = [
            'modelInclude' => '',
            'modelGraphQLFields' => '',
            'DummyType' => $typeName,
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

    protected function replaceResourceClass($stub, $name)
    {
        $replacements = [];

        $resourceName = substr(class_basename($name), 0, -4) . 'Resource';
        $resourceClass = 'App\GraphQL\Resources\\' . $resourceName;

        if (!class_exists($resourceClass)) {
            $this->call('make:graphql:resource', ['name' => $resourceName]);
        }

        if ($resourceClass) {
            $replacements['{resourceClass}'] = 'return ' . $resourceName . '::class;';
            $replacements['{resourceInclude}'] = 'use ' . $resourceClass . ";\n";
        } else {
            $replacements['{resourceClass}'] = '// TODO: Implement getResourceClassName() method.';
            $replacements['{resourceInclude}'] = '';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    public function handle()
    {
        $return = parent::handle();

        $this->call('config:graphql');

        return $return;
    }
}
