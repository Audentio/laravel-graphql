<?php

return [
    /*
     * If debug is enabled additional queries will be made available for debugging
     * SQL queries.
     */
    'enableDebug' => false,

    /*
     * If enabled, a base scope will be generated and passed to the $scope variable
     * automatically when relevant. For installs prior to 1.2 this should be set to
     * false.
     */
    'enableBaseScopeGeneration' => true,

    /*
     * If enabled, the `model` attribute will be assigned to all Types with an
     * associated model automatically.
     */
    'enableTypeModel' => true,

    /*
     * Sets the default value for `hasOperator` on filters. The old value was 'true'
     * so this may need adjusted on legacy applications when upgrading the library.
     */
    'filterDefaultHasOperatorValue' => false,

    /*
     * Prefix to automatically prepend to type names.
     */
    'namePrefix' => '',

    /**
     * Defines whether the GraphQL schema will be stored in a cache or built on every request
     */
    'enableSchemaCache' => false,

    /**
     * Can be 'laravel' to use laravels default cache or 'file' to store to a file cache. File caching
     * does not support expiry times.
     */
    'schemaCacheStorageMechanism' => 'laravel',

    /**
     * Path to the schema file cache data
     */
    'schemaFileCachePath' => null,

    /**
     * The default TTL for automatic schema caches. Use graphql:build-schema-cache to create
     * a persistent schema cache instead.
     */
    'schemaCacheTTL' => 300
];
