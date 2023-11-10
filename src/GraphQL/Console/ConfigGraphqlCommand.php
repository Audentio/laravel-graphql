<?php

namespace Audentio\LaravelGraphQL\GraphQL\Console;

use Audentio\LaravelBase\Console\Commands\AbstractConfigCommand;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Enums\ContentType\ContentTypeEnum;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Enums\Filter\FilterOperatorEnum;
use Audentio\LaravelGraphQL\GraphQL\Definitions\UnionTypes\ContentType\ContentUnionType;
use Audentio\LaravelGraphQL\LaravelGraphQL;

class ConfigGraphqlCommand extends AbstractConfigCommand
{
    protected function getConfig(): array
    {
        return [
            'query' => $this->buildQueries(),
            'mutation' => $this->buildMutations(),

            'types' => $this->buildTypes(),
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

        $queries = $this->formatResponse($classes, 'Query', true);

        $queries = array_replace(
            LaravelGraphQL::getDefaultSchema()['queries'] ?? [],
            $queries,
            []
        );

        ksort($queries);

        return $queries;
    }

    protected function buildMutations()
    {
        $dir = app_path('GraphQL/Mutations');

        $this->getRecursiveClasses($classes, $dir);

        $mutations = $this->formatResponse($classes, 'Mutation', true);

        $mutations = array_replace(
            LaravelGraphQL::getDefaultSchema()['mutations'] ?? [],
            $mutations,
            []
        );

        ksort($mutations);

        return $mutations;
    }

    protected function buildTypes(): array
    {
        $types = array_replace(
            LaravelGraphQL::getDefaultSchema()['types'] ?? [],
            $this->buildGeneralTypes(),
            $this->buildEnums(),
            $this->buildUnions(),
            $this->buildScalars(),
            []
        );

        return $types;
    }

    protected function buildGeneralTypes()
    {
        $dir = app_path('GraphQL/Types');

        $this->getRecursiveClasses($classes, $dir);

        return $this->formatResponse($classes, 'Type');
    }

    protected function buildScalars()
    {
        $dir = app_path('GraphQL/Scalars');
        $this->getRecursiveClasses($classes, $dir);
        return $this->formatResponse($classes, '');
    }

    protected function buildEnums()
    {
        $dir = app_path('GraphQL/Enums');
        $classes = [
            FilterOperatorEnum::class,
            ContentTypeEnum::class,
        ];

        $this->getRecursiveClasses($classes, $dir);

        return $this->formatResponse($classes, '');
    }

    protected function buildUnions()
    {
        $dir = app_path('GraphQL/UnionTypes');

        $classes = [
            ContentUnionType::class,
        ];

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
            if ($suffix !== 'UnionType') {
                if (substr($itemName, (-1 * $suffixLength)) === $suffix) {
                    $itemName = substr($itemName, 0, (-1 * $suffixLength));
                }
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
            if (substr($realName, 0, 1) === DIRECTORY_SEPARATOR) {
                $realName = 'App' . $realName;
            } else {
                $realName = 'App' . DIRECTORY_SEPARATOR . $realName;
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