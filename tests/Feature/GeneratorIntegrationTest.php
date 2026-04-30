<?php

use Crudify\CrudifyServiceProvider;
use Crudify\FieldParser;
use Crudify\Generators\ControllerGenerator;
use Crudify\Generators\FactoryGenerator;
use Crudify\Generators\FormRequestGenerator;
use Crudify\Generators\LivewireComponentGenerator;
use Crudify\Generators\LivewireViewGenerator;
use Crudify\Generators\MigrationGenerator;
use Crudify\Generators\ModelGenerator;
use Crudify\Generators\RouteGenerator;
use Crudify\Generators\SeederGenerator;
use Crudify\Generators\VoltLivewireGenerator;
use Crudify\RelationshipParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/crudify-tests-'.uniqid();
    mkdir($this->tmpDir, 0777, true);

    // Create mock app structure
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

it('generates a valid controller', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text');

    $generator = new ControllerGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(1);
    expect(file_exists($paths[0]))->toBeTrue();

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('class PostsController extends Controller');
    expect($content)->toContain("return view('posts.index', [");
    expect($content)->toContain("'posts' => Post::latest()->paginate(10),");
    expect($content)->toContain('Post::create($validated);');
    expect($content)->toContain('$post->update($validated);');
    expect($content)->toContain('$post->delete();');
    expect($content)->not->toContain('posts::latest()');
    expect($content)->not->toContain('$this->posts->update');
});

it('generates valid form requests with correct unique rules', function () {
    $parser = new FieldParser;
    $parser->parse('title:string:unique|email:string:unique:nullable');

    $generator = new FormRequestGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(2);

    $storeContent = file_get_contents($paths[0]);
    $updateContent = file_get_contents($paths[1]);

    expect($storeContent)->toContain("Rule::unique('posts', 'title')");
    expect($updateContent)->toContain("Rule::unique('posts', 'title')->ignore(\$this->route('post'))");
    expect($storeContent)->toContain('use Illuminate\Validation\Rule;');
});

it('generates livewire components in correct location', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text');

    $generator = new LivewireComponentGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(4);

    foreach ($paths as $path) {
        expect(str_ends_with($path, '.php'))->toBeTrue();
        expect(str_contains($path, 'app/Livewire/Pages/Posts/'))->toBeTrue();
    }

    $indexContent = file_get_contents($paths[0]);
    expect($indexContent)->toContain('namespace App\Livewire\Pages\Posts');
    expect($indexContent)->toContain('class Index extends Component');
    expect($indexContent)->toContain('use WithPagination;');
    expect($indexContent)->toContain("protected array \$sortable = ['id', 'title', 'body'];");
    expect($indexContent)->toContain('if (! in_array($field, $this->sortable, true)) {');
    expect($indexContent)->toContain('$this->resetPage();');
    expect($indexContent)->toContain('->orderBy($this->getSortField(), $this->getSortDirection())');
    expect($indexContent)->toContain("public string \$inlineSuggestion = '';");
    expect($indexContent)->toContain('public function updatedSearch(): void');
    expect($indexContent)->not->toContain('#[\Livewire\Attributes\Computed]');
    expect($indexContent)->not->toContain('public function inlineSuggestion(): string');
});

it('generates livewire views without calling route at generation time', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text');

    $generator = new LivewireViewGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(4);

    foreach ($paths as $path) {
        expect(str_ends_with($path, '.blade.php'))->toBeTrue();
    }

    $indexContent = file_get_contents($paths[0]);
    expect($indexContent)->toContain("{{ route('posts.create') }}");
    expect($indexContent)->not->toContain('Illuminate\Routing\Exceptions\RouteNotFoundException');
});

it('generates livewire index view with centered padded layout', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text');

    $generator = new LivewireViewGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $indexContent = file_get_contents($paths[0]);

    expect($indexContent)->toContain('mx-auto max-w-7xl');
    expect($indexContent)->toContain('px-4 pt-4 pb-8 sm:px-6 lg:px-8');
    expect($indexContent)->toContain('overflow-x-auto');
});

