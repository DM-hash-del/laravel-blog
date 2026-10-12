<?php

use App\Services\AiAssist\FieldStates;
use App\Services\AiAssist\RequestBuilder;

beforeEach(function () {
    config([
        'services.openai.model' => 'unit-model',
        'services.openai.max_completion_tokens' => 1234,
    ]);

    $this->values = [
        'title' => 'Testing Laravel Apps',
        'excerpt' => '',
        'body' => 'Draft body text.',
        'category' => '',
        'tags' => [],
    ];
    $this->states = FieldStates::from($this->values, ['body' => true]);
});

test('the schema asks only for the fields to generate or enhance, strictly', function () {
    $body = (new RequestBuilder)->build($this->values, $this->states, ['Laravel', 'PHP'], ['testing']);
    $format = $body['response_format'];
    $schema = $format['json_schema']['schema'];

    expect($format['type'])->toBe('json_schema')
        ->and($format['json_schema']['strict'])->toBeTrue()
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and(array_keys($schema['properties']))->toBe(['excerpt', 'body', 'category', 'tags'])
        ->and($schema['required'])->toBe(['excerpt', 'body', 'category', 'tags'])
        ->and($schema['properties'])->not->toHaveKey('title');
});

test('the category must be an existing category name, or null when none fits', function () {
    $body = (new RequestBuilder)->build($this->values, $this->states, ['Laravel', 'PHP'], []);

    expect($body['response_format']['json_schema']['schema']['properties']['category'])
        ->toBe(['type' => ['string', 'null'], 'enum' => ['Laravel', 'PHP', null]]);
});

test('the category is left out when no categories exist', function () {
    $builder = new RequestBuilder;
    $body = $builder->build($this->values, $this->states, [], []);
    $payload = json_decode($body['messages'][1]['content'], true);

    expect($builder->askedFields($this->states, []))->not->toContain('category')
        ->and($body['response_format']['json_schema']['schema']['properties'])->not->toHaveKey('category')
        ->and($payload['fields']['category']['state'])->toBe('untouched');
});

test('every field is sent with its state and value, with the model and token cap from config', function () {
    $body = (new RequestBuilder)->build($this->values, $this->states, ['Laravel'], ['testing']);
    $payload = json_decode($body['messages'][1]['content'], true);

    expect($body['model'])->toBe('unit-model')
        ->and($body['max_completion_tokens'])->toBe(1234)
        ->and($body['messages'][0]['role'])->toBe('system')
        ->and($payload['fields'])->toBe([
            'title' => ['state' => 'untouched', 'value' => 'Testing Laravel Apps'],
            'excerpt' => ['state' => 'generate', 'value' => ''],
            'body' => ['state' => 'enhance', 'value' => 'Draft body text.'],
            'category' => ['state' => 'generate', 'value' => ''],
            'tags' => ['state' => 'generate', 'value' => []],
        ])
        ->and($payload['categories'])->toBe(['Laravel'])
        ->and($payload['existing_tags'])->toBe(['testing']);
});

test('the request body never contains the API key', function () {
    config(['services.openai.key' => 'sk-secret-value']);

    $body = (new RequestBuilder)->build($this->values, $this->states, ['Laravel'], []);

    expect(json_encode($body))->not->toContain('sk-secret-value');
});
