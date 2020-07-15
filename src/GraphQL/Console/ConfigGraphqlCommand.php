<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console;

use Audentio\LaravelBase\Console\Commands\AbstractConfigCommand;
use Audentio\LaravelGraphQL\GraphQL\Enums\Filter\FilterOperatorEnum;

class ConfigGraphqlCommand extends AbstractConfigCommand
{
    protected function getConfig(): array
    {
        return [
            'query' => $this->buildQueries(),
            'mutation' => $this->buildMutations(),

            'types' => array_merge(
                $this->buildTypes(),
                $this->buildEnums(),
                $this->buildUnions(),
                []
            ),
        ];
    }

    protected function getConfigFileName(): string
    {
        return 'gqlData.php';
    }

    protected function buildQueries()
    {
        $dir = app_path('GraphQL/Queries');

        $this->getRecursiveClasses($classes, $dir);

        return $this->formatResponse($classes, 'Query', true);
    }

    protected function buildMutations()
    {
        $dir = app_path('GraphQL/Mutations');

        $this->getRecursiveClasses($classes, $dir);

        return $this->formatResponse($classes, 'Mutation', true);
    }

    protected function buildTypes()
    {
        $dir = app_path('GraphQL/Types');

        $this->getRecursiveClasses($classes, $dir);

        return $this->formatResponse($classes, 'Type');
    }

    protected function buildEnums()
    {
        $dir = app_path('GraphQL/Enums');

        $this->getRecursiveClasses($classes, $dir);

        $classes['FilterOperatorEnum'] = FilterOperatorEnum::class;

        return $this->formatResponse($classes, '');
    }

    protected function buildUnions()
    {
        $dir = app_path('GraphQL/UnionTypes');

        $this->getRecursiveClasses($classes, $dir);

        return $this->formatResponse($classes, 'UnionType');
    }

    protected function formatResponse(array $classes, $suffix, $lcfirst = false)
    {
        $suffixLength = strlen($suffix);
        $return = [];
        foreach ($classes as $class) {
            $parts = explode('\\', $class);
            $itemName = end($parts);
            if (substr($itemName, (-1 * $suffixLength)) === $suffix) {
                $itemName = substr($itemName, 0, (-1 * $suffixLength));
            }

            if ($lcfirst) {
                $itemName = lcfirst($itemName);
            }

            $return[$itemName] = $class;
        }

        return $return;
    }

    protected function getRecursiveClasses(&$classes, $baseDir, $dir = '')
    {
        if (!$classes) {
            $classes = [];
        }

        if (!$dir) {
            $dir = $baseDir;
        }
        foreach (glob($dir . '/*') as $filePath) {
            if (is_dir($filePath)) {
                $this->getRecursiveClasses($classes, $baseDir, $filePath);
                continue;
            }

            $realName = str_replace(app_path(), '', $filePath);
            $realName = str_replace('.php', '', $realName);
            if (substr($realName, 0, 1) === '/') {
                $realName = 'App' . $realName;
            } else {
                $realName = 'App/' . $realName;
            }

            $className = str_replace('/', '\\', $realName);

            if (!class_exists($className)) {
                continue;
            }

            try {
                $class = new \ReflectionClass($className);
            } catch (\ReflectionException $e) {
                continue;
            }

            if ($class->isAbstract()) {
                continue;
            }

            $classes[] = $className;
        }
    }

}