it('generates livewire create and edit views with csrf tokens', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text');

    $generator = new LivewireViewGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    $editContent = file_get_contents($paths[2]);

    expect($createContent)->toContain('@csrf');
    expect($editContent)->toContain('@csrf');
});

it('generates model with soft deletes when enabled', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new ModelGenerator(new Filesystem, $parser, ['softDeletes' => true]);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('use Illuminate\Database\Eloquent\SoftDeletes;');
    expect($content)->toContain('use HasFactory, SoftDeletes;');
});

it('generates model without soft deletes when disabled', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new ModelGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('use HasFactory;');
    expect($content)->not->toContain('SoftDeletes');
    expect($content)->not->toContain('use ;');
});

it('generates migration with soft deletes when enabled', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new MigrationGenerator(new Filesystem, $parser, ['softDeletes' => true]);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('$table->softDeletes();');
});

it('generates pivot migration for belongsToMany relationships', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('tags:belongsToMany:Tag');

    $generator = new MigrationGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(2);

    $pivotPath = collect($paths)->first(fn ($path) => str_contains($path, '_create_post_tag_table.php'));
    expect($pivotPath)->not->toBeNull();

    $content = file_get_contents($pivotPath);
    expect($content)->toContain("Schema::create('post_tag'");
    expect($content)->toContain("\$table->foreignIdFor(\\App\\Models\\Post::class)->constrained('posts')->cascadeOnDelete();");
    expect($content)->toContain("\$table->foreignIdFor(\\App\\Models\\Tag::class)->constrained('tags')->cascadeOnDelete();");
    expect($content)->toContain("\$table->primary(['post_id', 'tag_id']);");
});

it('does not duplicate belongsTo foreign keys already declared as fields', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|user_id:foreign:users|category_id:foreign:categories');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User|category:belongsTo:Category');

    $generator = new MigrationGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);

    expect(substr_count($content, "\$table->foreignId('user_id')"))->toBe(1);
    expect(substr_count($content, "\$table->foreignId('category_id')"))->toBe(1);
    expect($content)->toContain("\$table->foreignId('user_id')->constrained('users');");
    expect($content)->toContain("\$table->foreignId('category_id')->constrained('categories');");
});

it('uses the related model table when creating belongsTo foreign keys', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('author:belongsTo:User:email');

    $generator = new MigrationGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);

    expect($content)->toContain("\$table->foreignId('author_id')->constrained('users')->cascadeOnDelete();");
    expect($content)->not->toContain('constrained()->cascadeOnDelete()');
});

it('generates livewire v4 compatible routes', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new RouteGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain("Route::get('/posts', \\App\\Livewire\\Pages\\Posts\\Index::class)->name('posts.index');");
    expect($content)->not->toContain('Route::livewire');
});

it('does not duplicate routes on subsequent runs', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new RouteGenerator(new Filesystem, $parser);
    $generator->generate('Post');
    $generator->generate('Post');

    $content = file_get_contents(base_path('routes/web.php'));
    expect(substr_count($content, "Route::get('/posts', \\App\\Livewire\\Pages\\Posts\\Index::class)"))->toBe(1);
});

it('generates model with relationship methods', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|user_id:foreign:users');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User|comments:hasMany:Comment');

    $generator = new ModelGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
    expect($content)->toContain('return $this->belongsTo(\App\Models\User::class);');
    expect($content)->toContain('public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany');
    expect($content)->toContain('return $this->hasMany(\App\Models\Comment::class);');
});

it('generates belongsTo relationship methods with custom foreign keys', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|user_id:foreign:users');

    $relParser = new RelationshipParser;
    $relParser->parse('author:belongsTo:User:email:user_id');

    $generator = new ModelGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);

    expect($content)->toContain("return \$this->belongsTo(\\App\\Models\\User::class, 'user_id');");
});

it('generates controller with eager loading when relationships exist', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User');

    $generator = new ControllerGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain("Post::query()->with(['user'])->latest()->paginate(10)");
});

