<?php

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/crudify-cmd-tests-'.uniqid();
    mkdir($this->tmpDir, 0777, true);

    mkdir($this->tmpDir.'/app/Models', 0755, true);
    mkdir($this->tmpDir.'/app/Http/Controllers', 0755, true);
    mkdir($this->tmpDir.'/app/Http/Requests', 0755, true);
    mkdir($this->tmpDir.'/app/Policies', 0755, true);
    mkdir($this->tmpDir.'/app/Livewire/Pages', 0755, true);
    mkdir($this->tmpDir.'/resources/views/livewire/pages', 0755, true);
    mkdir($this->tmpDir.'/database/migrations', 0755, true);
    mkdir($this->tmpDir.'/database/factories', 0755, true);
    mkdir($this->tmpDir.'/database/seeders', 0755, true);
    mkdir($this->tmpDir.'/routes', 0755, true);
    file_put_contents($this->tmpDir.'/routes/web.php', "<?php\n");

    $this->swapAppPaths($this->tmpDir);
});

afterEach(function () {
    if (is_dir($this->tmpDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->tmpDir);
    }
});

it('validates model name is required', function () {
    $this->artisan('crudify:generate')
        ->assertFailed();
});

it('validates model name is not a reserved keyword', function () {
    $this->artisan('crudify:generate class --fields=title:string')
        ->assertFailed();
});

it('requires fields or file option', function () {
    $this->artisan('crudify:generate Post')
        ->assertFailed();
});

it('generates volt files by default', function () {
    $this->artisan('crudify:generate Post --fields=title:string|body:text')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Policies/PostPolicy.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/pages/posts/index.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/pages/posts/create.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/pages/posts/edit.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/pages/posts/show.blade.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();
    expect(file_exists(base_path('app/Http/Requests/StorePostRequest.php')))->toBeFalse();
    expect(file_exists(base_path('app/Http/Requests/UpdatePostRequest.php')))->toBeFalse();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Index.php')))->toBeFalse();
    expect(glob(base_path('database/migrations/*_create_posts_table.php')))->toHaveCount(1);
    expect(file_exists(base_path('database/factories/PostFactory.php')))->toBeTrue();
    expect(file_exists(base_path('database/seeders/PostSeeder.php')))->toBeTrue();
});

it('generates classic livewire files when --livewire is used', function () {
    $this->artisan('crudify:generate Post --fields=title:string|body:text --livewire')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Requests/StorePostRequest.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Requests/UpdatePostRequest.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Index.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Create.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Edit.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Show.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/index.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/create.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/edit.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/show.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/pages/posts/index.blade.php')))->toBeFalse();
});

it('respects --only option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --only=model|migration')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();
});

it('accepts pipe and semicolon separators in cli options', function () {
    $this->artisan('crudify:generate Post --fields=title:string|body:text;user_id:foreign:users --relationships=user:belongsTo:User|tags:belongsToMany:Tag --only=model;livewire --livewire')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Index.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();

    $content = file_get_contents(base_path('app/Models/Post.php'));
    expect($content)->toContain('public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
    expect($content)->toContain('public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany');
});

it('respects --skip option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --skip=controller --livewire')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();
});

it('respects --force option', function () {
    file_put_contents(base_path('app/Models/Post.php'), '<?php // existing');

    $this->artisan('crudify:generate Post --fields=title:string --force')
        ->assertSuccessful();

    $content = file_get_contents(base_path('app/Models/Post.php'));
    expect($content)->not->toContain('// existing');
});

it('fails without --force when file exists', function () {
    file_put_contents(base_path('app/Models/Post.php'), '<?php // existing');

    $this->artisan('crudify:generate Post --fields=title:string')
        ->assertFailed();
});

it('respects --dry-run option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --dry-run')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeFalse();
});

it('respects --soft-delete option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --soft-delete --livewire')
        ->assertSuccessful();

    $modelContent = file_get_contents(base_path('app/Models/Post.php'));
    expect($modelContent)->toContain('SoftDeletes');

    $migration = glob(base_path('database/migrations/*_create_posts_table.php'))[0];
    $migrationContent = file_get_contents($migration);
    expect($migrationContent)->toContain('$table->softDeletes();');
});

