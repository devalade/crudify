<?php

use Crudify\Generators\BaseGenerator;
use Crudify\FieldParser;
use Illuminate\Filesystem\Filesystem;

it('uses laravel str helpers for pluralization', function () {
    $generator = new class(new Filesystem(), new FieldParser()) extends BaseGenerator {
        public function generate(string $model): array
        {
            return [];
        }

        public function types(): array
        {
            return ['test'];
        }

        public function testPluralize(string $word): string
        {
            return $this->pluralize($word);
        }
    };

    expect($generator->testPluralize('post'))->toBe('posts');
    expect($generator->testPluralize('category'))->toBe('categories');
    expect($generator->testPluralize('person'))->toBe('people');
});

it('throws when stub is missing', function () {
    $generator = new class(new Filesystem(), new FieldParser()) extends BaseGenerator {
        public function generate(string $model): array
        {
            return [];
        }

        public function types(): array
        {
            return ['test'];
        }

        public function testGetStub(string $name): string
        {
            return $this->getStub($name);
        }
    };

    expect(fn() => $generator->testGetStub('nonexistent'))->toThrow(\RuntimeException::class);
});

it('respects dry run option', function () {
    $generator = new class(new Filesystem(), new FieldParser(), ['dryRun' => true]) extends BaseGenerator {
        public function generate(string $model): array
        {
            $this->createFile('/tmp/crudify-test-dry-run.txt', 'test');
            return [];
        }

        public function types(): array
        {
            return ['test'];
        }
    };

    $generator->generate('Post');

    expect(file_exists('/tmp/crudify-test-dry-run.txt'))->toBeFalse();
});

it('throws when file exists without force', function () {
    $tmpFile = sys_get_temp_dir() . '/crudify-test-force.txt';
    file_put_contents($tmpFile, 'existing');

    $generator = new class(new Filesystem(), new FieldParser()) extends BaseGenerator {
        private string $path;

        public function setPath(string $path): void
        {
            $this->path = $path;
        }

        public function generate(string $model): array
        {
            $this->createFile($this->path, 'new content');
            return [];
        }

        public function types(): array
        {
            return ['test'];
        }
    };

    $generator->setPath($tmpFile);

    expect(fn() => $generator->generate('Post'))->toThrow(\RuntimeException::class, 'Use --force to overwrite');

    unlink($tmpFile);
});