it('generates controller without eager loading when no relationships exist', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new ControllerGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain("'posts' => Post::latest()->paginate(10),");
    expect($content)->not->toContain('->with(');
});

it('generates livewire index with eager loading when relationships exist', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User');

    $generator = new LivewireComponentGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $indexContent = file_get_contents($paths[0]);
    expect($indexContent)->toContain("->with(['user'])");
});

it('generates form requests with exists rules for foreign keys', function () {
    $parser = new FieldParser;
    $parser->parse('user_id:foreign:users');

    $generator = new FormRequestGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $storeContent = file_get_contents($paths[0]);
    expect($storeContent)->toContain("Rule::exists('users', 'id')");
});

it('generates form requests with belongsToMany array exists rules', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('tags:belongsToMany:Tag');

    $generator = new FormRequestGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $storeContent = file_get_contents($paths[0]);
    $updateContent = file_get_contents($paths[1]);

    expect($storeContent)->toContain("'selectedTagsIds' => ['nullable', 'array']");
    expect($storeContent)->toContain("'selectedTagsIds.*' => ['integer', Rule::exists('tags', 'id')]");
    expect($updateContent)->toContain("'selectedTagsIds' => ['sometimes', 'array']");
});

it('generates missing related model for belongsToMany relationships', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('tags:belongsToMany:Tag');

    $generator = new ModelGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(2);
    expect(file_exists(base_path('app/Models/Tag.php')))->toBeTrue();

    $content = file_get_contents(base_path('app/Models/Tag.php'));
    expect($content)->toContain('class Tag extends Model');
    expect($content)->toContain('use HasFactory;');
});

it('generates a factory with faker methods mapped to field types', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text|is_published:boolean|published_at:datetime|views:integer|price:decimal|email:email');

    $generator = new FactoryGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(1);
    expect(file_exists($paths[0]))->toBeTrue();

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('class PostFactory extends Factory');
    expect($content)->toContain('fake()->word()');
    expect($content)->toContain('fake()->paragraph()');
    expect($content)->toContain('fake()->boolean()');
    expect($content)->toContain('fake()->dateTime()');
    expect($content)->toContain('fake()->randomNumber()');
    expect($content)->toContain('fake()->randomFloat(2, 0, 1000)');
    expect($content)->toContain('fake()->safeEmail()');
    expect($content)->toContain('protected $model = Post::class;');
});

it('generates unique faker values for unique factory fields', function () {
    $parser = new FieldParser;
    $parser->parse('title:string:unique|slug:string:unique|email:email:unique');

    $generator = new FactoryGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);

    expect($content)->toContain("'title' => fake()->unique()->bothify('????-????-####'),");
    expect($content)->toContain("'slug' => fake()->unique()->slug(3),");
    expect($content)->toContain("'email' => fake()->unique()->safeEmail(),");
});

it('generates factory with related factory for foreign keys', function () {
    $parser = new FieldParser;
    $parser->parse('user_id:foreign:users');

    $generator = new FactoryGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('User::factory()');
});

it('generates a seeder', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new SeederGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(1);
    expect(file_exists($paths[0]))->toBeTrue();

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('class PostSeeder extends Seeder');
    expect($content)->toContain('Post::factory()->count(10)->create();');
});

it('generates form requests with mime type validation for media uploads', function () {
    $parser = new FieldParser;
    $parser->parse('photo:image|attachment:file|clip:video|gallery:image:multiple|docs:file:multiple|trailers:video:multiple');

    $generator = new FormRequestGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $storeContent = file_get_contents($paths[0]);

    expect($storeContent)->toContain("'mimes:jpeg,png,jpg,gif,webp,svg,avif'");
    expect($storeContent)->toContain("'mimes:pdf,doc,docx,txt,zip,xls,xlsx,csv,ppt,pptx'");
    expect($storeContent)->toContain("'mimes:mp4,mov,avi,webm,mkv'");
    expect($storeContent)->toContain("'gallery.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp,svg,avif', 'max:2048']");
    expect($storeContent)->toContain("'docs.*' => ['file', 'mimes:pdf,doc,docx,txt,zip,xls,xlsx,csv,ppt,pptx', 'max:2048']");
    expect($storeContent)->toContain("'trailers.*' => ['file', 'mimes:mp4,mov,avi,webm,mkv', 'max:10240']");
});

