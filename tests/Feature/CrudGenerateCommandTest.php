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
        ->expectsQuestion('What is the model name?', '')
        ->assertFailed();
});

it('validates model name is not a reserved keyword', function () {
    $this->artisan('crudify:generate class --fields=title:string')
        ->assertFailed();
});

it('requires fields or file option', function () {
    $this->artisan('crudify:generate Post')
        ->expectsQuestion('Define your fields', '')
        ->assertFailed();
});

it('generates volt files by default', function () {
    $this->artisan('crudify:generate Post --fields=title:string|body:text')
        ->expectsQuestion('Define relationships (optional)', '')
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

    $indexContent = file_get_contents(base_path('resources/views/pages/posts/index.blade.php'));
    expect($indexContent)->toContain("public string \$inlineSuggestion = '';");
    expect($indexContent)->toContain('public function updatedSearch(): void');
    expect($indexContent)->not->toContain('#[\Livewire\Attributes\Computed]');
    expect($indexContent)->not->toContain('public function inlineSuggestion(): string');
});

it('generates classic livewire files when --livewire is used', function () {
    $this->artisan('crudify:generate Post --fields=title:string|body:text --livewire')
        ->expectsQuestion('Define relationships (optional)', '')
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
        ->expectsQuestion('Define relationships (optional)', '')
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
        ->expectsQuestion('Define relationships (optional)', '')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();
});

it('respects --force option', function () {
    file_put_contents(base_path('app/Models/Post.php'), '<?php // existing');

    $this->artisan('crudify:generate Post --fields=title:string --force')
        ->expectsQuestion('Define relationships (optional)', '')
        ->assertSuccessful();

    $content = file_get_contents(base_path('app/Models/Post.php'));
    expect($content)->not->toContain('// existing');
});

it('fails without --force when file exists', function () {
    file_put_contents(base_path('app/Models/Post.php'), '<?php // existing');

    $this->artisan('crudify:generate Post --fields=title:string')
        ->expectsQuestion('Define relationships (optional)', '')
        ->assertFailed();
});

it('respects --dry-run option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --dry-run')
        ->expectsQuestion('Define relationships (optional)', '')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeFalse();
});

it('respects --soft-delete option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --soft-delete --livewire')
        ->expectsQuestion('Define relationships (optional)', '')
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

it('preserves yaml boolean field types and defaults', function () {
    $yaml = <<<'YAML'
model: Post
fields:
  title:
    type: string
    unique: true
  is_published:
    type: boolean
    default: false
YAML;

    $yamlPath = $this->tmpDir.'/post.yaml';
    file_put_contents($yamlPath, $yaml);

    $this->artisan('crudify:generate', ['--file' => $yamlPath, '--only' => 'migration;factory'])
        ->assertSuccessful();

    $migration = collect(glob(base_path('database/migrations/*_create_posts_table.php')))->first();
    $migrationContent = file_get_contents($migration);
    $factoryContent = file_get_contents(base_path('database/factories/PostFactory.php'));

    expect($migrationContent)->toContain("\$table->boolean('is_published')->default(false);");
    expect($migrationContent)->not->toContain("\$table->string('is_published')");
    expect($factoryContent)->toContain("'title' => fake()->unique()->bothify('????-????-####'),");
    expect($factoryContent)->toContain("'is_published' => fake()->boolean(),");
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

it('uses custom relationship display fields in generated views', function () {
    $this->artisan('crudify:generate Post --fields=title:string --relationships=author:belongsTo:User:email|tags:belongsToMany:Tag:slug --livewire')
        ->assertSuccessful();

    $indexView = file_get_contents(base_path('resources/views/livewire/pages/posts/index.blade.php'));
    $showView = file_get_contents(base_path('resources/views/livewire/pages/posts/show.blade.php'));
    $createView = file_get_contents(base_path('resources/views/livewire/pages/posts/create.blade.php'));

    expect($indexView)->toContain('{{ $post->author->email ?? $post->author->id }}');
    expect($indexView)->toContain('{{ $item->slug ?? $item->id }}');
    expect($showView)->toContain('{{ $post->author->email ?? $post->author->id }}');
    expect($showView)->toContain('{{ $item->slug ?? $item->id }}');
    expect($createView)->toContain('{{ $option->email ?? $option->id }}');
    expect($createView)->toContain('label="{{ $option->slug ?? $option->id }}"');
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

    $migration = collect(glob(base_path('database/migrations/*_create_articles_table.php')))->first();
    $migrationContent = file_get_contents($migration);

    expect(substr_count($migrationContent, "\$table->foreignId('author_id')"))->toBe(1);
    expect($migrationContent)->toContain("\$table->foreignId('author_id')->constrained('users');");
    expect($migrationContent)->not->toContain("\$table->string('author_id')");
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
        ->expectsQuestion('Define relationships (optional)', '')
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
        ->expectsOutputToContain("Invalid field token 'string,body'")
        ->assertFailed();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeFalse();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();
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
