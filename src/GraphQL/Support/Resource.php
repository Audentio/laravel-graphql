<?php

namespace Audentio\LaravelGraphQL\GraphQL\Support;

abstract class Resource
{
    public function getTypeFields(): array
    {
        return array_merge(
            $this->getOutputFields(),
            $this->getCommonFields(false)
        );
    }

    abstract public function getExpectedModelClass(): ?string;

    abstract public function getOutputFields(): array;
    abstract public function getInputFields(bool $update = false): array;
    abstract public function getCommonFields(bool $update = false): array;
    abstract public function getGraphQLTypeName();
}