it('generates livewire edit component with file deletion logic', function () {
    $parser = new FieldParser;
    $parser->parse('photo:image|attachment:file');

    $generator = new LivewireComponentGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $editContent = file_get_contents($paths[2]);

    expect($editContent)->toContain('use Illuminate\Support\Facades\Storage;');
    expect($editContent)->toContain("Storage::disk('public')->delete(\$this->post->photo)");
    expect($editContent)->toContain("Storage::disk('public')->delete(\$this->post->attachment)");
});

it('generates livewire forms with custom belongsTo foreign keys', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|user_id:foreign:users');

    $relParser = new RelationshipParser;
    $relParser->parse('author:belongsTo:User:email:user_id');

    $componentGenerator = new LivewireComponentGenerator(new Filesystem, $parser, [], $relParser);
    $componentPaths = $componentGenerator->generate('Post');

    $viewGenerator = new LivewireViewGenerator(new Filesystem, $parser, ['force' => true], $relParser);
    $viewPaths = $viewGenerator->generate('Post');

    $createComponent = file_get_contents($componentPaths[1]);
    $editComponent = file_get_contents($componentPaths[2]);
    $createView = file_get_contents($viewPaths[1]);

    expect($createComponent)->toContain('public int $user_id;');
    expect($createComponent)->not->toContain('public int $author_id;');
    expect($editComponent)->toContain('$this->user_id = $post->user_id;');
    expect($editComponent)->not->toContain('$this->author_id');
    expect($createView)->toContain('wire:model="user_id"');
    expect($createView)->not->toContain('wire:model="author_id"');
    expect(substr_count($createView, 'wire:model="user_id"'))->toBe(1);
});

it('generates livewire edit component with multiple file removal methods', function () {
    $parser = new FieldParser;
    $parser->parse('gallery:image:multiple|docs:file:multiple');

    $generator = new LivewireComponentGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $editContent = file_get_contents($paths[2]);

    expect($editContent)->toContain('public array $galleryToRemove = []');
    expect($editContent)->toContain('public array $docsToRemove = []');
    expect($editContent)->toContain('public function removeGalleryFile(string $path): void');
    expect($editContent)->toContain('public function removeDocsFile(string $path): void');
    expect($editContent)->toContain("Storage::disk('public')->delete(\$path)");
    expect($editContent)->toContain('use Illuminate\Support\Facades\Storage;');
});

it('generates livewire edit view with remove buttons for multiple files', function () {
    $parser = new FieldParser;
    $parser->parse('gallery:image:multiple');

    $generator = new LivewireViewGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $editContent = file_get_contents($paths[2]);

    expect($editContent)->toContain('wire:click="removeGalleryFile');
    expect($editContent)->toContain('@unless(in_array($path, $galleryToRemove))');
});

it('limits relationship options to prevent memory issues', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User');

    $generator = new LivewireComponentGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    expect($createContent)->toContain('::limit(100)->get()');
    expect($createContent)->not->toContain('::all()');
});

it('generates volt livewire components with all placeholders replaced', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(4);

    foreach ($paths as $path) {
        expect(str_ends_with($path, '.blade.php'))->toBeTrue();
        expect(str_contains($path, 'resources/views/pages/'))->toBeTrue();
    }

    $indexContent = file_get_contents($paths[0]);
    expect($indexContent)->not->toContain('{{ viewPath }}');
    expect($indexContent)->not->toContain('{{ titleSingular }}');
    expect($indexContent)->toContain('view(\'pages.posts.index\'');

    $createContent = file_get_contents($paths[1]);
    expect($createContent)->not->toContain('{{ viewPath }}');
    expect($createContent)->toContain('view(\'pages.posts.create\'');

    $showContent = file_get_contents($paths[3]);
    expect($showContent)->not->toContain('{{ pluralTitle }}');
    expect($showContent)->not->toContain('{{ showFields }}');
    expect($showContent)->not->toContain('{{ editRoute }}');
    expect($showContent)->not->toContain('{{ titleSingular }}');
    expect($showContent)->toContain('posts.edit');
});

