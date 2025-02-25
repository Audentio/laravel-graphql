<?php

namespace Audentio\LaravelGraphQL\Utils;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;

class GraphQLRequestTagUtil
{
    public static function concatenateTags(array $tags): array
    {
        $concatTags = [];

        foreach ($tags as $tag) {
            $reference = &$concatTags;
            $tagParts = explode(':', $tag, 3);
            foreach ($tagParts as $key=>$part) {
                if ($key === 2) {
                    $reference[] = $part;
                    continue;
                }
                if (!array_key_exists($part, $reference)) {
                    $reference[$part] = [];
                }

                $reference = &$reference[$part];
            }
        }

        $returnTags = [];

        foreach ($concatTags as $rootTag=>$subTags) {
            foreach ($subTags as $subTag => $children) {
                $returnTags[] = $rootTag . ':' . $subTag . ':' . implode(',', $children);
            }
        }

        return $returnTags;
    }

    public static function buildTagsForSource(Source $source, bool $excludeUndefinedOperations = true): array
    {
        $tags = [];

        try {
            $documentNode = Parser::parse($source);
            if (!$documentNode) return [];

            /** @var OperationDefinitionNode $node */
            foreach ($documentNode->definitions as $node) {
                $operationUndefined = false;
                $operationName = $node->name->value ?? null;
                $operationType = isset($node->operation) ? ucfirst($node->operation) : null;
                if (!$operationType) {
                    $operationUndefined = true;
                    $operationType = 'UndefinedOperation';

                }
                if ($excludeUndefinedOperations && $operationUndefined) {
                    continue;
                }
                if ($operationName) {
                    $tags[] = 'GraphQL:Operation:' . $operationName;
                }
                /** @var NodeList $selections */
                $selections = $node->selectionSet->selections;

                /** @var FieldNode $selection */
                foreach ($selections as $selection) {
                    $alias = $selection->alias;
                    $tag = 'GraphQL:' . $operationType . ':' . $selection->name->value;
                    if ($alias) {
                        $tag .= ':' . $alias->value;
                    }
                    $tags[] = $tag;
                }
            }
        } catch (\Throwable $e) {}

        return $tags;
    }
}
