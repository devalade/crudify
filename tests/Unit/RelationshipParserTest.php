<?php

use Crudify\RelationshipParser;

it('parses relationship string', function () {
    $parser = new RelationshipParser;
    $parser->parse('user:belongsTo:User|comments:hasMany:Comment');

    $relationships = $parser->getRelationships();

    expect($relationships)->toHaveCount(2);
    expect($relationships[0])->toBe([
        'name' => 'user',
        'type' => 'belongsTo',
        'model' => 'User',
        'display' => 'name',
    ]);
    expect($relationships[1])->toBe([
        'name' => 'comments',
        'type' => 'hasMany',
        'model' => 'Comment',
        'display' => 'name',
    ]);
});

it('ignores empty or invalid relationship strings', function () {
    $parser = new RelationshipParser;
    $parser->parse('user:belongsTo:User||invalid|comments:hasMany:Comment');

    expect($parser->getRelationships())->toHaveCount(2);
});

it('sets relationships from array', function () {
    $parser = new RelationshipParser;
    $parser->setRelationships([
        ['name' => 'author', 'type' => 'belongsTo', 'model' => 'User'],
    ]);

    expect($parser->getRelationships())->toHaveCount(1);
    expect($parser->getRelationships()[0]['name'])->toBe('author');
});

it('supports fully qualified model classes', function () {
    $parser = new RelationshipParser;
    $parser->parse('owner:belongsTo:App\\Models\\User');

    $relationships = $parser->getRelationships();

    expect($relationships[0]['model'])->toBe('App\\Models\\User');
});

it('supports hasOne and belongsToMany types', function () {
    $parser = new RelationshipParser;
    $parser->parse('profile:hasOne:Profile|tags:belongsToMany:Tag');

    $relationships = $parser->getRelationships();

    expect($relationships[0]['type'])->toBe('hasOne');
    expect($relationships[1]['type'])->toBe('belongsToMany');
});

it('parses optional relationship display field', function () {
    $parser = new RelationshipParser;
    $parser->parse('tags:belongsToMany:Tag:slug');

    $relationships = $parser->getRelationships();

    expect($relationships[0]['display'])->toBe('slug');
});

it('supports pipe and semicolon relationship separators', function () {
    $parser = new RelationshipParser;
    $parser->parse('user:belongsTo:User|comments:hasMany:Comment;tags:belongsToMany:Tag');

    $relationships = $parser->getRelationships();

    expect($relationships)->toHaveCount(3);
    expect($relationships[0]['name'])->toBe('user');
    expect($relationships[1]['name'])->toBe('comments');
    expect($relationships[2]['name'])->toBe('tags');
});

it('does not split relationships on commas anymore', function () {
    $parser = new RelationshipParser;
    $parser->parse('user:belongsTo:User,comments:hasMany:Comment');

    expect($parser->getRelationships())->toHaveCount(1);
    expect($parser->getRelationships()[0]['model'])->toBe('User,comments');
});