it('generates volt show view with correct details structure', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text|is_published:boolean');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $showContent = file_get_contents($paths[3]);
    expect($showContent)->toContain('<flux:subheading>Title</flux:subheading>');
    expect($showContent)->toContain('{{ $post->title }}');
    expect($showContent)->toContain('<flux:subheading>Body</flux:subheading>');
    expect($showContent)->toContain('{{ $post->body }}');
    expect($showContent)->not->toContain('{{ details }}');
    expect($showContent)->not->toContain('{{ pluralTitle }}');
});

it('generates volt index view with correct eager loading clause', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $indexContent = file_get_contents($paths[0]);
    expect($indexContent)->toContain("->with(['user'])");
});

it('generates volt create with file upload support', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|photo:image|attachment:file|clip:video');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    expect($paths)->toHaveCount(4);

    $createContent = file_get_contents($paths[1]);
    expect($createContent)->toContain('use Livewire\WithFileUploads;');
    expect($createContent)->toContain('use WithFileUploads;');
    expect($createContent)->toContain('public $photo;');
    expect($createContent)->toContain('public $attachment;');
    expect($createContent)->toContain('public $clip;');
    expect($createContent)->toContain('type="file"');
    expect($createContent)->toContain('accept="image/*"');
    expect($createContent)->toContain('accept="video/*"');
    expect($createContent)->toContain("->store('posts', 'public')");
    expect($createContent)->not->toContain('{{ uses }}');
    expect($createContent)->not->toContain('{{ traits }}');
    expect($createContent)->not->toContain('{{ properties }}');
});

it('generates volt edit with file deletion and removal methods', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|gallery:image:multiple|docs:file:multiple');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $editContent = file_get_contents($paths[2]);
    expect($editContent)->toContain('use Livewire\WithFileUploads;');
    expect($editContent)->toContain('use Illuminate\Support\Facades\Storage;');
    expect($editContent)->toContain('use WithFileUploads;');
    expect($editContent)->toContain('public $gallery = [];');
    expect($editContent)->toContain('public array $galleryToRemove = [];');
    expect($editContent)->toContain('public $docs = [];');
    expect($editContent)->toContain('public array $docsToRemove = [];');
    expect($editContent)->toContain('public function removeGalleryFile(string $path): void');
    expect($editContent)->toContain('public function removeDocsFile(string $path): void');
    expect($editContent)->toContain("Storage::disk('public')->delete(\$path)");
    expect($editContent)->not->toContain('{{ fileStorage }}');
    expect($editContent)->not->toContain('{{ extraMethods }}');
});

it('generates volt edit storage import for single media fields', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|photo:image');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $editContent = file_get_contents($paths[2]);

    expect($editContent)->toContain('use Illuminate\Support\Facades\Storage;');
    expect($editContent)->toContain("Storage::disk('public')->delete(\$this->post->photo)");
});

it('generates volt create with belongsTo relationship support', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    expect($createContent)->toContain('public int $user_id = 0;');
    expect($createContent)->toContain('public $userOptions = [];');
    expect($createContent)->toContain('$this->userOptions = \App\Models\User::limit(100)->get();');
});

it('generates volt forms with custom belongsTo foreign keys', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|user_id:foreign:users');

    $relParser = new RelationshipParser;
    $relParser->parse('author:belongsTo:User:email:user_id');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    $editContent = file_get_contents($paths[2]);

    expect($createContent)->toContain('public int $user_id = 0;');
    expect($createContent)->not->toContain('public int $author_id');
    expect($createContent)->toContain('wire:model="user_id"');
    expect($editContent)->toContain('$this->user_id = $post->user_id;');
    expect($editContent)->not->toContain('$this->author_id');
    expect($editContent)->toContain('wire:model="user_id"');
});

