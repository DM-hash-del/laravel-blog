<?php

namespace App\Services\AiAssist;

/**
 * Builds the Chat Completions request body for the post editor.
 *
 * Every field is sent with its state, and the strict JSON schema asks only
 * for the fields to generate or enhance.
 */
final class RequestBuilder
{
    /**
     * The instructions sent as the system message.
     */
    private const string Instructions = <<<'TEXT'
        You help the owner of a blog write posts. You receive every field of the post editor with a state:
        - "generate": write this field from scratch.
        - "enhance": improve the existing value, keeping its meaning and the author's voice.
        - "untouched": read-only context. Do not rewrite it; use it to inform the other fields.
        Field rules:
        - title: a clear, specific title of at most 255 characters.
        - excerpt: one or two sentences summarising the post; it is also the page's search description.
        - body: the full post in Markdown. Use headings, lists and code blocks where they help. Never include raw HTML.
        - category: exactly one name from the categories list that genuinely fits the post, or null if none fits. Never invent a category.
        - tags: 3 to 6 short, lowercase tags. Prefer names from the existing tags list when they fit.
        Reply only with JSON matching the schema.
        TEXT;

    /**
     * Get the fields that will be asked for: the requested ones, minus the category when none exist.
     *
     * @param  list<string>  $categoryNames
     * @return list<string>
     */
    public function askedFields(FieldStates $states, array $categoryNames): array
    {
        return array_values(array_filter(
            $states->requested(),
            fn (string $field): bool => $field !== 'category' || $categoryNames !== [],
        ));
    }

    /**
     * Build the request body.
     *
     * @param  array{title: string, excerpt: string, body: string, category: string, tags: list<string>}  $values
     * @param  list<string>  $categoryNames
     * @param  list<string>  $tagNames
     * @return array<string, mixed>
     */
    public function build(array $values, FieldStates $states, array $categoryNames, array $tagNames): array
    {
        $askedFields = $this->askedFields($states, $categoryNames);

        $fields = [];

        foreach (FieldStates::Fields as $field) {
            $fields[$field] = [
                'state' => in_array($field, $askedFields, true) ? $states->of($field) : FieldStates::Untouched,
                'value' => $values[$field],
            ];
        }

        return [
            'model' => config('services.openai.model'),
            'max_completion_tokens' => config('services.openai.max_completion_tokens'),
            'messages' => [
                ['role' => 'system', 'content' => self::Instructions],
                ['role' => 'user', 'content' => json_encode([
                    'fields' => $fields,
                    'categories' => $categoryNames,
                    'existing_tags' => $tagNames,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'post_suggestions',
                    'strict' => true,
                    'schema' => $this->schema($askedFields, $categoryNames),
                ],
            ],
        ];
    }

    /**
     * Build the strict schema for only the asked-for fields.
     *
     * @param  list<string>  $askedFields
     * @param  list<string>  $categoryNames
     * @return array<string, mixed>
     */
    private function schema(array $askedFields, array $categoryNames): array
    {
        $properties = [];

        foreach ($askedFields as $field) {
            $properties[$field] = match ($field) {
                'category' => ['type' => ['string', 'null'], 'enum' => [...$categoryNames, null]],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                default => ['type' => 'string'],
            };
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $askedFields,
            'additionalProperties' => false,
        ];
    }
}
