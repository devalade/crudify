<?php

use Crudify\FieldParser;

it('parses simple fields', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text|is_published:boolean');

    $fields = $parser->getFields();

    expect($fields)->toHaveCount(3);
    expect($fields[0])->toBe([
        'name' => 'title',
        'type' => 'string',
        'nullable' => false,
        'unique' => false,
        'default' => null,
        'index' => false,
        'foreign_table' => null,
        'multiple' => false,
    ]);
    expect($fields[1]['type'])->toBe('text');
    expect($fields[2]['type'])->toBe('boolean');
});

it('parses fields with modifiers', function () {
    $parser = new FieldParser;
    $parser->parse('email:string:nullable:unique|age:integer:default:18');

    $fields = $parser->getFields();

    expect($fields[0]['nullable'])->toBeTrue();
    expect($fields[0]['unique'])->toBeTrue();
    expect($fields[1]['default'])->toBe('18');
});

it('parses foreign key fields', function () {
    $parser = new FieldParser;
    $parser->parse('user_id:foreign:users');

    $fields = $parser->getFields();

    expect($fields[0]['type'])->toBe('foreign');
    expect($fields[0]['foreign_table'])->toBe('users');
});

it('parses nullable foreign key fields', function () {
    $parser = new FieldParser;
    $parser->parse('user_id:foreign:users:nullable');

    $fields = $parser->getFields();

    expect($fields[0]['type'])->toBe('foreign');
    expect($fields[0]['foreign_table'])->toBe('users');
    expect($fields[0]['nullable'])->toBeTrue();
});

it('returns correct casts', function () {
    $parser = new FieldParser;
    $parser->parse('is_active:boolean|views:integer|price:float|published_at:datetime|meta:json');

    $casts = $parser->getCasts();

    expect($casts)->toBe([
        'is_active' => 'boolean',
        'views' => 'integer',
        'price' => 'float',
        'published_at' => 'datetime',
        'meta' => 'array',
    ]);
});

it('returns correct migration types', function () {
    $parser = new FieldParser;

    expect($parser->getMigrationType('string'))->toBe('string');
    expect($parser->getMigrationType('bigint'))->toBe('bigInteger');
    expect($parser->getMigrationType('datetime'))->toBe('dateTime');
    expect($parser->getMigrationType('foreign'))->toBe('foreignId');
});

it('parses image, file, and video fields', function () {
    $parser = new FieldParser;
    $parser->parse('photo:image|attachment:file|clip:video|gallery:image:multiple|docs:file:multiple|trailers:video:multiple');

    $fields = $parser->getFields();

    expect($fields)->toHaveCount(6);
    expect($fields[0])->toBe([
        'name' => 'photo',
        'type' => 'image',
        'nullable' => false,
        'unique' => false,
        'default' => null,
        'index' => false,
        'foreign_table' => null,
        'multiple' => false,
    ]);
    expect($fields[1]['type'])->toBe('file');
    expect($fields[2]['type'])->toBe('video');
    expect($fields[3]['multiple'])->toBeTrue();
    expect($fields[3]['type'])->toBe('image');
    expect($fields[4]['multiple'])->toBeTrue();
    expect($fields[4]['type'])->toBe('file');
    expect($fields[5]['multiple'])->toBeTrue();
    expect($fields[5]['type'])->toBe('video');
});

it('returns correct migration types for image, file, and video fields', function () {
    $parser = new FieldParser;

    expect($parser->getMigrationType('image'))->toBe('string');
    expect($parser->getMigrationType('file'))->toBe('string');
    expect($parser->getMigrationType('video'))->toBe('string');
    expect($parser->getMigrationType('image', true))->toBe('json');
    expect($parser->getMigrationType('file', true))->toBe('json');
    expect($parser->getMigrationType('video', true))->toBe('json');
});

it('returns correct casts for multiple media fields', function () {
    $parser = new FieldParser;
    $parser->parse('gallery:image:multiple|trailers:video:multiple');

    expect($parser->getCasts())->toBe([
        'gallery' => 'array',
        'trailers' => 'array',
    ]);
});

it('ignores empty field names', function () {
    $parser = new FieldParser;
    $parser->parse('title:string||body:text');

    expect($parser->getFields())->toHaveCount(2);
});

it('supports pipe and semicolon field separators', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text;is_published:boolean');

    $fields = $parser->getFields();

    expect($fields)->toHaveCount(3);
    expect($fields[0]['name'])->toBe('title');
    expect($fields[1]['name'])->toBe('body');
    expect($fields[2]['name'])->toBe('is_published');
});

it('does not split fields on commas anymore', function () {
    $parser = new FieldParser;
    $parser->parse('title:string,body:text');

    expect($parser->getFields())->toHaveCount(1);
    expect($parser->getFields()[0]['name'])->toBe('title');
});
