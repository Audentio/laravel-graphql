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
];