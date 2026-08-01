<?php

namespace App\GraphQL\Support;

use App\Support\Graph\GraphEdgeExplorer;
use Nuwave\Lighthouse\Exceptions\ValidationException;

class GraphMutationInputValidator
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function createNode(array $input): void
    {
        $type = (string) ($input['type'] ?? '');
        $parent = is_array($input['parent'] ?? null) ? $input['parent'] : [];
        $parentType = $parent['type'] ?? null;
        $parentId = $parent['id'] ?? null;
        $attributes = is_array($input['attributes'] ?? null) ? $input['attributes'] : [];
        $errors = [];

        if ($type === 'space' && $parentType !== null && $parentType !== 'space') {
            $errors['input.parent.type'] = 'A Space node may only be contained by another Space.';
        }

        if (in_array($type, ['thread', 'post'], true) && (! is_string($parentId) || trim($parentId) === '')) {
            $errors['input.parent.id'] = 'A parent node is required.';
        }

        if ($type === 'thread' && ! in_array($parentType, ['space', 'thread'], true)) {
            $errors['input.parent.type'] = 'A Thread node must be contained by a Space or Thread.';
        }

        if ($type === 'post' && ! in_array($parentType, ['space', 'thread', 'post'], true)) {
            $errors['input.parent.type'] = 'A Post node must be contained by a Space, Thread, or Post.';
        }

        if ($type === 'thread' && ! is_string($attributes['title'] ?? null)) {
            $errors['input.attributes.title'] = 'A Thread title is required.';
        }

        $this->validateJsonAttributes($attributes, $errors);
        $this->throwIfInvalid($errors);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateNode(string $type, array $attributes): void
    {
        $errors = [];

        if ($attributes === []) {
            $errors['input.attributes'] = 'At least one node attribute is required.';
        }

        $allowed = match ($type) {
            'space' => ['status'],
            'thread' => ['title', 'purpose', 'phase', 'status'],
            'post' => ['post_type', 'tag', 'text', 'payload', 'meta', 'status', 'occurred_at'],
            default => [],
        };

        foreach (array_keys($attributes) as $attribute) {
            if (! in_array($attribute, $allowed, true)) {
                $errors["input.attributes.{$attribute}"] = "The {$attribute} attribute cannot be updated for a {$type} node.";
            }
        }

        $this->validateJsonAttributes($attributes, $errors);
        $this->throwIfInvalid($errors);
    }

    public function edgeType(string $edgeType): void
    {
        if (in_array($edgeType, GraphEdgeExplorer::ReservedEdgeTypes, true)) {
            $this->throwIfInvalid(['input.edgeType' => 'The edge type is not supported.']);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateEdge(array $attributes): void
    {
        if ($attributes === []) {
            $this->throwIfInvalid(['input' => 'At least one edge attribute is required.']);
        }

        if (is_string($attributes['edge_type'] ?? null)) {
            $this->edgeType($attributes['edge_type']);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $errors
     */
    protected function validateJsonAttributes(array $attributes, array &$errors): void
    {
        foreach (['payload', 'meta'] as $attribute) {
            if (array_key_exists($attribute, $attributes) && ! is_array($attributes[$attribute])) {
                $errors["input.attributes.{$attribute}"] = "The {$attribute} attribute must be a JSON object or array.";
            }
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function throwIfInvalid(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
