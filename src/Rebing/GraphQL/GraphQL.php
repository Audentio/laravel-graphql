<?php

namespace Audentio\LaravelGraphQL\Rebing\GraphQL;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use Illuminate\Support\Facades\Cache;
use Rebing\GraphQL\GraphQL as BaseGraphQL;

class GraphQL extends BaseGraphQL
{
    public static function buildLaravelSchemaCache(?string $schemaName = null, ?int $duration = null): void
    {
        $instance = new self(app(), config());
        $schemaName = $schemaName ?? config()->get('graphql.default_schema', 'default');

        $schema = $instance->schema($schemaName, true);
        $instance->storeSchemaInLaravelCache($schemaName, $schema, $duration);
    }

    public static function clearLaravelSchemaCache(?string $schemaName = null): void
    {
        $instance = new self(app(), config());
        $schemaName = $schemaName ?? config()->get('graphql.default_schema', 'default');
        $instance->clearSchemaLaravelCache($schemaName);
    }

    public function schema(?string $schemaName = null, bool $forceRefresh = false): Schema
    {
        if (!$this->config->get('audentioGraphQL.enableSchemaCache')) {
            return parent::schema($schemaName);
        }

        $schemaName = $schemaName ?? $this->config->get('graphql.default_schema', 'default');

        if (isset($this->schemas[$schemaName])) {
            return $this->schemas[$schemaName];
        }

        if (!$forceRefresh && Cache::has('gqlSchema.'.$schemaName)) {
            $schemaConfig = static::getNormalizedSchemaConfiguration($schemaName);
            $schema = $this->buildSchemaFromLaravelCache($schemaName, $schemaConfig);
        } else {
            $schema = parent::schema($schemaName);
            $this->storeSchemaInLaravelCache($schemaName, $schema, $this->config->get('audentioGraphQL.schemaCacheTTL'));
        }

        return $schema;
    }

    public function storeSchemaInLaravelCache(string $schemaName, Schema $schema, ?int $duration = 300)
    {
        $schemaConfig = $schema->getConfig();
        $prop = new \ReflectionProperty($schemaConfig, 'types');
        $prop->setAccessible(true);
        $prop->setValue($schemaConfig, null);

        $prop = new \ReflectionProperty($schema, 'config');
        $prop->setAccessible(true);
        $prop->setValue($schema, $schemaConfig);

        $cache = [
            'schema' => $schema,
            'types' => $this->types,
            'typeInstances' => $this->typesInstances
        ];

        $cacheContent = \Opis\Closure\serialize($cache);
        if (config('audentioGraphQL.schemaCacheStorageMechanism') == 'file') {
            file_put_contents($this->getSchemaFileCacheName($schemaName), $cacheContent);
        } else {
            if ($duration === null) {
                Cache::forever('gqlSchema.'.$schemaName, $cacheContent);
            } else {
                Cache::put('gqlSchema.'.$schemaName, $cacheContent);
            }
        }
    }

    protected function getSchemaFileCacheName(string $schemaName): string
    {
        return rtrim(config('audentioGraphQL.schemaFileCachePath'), '/').'/gqlSchema-'.$schemaName.'.dat';
    }

    protected function clearSchemaLaravelCache(string $schemaName): void
    {
        if (config('audentioGraphQL.schemaCacheStorageMechanism') == 'file') {
            unlink($this->getSchemaFileCacheName($schemaName));
        } else {
            Cache::forget('gqlSchema.'.$schemaName);
        }
    }

    protected function buildSchemaFromLaravelCache(string $schemaName, array $schemaConfig)
    {
        if (config('audentioGraphQL.schemaCacheStorageMechanism') == 'file') {
            $cacheContent = file_get_contents($this->getSchemaFileCacheName($schemaName));
        } else {
            $cacheContent = Cache::get('gqlSchema.'.$schemaName);
        }

        /** @var Schema $schema */
        $cache = \Opis\Closure\unserialize($cacheContent);
        $schema = $cache['schema'];

        $this->clearTypeInstances();
        $this->clearTypes();
        $this->types = $cache['types'];
        $this->typesInstances = $cache['typeInstances'];

        $config = $schema->getConfig();

        $config->setTypes(function () {
            $types = [];

            foreach ($this->getTypes() as $name => $type) {
                $types[] = $this->type($name);
            }

            return $types;
        });

        foreach ($schemaConfig['query'] as $query) {
            new $query;
        }

        $prop = new \ReflectionProperty($schema, 'config');
        $prop->setAccessible(true);
        $prop->setValue($schema, $config);

        $prop = new \ReflectionProperty($schema, 'resolvedTypes');
        $prop->setAccessible(true);
        $resolvedTypes = $prop->getValue($schema);

        $standardTypes = array_intersect_key($resolvedTypes, array_flip(['ID', 'String', 'Int', 'Float', 'Boolean']));
        Type::overrideStandardTypes($standardTypes);

        $class = new \ReflectionClass(Introspection::class);
        $map = array_intersect_key($resolvedTypes, array_flip(['__Schema', '__Type', '__Directive', '__Field', '__InputValue', '__EnumValue', '__TypeKind', '__DirectiveLocation']));
        $class->setStaticPropertyValue('map', $map);

        $prop->setValue($schema, $resolvedTypes);

        return $schema;
    }
}