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

it('generates all files successfully', function () {
    $this->artisan('crudify:generate Post --fields=title:string,body:text')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Requests/StorePostRequest.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Requests/UpdatePostRequest.php')))->toBeTrue();
    expect(file_exists(base_path('app/Policies/PostPolicy.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Index.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Create.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Edit.php')))->toBeTrue();
    expect(file_exists(base_path('app/Livewire/Pages/Posts/Show.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/index.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/create.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/edit.blade.php')))->toBeTrue();
    expect(file_exists(base_path('resources/views/livewire/pages/posts/show.blade.php')))->toBeTrue();
    expect(glob(base_path('database/migrations/*_create_posts_table.php')))->toHaveCount(1);
});

it('respects --only option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --only=model,migration')
        ->assertSuccessful();

    expect(file_exists(base_path('app/Models/Post.php')))->toBeTrue();
    expect(file_exists(base_path('app/Http/Controllers/PostsController.php')))->toBeFalse();
});

it('respects --skip option', function () {
    $this->artisan('crudify:generate Post --fields=title:string --skip=controller')
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
    $this->artisan('crudify:generate Post --fields=title:string --soft-delete')
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
});

it('generates model with relationships via cli option', function () {
    $this->artisan('crudify:generate Post --fields=title:string,user_id:foreign:users --relationships=user:belongsTo:User')
        ->assertSuccessful();

    $content = file_get_contents(base_path('app/Models/Post.php'));
    expect($content)->toContain('public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
    expect($content)->toContain('return $this->belongsTo(\App\Models\User::class);');
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