it('handles yaml file input', function () {
    $yaml = <<<'YAML'
model: Article
fields:
  title: string
  body: text
options:
  soft_deletes: true
YAML;

    $yamlPath = $this->tmpDir.'/test.yaml';
    file_put_contents($yamlPath, $yaml);

    $this->artisan('crudify:generate', ['--file' => $yamlPath])
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Article.php')))->toBeTrue();

    $content = file_get_contents(base_path('app/Models/Article.php'));
    expect($content)->toContain("'title'");
    expect($content)->toContain("'body'");
});

it('generates model with relationships via cli option', function () {
    $this->artisan('crudify:generate Post --fields=title:string|user_id:foreign:users --relationships=user:belongsTo:User')
        ->assertSuccessful();

    $content = file_get_contents(base_path('app/Models/Post.php'));
    expect($content)->toContain('public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
    expect($content)->toContain('return $this->belongsTo(\App\Models\User::class);');
});

it('generates belongsToMany support artifacts from cli option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --relationships=tags:belongsToMany:Tag --livewire')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Tag.php')))->toBeTrue();
    expect(glob(base_path('database/migrations/*_create_post_tag_table.php')))->toHaveCount(1);

    $storeRequest = file_get_contents(base_path('app/Http/Requests/StorePostRequest.php'));
    expect($storeRequest)->toContain("'selectedTagsIds' => ['nullable', 'array']");
    expect($storeRequest)->toContain("'selectedTagsIds.*' => ['integer', Rule::exists('tags', 'id')]");

    $indexView = file_get_contents(base_path('resources/views/livewire/pages/posts/index.blade.php'));
    expect($indexView)->toContain('<flux:badge size="sm">{{ $item->name ?? $item->id }}</flux:badge>');
});

it('handles yaml file with relationships', function () {
    $yaml = <<<'YAML'
model: Article
fields:
  title: string
  author_id: foreign:users
relationships:
  author:
    type: belongsTo
    model: User
  comments:
    type: hasMany
    model: Comment
YAML;

    $yamlPath = $this->tmpDir.'/test.yaml';
    file_put_contents($yamlPath, $yaml);

    $this->artisan('crudify:generate', ['--file' => $yamlPath])
        ->assertSuccessful();

    $content = file_get_contents(base_path('app/Models/Article.php'));
    expect($content)->toContain('public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
    expect($content)->toContain('public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany');
});

it('handles yaml file with multiple fields after separator change', function () {
    $yaml = <<<'YAML'
model: Product
fields:
  name: string
  price: decimal
  photo:
    type: image
    nullable: true
YAML;

    $yamlPath = $this->tmpDir.'/product.yaml';
    file_put_contents($yamlPath, $yaml);

    $this->artisan('crudify:generate', ['--file' => $yamlPath])
        ->assertSuccessful();

    $content = file_get_contents(base_path('app/Models/Product.php'));
    expect($content)->toContain("'name'");
    expect($content)->toContain("'price'");
    expect($content)->toContain("'photo'");
});

it('generates factory and seeder with correct content', function () {
    $this->artisan('crudify:generate Post --fields=title:string|body:text|is_published:boolean')
        ->assertSuccessful();

    $factoryContent = file_get_contents(base_path('database/factories/PostFactory.php'));
    expect($factoryContent)->toContain('class PostFactory extends Factory');
    expect($factoryContent)->toContain('fake()->word()');
    expect($factoryContent)->toContain('fake()->paragraph()');
    expect($factoryContent)->toContain('fake()->boolean()');

    $seederContent = file_get_contents(base_path('database/seeders/PostSeeder.php'));
    expect($seederContent)->toContain('class PostSeeder extends Seeder');
    expect($seederContent)->toContain('Post::factory()->count(10)->create();');
});

it('does not accept comma separators in cli field lists', function () {
    $this->artisan('crudify:generate Post --fields=title:string,body:text --only=model|controller --livewire')
        ->assertSuccessful();

    $modelContent = file_get_contents(base_path('app/Models/Post.php'));
    expect($modelContent)->toContain("'title'");
    expect($modelContent)->not->toContain("'body'");
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeTrue();
});

it('respects yaml option to disable volt and generate classic livewire', function () {
    $yaml = <<<'YAML'
model: Article
fields:
  title: string
  body: text
options:
  volt: false
YAML;

    $yamlPath = $this->tmpDir.'/classic.yaml';
    file_put_contents($yamlPath, $yaml);

    $this->artisan('crudify:generate', ['--file' => $yamlPath])
        ->assertSuccessful();

    expect(file_exists(base_path('app/Livewire/Pages/Articles/Index.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/articles/index.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/pages/articles/index.blade.php')))->toBeFalse();
});