it('generates volt edit with syncRelationships for belongsToMany', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('tags:belongsToMany:Tag');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    $editContent = file_get_contents($paths[2]);

    expect($createContent)->toContain('public array $selectedTagsIds = [];');
    expect($createContent)->toContain('public $tagsOptions = [];');
    expect($editContent)->toContain('$this->post->tags()->sync($this->selectedTagsIds);');
    expect($editContent)->toContain('public array $selectedTagsIds = [];');
    expect($editContent)->toContain('public $tagsOptions = [];');
    expect($editContent)->toContain('$this->tagsOptions = \App\Models\Tag::limit(100)->get();');
    expect($editContent)->toContain('$this->selectedTagsIds = $post->tags->pluck(\'id\')->toArray();');
});

it('generates volt index with search and pagination', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|body:text|email:email');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $indexContent = file_get_contents($paths[0]);
    expect($indexContent)->toContain('public string $search = \'\';');
    expect($indexContent)->toContain('public string $sortField = \'id\';');
    expect($indexContent)->toContain('public string $sortDirection = \'desc\';');
    expect($indexContent)->toContain('public int $perPage = 10;');
    expect($indexContent)->toContain("protected array \$sortable = ['id', 'title', 'body', 'email'];");
    expect($indexContent)->toContain('wire:model.live.debounce.300ms="search"');
    expect($indexContent)->toContain('public function updatingPerPage(): void');
    expect($indexContent)->toContain('if (! in_array($field, $this->sortable, true)) {');
    expect($indexContent)->toContain('->orderBy($this->getSortField(), $this->getSortDirection())');
    expect($indexContent)->toContain('$this->resetPage();');
    expect($indexContent)->toContain('$q->orWhere(\'title\', \'like\', \'%\' . $this->search . \'%\')');
    expect($indexContent)->toContain('$q->orWhere(\'body\', \'like\', \'%\' . $this->search . \'%\')');
    expect($indexContent)->toContain('$q->orWhere(\'email\', \'like\', \'%\' . $this->search . \'%\')');
    expect($indexContent)->toContain('mx-auto max-w-7xl');
    expect($indexContent)->toContain('px-4 pt-4 pb-8 sm:px-6 lg:px-8');
    expect($indexContent)->toContain('overflow-x-auto');
    expect($indexContent)->toContain('@if($posts->hasPages())');
    expect($indexContent)->toContain('{{ $posts->links() }}');
    expect($indexContent)->not->toContain('{{ with }}');
});

it('resets pagination after delete in generated index components', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $livewireGenerator = new LivewireComponentGenerator(new Filesystem, $parser);
    $livewirePaths = $livewireGenerator->generate('Post');
    $livewireIndex = file_get_contents($livewirePaths[0]);

    $voltGenerator = new VoltLivewireGenerator(new Filesystem, $parser);
    $voltPaths = $voltGenerator->generate('Post');
    $voltIndex = file_get_contents($voltPaths[0]);

    expect($livewireIndex)->toContain('public function delete(int $id): void');
    expect($livewireIndex)->toContain('$this->resetPage();');
    expect($voltIndex)->toContain('public function delete(int $id): void');
    expect($voltIndex)->toContain('$this->resetPage();');
});

it('generates volt edit and show redirects using named routes', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $editContent = file_get_contents($paths[2]);
    $showContent = file_get_contents($paths[3]);

    expect($editContent)->toContain('$this->post = $post;');
    expect($editContent)->toContain('$this->redirectRoute(\'posts.index\');');
    expect($showContent)->toContain('$this->redirectRoute(\'posts.index\');');
    expect($editContent)->not->toContain('/posts.index');
    expect($showContent)->not->toContain('/posts.index');
    expect($showContent)->toContain('posts.edit');
    expect($showContent)->toContain('<flux:button href="{{ route(\'posts.edit\', $post) }}" variant="primary">Edit</flux:button>');
});

