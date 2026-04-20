<?php

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
use Crudify\RelationshipParser;
use Illuminate\Filesystem\Filesystem;

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
    $parser->parse('title:string,body:text');

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
    $parser->parse('title:string:unique,email:string:unique:nullable');

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
    $parser->parse('title:string,body:text');

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
});

it('generates livewire views without calling route at generation time', function () {
    $parser = new FieldParser;
    $parser->parse('title:string,body:text');

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

it('generates livewire v4 compatible routes', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new RouteGenerator(new Filesystem, $parser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain("Route::get('/posts', Index::class)->name('posts.index');");
    expect($content)->toContain("use App\Livewire\Pages\Posts\Index;");
    expect($content)->not->toContain('Route::livewire');
});

it('does not duplicate routes on subsequent runs', function () {
    $parser = new FieldParser;
    $parser->parse('title:string');

    $generator = new RouteGenerator(new Filesystem, $parser);
    $generator->generate('Post');
    $generator->generate('Post');

    $content = file_get_contents(base_path('routes/web.php'));
    expect(substr_count($content, "Route::get('/posts', Index::class)"))->toBe(1);
});

it('generates model with relationship methods', function () {
    $parser = new FieldParser;
    $parser->parse('title:string,user_id:foreign:users');

    $relParser = new RelationshipParser;
    $relParser->parse('user:belongsTo:User,comments:hasMany:Comment');

    $generator = new ModelGenerator(new Filesystem, $parser, [], $relParser);
    $paths = $generator->generate('Post');

    $content = file_get_contents($paths[0]);
    expect($content)->toContain('public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo');
    expect($content)->toContain('return $this->belongsTo(\App\Models\User::class);');
    expect($content)->toContain('public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany');
    expect($content)->toContain('return $this->hasMany(\App\Models\Comment::class);');
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

it('generates a factory with faker methods mapped to field types', function () {
    $parser = new FieldParser;
    $parser->parse('title:string,body:text,is_published:boolean,published_at:datetime,views:integer,price:decimal,email:email');

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
