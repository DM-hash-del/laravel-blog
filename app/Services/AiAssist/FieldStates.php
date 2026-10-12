<?php

namespace App\Services\AiAssist;

/**
 * Works out what the AI should do with each editor field.
 *
 * An empty field is generated. A filled field with its Enhance box ticked is
 * enhanced. A filled field left unticked is untouched: sent as read-only
 * context, and any output for it is discarded. A filled category is always
 * untouched, because the AI never changes a chosen category.
 */
final class FieldStates
{
    public const string Generate = 'generate';

    public const string Enhance = 'enhance';

    public const string Untouched = 'untouched';

    /**
     * The editor fields the AI can work on, in payload order.
     *
     * @var list<string>
     */
    public const array Fields = ['title', 'excerpt', 'body', 'category', 'tags'];

    /**
     * Fields the AI may fill when empty but never change once filled.
     *
     * A chosen category is the admin's decision; the AI only picks one when none is chosen.
     *
     * @var list<string>
     */
    public const array NotEnhanceable = ['category'];

    /**
     * @param  array<string, string>  $states
     */
    private function __construct(private readonly array $states) {}

    /**
     * Work out each field's state from its current value and its Enhance box.
     *
     * @param  array{title: string, excerpt: string, body: string, category: string, tags: list<string>}  $values
     * @param  array<string, bool>  $enhance
     */
    public static function from(array $values, array $enhance): self
    {
        $states = [];

        foreach (self::Fields as $field) {
            $states[$field] = match (true) {
                self::isEmpty($values[$field] ?? null) => self::Generate,
                in_array($field, self::NotEnhanceable, true) => self::Untouched,
                (bool) ($enhance[$field] ?? false) => self::Enhance,
                default => self::Untouched,
            };
        }

        return new self($states);
    }

    /**
     * Get one field's state.
     */
    public function of(string $field): string
    {
        return $this->states[$field];
    }

    /**
     * Get the fields the AI should write: those to generate or enhance.
     *
     * @return list<string>
     */
    public function requested(): array
    {
        return array_values(array_filter(self::Fields, fn (string $field): bool => $this->states[$field] !== self::Untouched));
    }

    /**
     * Get every field's state.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->states;
    }

    /**
     * Determine whether a field value counts as empty: blank text or no tags.
     */
    private static function isEmpty(mixed $value): bool
    {
        return is_array($value) ? $value === [] : trim((string) $value) === '';
    }
}