it('generates volt relationship form fields', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User|tags:belongsToMany:Tag');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    $editContent = file_get_contents($paths[2]);

    expect($createContent)->toContain('<flux:select wire:model="user_id" label="User">');
    expect($createContent)->toContain('@foreach($userOptions as $option)');
    expect($createContent)->toContain('wire:model="selectedTagsIds"');
    expect($createContent)->not->toContain('<input type="text" wire:model="user_id" />');
    expect($editContent)->toContain('<flux:select wire:model="user_id" label="User">');
    expect($editContent)->toContain('wire:model="selectedTagsIds"');
    expect($editContent)->not->toContain('<input type="text" wire:model="user_id" />');
});

it('generates volt multi-media validation as arrays', function () {
    $parser = new FieldParser;
    $parser->parse('gallery:image:multiple|docs:file:multiple|trailers:video:multiple');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $createContent = file_get_contents($paths[1]);
    $editContent = file_get_contents($paths[2]);

    expect($createContent)->toContain("#[Validate('required|array')]");
    expect($createContent)->toContain("#[Validate('required|array')]\n    public \$docs = [];");
    expect($createContent)->toContain("#[Validate('required|array')]\n    public \$trailers = [];");
    expect($editContent)->toContain("#[Validate('sometimes|array')]");
    expect($editContent)->not->toContain('required|image');
    expect($editContent)->not->toContain('nullable|file');
});

it('discovers volt routes with singular model binding parameters', function () {
    mkdir($this->tmpDir.'/resources/views/pages/posts', 0755, true);

    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser);
    $generator->generate('Post');

    Route::macro('livewire', function (string $uri, string $component) {
        return Route::get($uri, fn () => $component);
    });

    $provider = $this->app->getProvider(CrudifyServiceProvider::class);
    $method = new ReflectionMethod($provider, 'discoverVoltRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(route('posts.show', ['post' => 1], false))->toBe('/posts/1/show');
    expect(route('posts.edit', ['post' => 1], false))->toBe('/posts/1/edit');
});

it('generates volt show with media and relationship aware rendering', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|user_id:foreign:users|gallery:image:multiple|manual:file|clip:video');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User|tags:belongsToMany:Tag');

    $generator = new VoltLivewireGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $showContent = file_get_contents($paths[3]);

    expect($showContent)->toContain('@foreach(is_array($post->gallery) ? $post->gallery : json_decode($post->gallery, true) ?? [] as $path)');
    expect($showContent)->toContain("asset('storage/' . \$path)");
    expect($showContent)->toContain("asset('storage/' . \$post->manual)");
    expect($showContent)->toContain("asset('storage/' . \$post->clip)");
    expect($showContent)->toContain('<video src="{{ asset(\'storage/\' . $post->clip) }}"');
    expect($showContent)->toContain('{{ $post->user->name ?? $post->user->id }}');
    expect($showContent)->toContain('@foreach($post->tags as $item)');
    expect($showContent)->not->toContain('{{ $post->gallery }}');
    expect($showContent)->not->toContain('{{ $post->user_id }}');
});

it('generates livewire views with video upload and preview support', function () {
    $parser = new FieldParser;
    $parser->parse('title:string|clip:video|trailers:video:multiple');

    $generator = new LivewireViewGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $indexContent = file_get_contents($paths[0]);
    $createContent = file_get_contents($paths[1]);
    $editContent = file_get_contents($paths[2]);
    $showContent = file_get_contents($paths[3]);

    expect($indexContent)->toContain('<flux:table.column>Media</flux:table.column>');
    expect($indexContent)->toContain('<video src="{{ asset(\'storage/\' . $post->clip) }}"');
    expect($createContent)->toContain('accept="video/*"');
    expect($editContent)->toContain('accept="video/*"');
    expect($editContent)->toContain('<video src="{{ asset(\'storage/\' . $post->clip) }}"');
    expect($editContent)->toContain('<video src="{{ asset(\'storage/\' . $path) }}"');
    expect($showContent)->toContain('<video src="{{ asset(\'storage/\' . $post->clip) }}"');
    expect($showContent)->toContain('<video src="{{ asset(\'storage/\' . $path) }}"');
});
