<?php

use App\Services\AiAssist\FieldStates;

function fieldValues(array $overrides = []): array
{
    return [
        'title' => '',
        'excerpt' => '',
        'body' => '',
        'category' => '',
        'tags' => [],
        ...$overrides,
    ];
}

test('an empty field is generated whether or not it is ticked', function (bool $ticked) {
    $states = FieldStates::from(fieldValues(), ['title' => $ticked]);

    expect($states->of('title'))->toBe(FieldStates::Generate);
})->with([true, false]);

test('a filled, ticked field is enhanced', function () {
    $states = FieldStates::from(fieldValues(['body' => 'Some text']), ['body' => true]);

    expect($states->of('body'))->toBe(FieldStates::Enhance);
});

test('a filled, unticked field is untouched', function () {
    $states = FieldStates::from(fieldValues(['excerpt' => 'Some text']), ['excerpt' => false]);

    expect($states->of('excerpt'))->toBe(FieldStates::Untouched);
});

test('whitespace counts as empty', function () {
    expect(FieldStates::from(fieldValues(['title' => "  \n "]), [])->of('title'))->toBe(FieldStates::Generate);
});

test('tags are empty only when there are no tag names', function () {
    expect(FieldStates::from(fieldValues(['tags' => []]), [])->of('tags'))->toBe(FieldStates::Generate)
        ->and(FieldStates::from(fieldValues(['tags' => ['php']]), [])->of('tags'))->toBe(FieldStates::Untouched);
});

test('requested lists only the fields to generate or enhance, in field order', function () {
    $states = FieldStates::from(
        fieldValues(['title' => 'A title', 'excerpt' => 'An excerpt', 'category' => 'Laravel', 'tags' => ['php']]),
        ['excerpt' => true],
    );

    expect($states->requested())->toBe(['excerpt', 'body'])
        ->and($states->toArray())->toBe([
            'title' => 'untouched',
            'excerpt' => 'enhance',
            'body' => 'generate',
            'category' => 'untouched',
            'tags' => 'untouched',
        ]);
});

test('a chosen category is never enhanced, but an empty one is generated', function () {
    expect(FieldStates::from(fieldValues(['category' => 'Laravel']), ['category' => true])->of('category'))->toBe(FieldStates::Untouched)
        ->and(FieldStates::from(fieldValues(), ['category' => false])->of('category'))->toBe(FieldStates::Generate);
});
