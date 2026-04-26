<?php

use Crudify\FieldParser;

it('parses simple fields', function () {
    $parser = new FieldParser;
    $parser->parse('title:string,body:text,is_published:boolean');

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
    $parser->parse('email:string:nullable:unique,age:integer:default:18');

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
    $parser->parse('is_active:boolean,views:integer,price:float,published_at:datetime,meta:json');

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

it('parses image and file fields', function () {
    $parser = new FieldParser;
    $parser->parse('photo:image,attachment:file,gallery:image:multiple,docs:file:multiple');

    $fields = $parser->getFields();

    expect($fields)->toHaveCount(4);
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
    expect($fields[2]['multiple'])->toBeTrue();
    expect($fields[2]['type'])->toBe('image');
    expect($fields[3]['multiple'])->toBeTrue();
    expect($fields[3]['type'])->toBe('file');
});

it('returns correct migration types for image and file fields', function () {
    $parser = new FieldParser;

    expect($parser->getMigrationType('image'))->toBe('string');
    expect($parser->getMigrationType('file'))->toBe('string');
    expect($parser->getMigrationType('image', true))->toBe('json');
    expect($parser->getMigrationType('file', true))->toBe('json');
});

it('returns correct casts for multiple file fields', function () {
    $parser = new FieldParser;
    $parser->parse('gallery:image:multiple');

    expect($parser->getCasts())->toBe(['gallery' => 'array']);
});

it('ignores empty field names', function () {
    $parser = new FieldParser;
    $parser->parse('title:string,,body:text');

    expect($parser->getFields())->toHaveCount(2);
});
