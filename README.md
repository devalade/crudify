# Crudify

[![Tests](https://github.com/devalade/crudify/actions/workflows/tests.yml/badge.svg)](https://github.com/devalade/crudify/actions/workflows/tests.yml)

Generate full CRUD scaffolding for Laravel with a single command. Built for **Livewire v4** and **Laravel 11/12/13**.

Crudify creates models, migrations, controllers, form requests, policies, Livewire components + views, routes, factories, and seeders — with support for relationships, soft deletes, file uploads, and searchable fields.

---

## Installation

```bash
composer require devalade/crudify --dev
```

---

## Quick Start

### CLI

```bash
php artisan crudify:generate Post \
  --fields="title:string|body:text|is_published:boolean|published_at:datetime" \
  --relationships="user:belongsTo:User|category:belongsTo:Category|tags:belongsToMany:Tag" \
  --soft-delete
```

### Volt (Single-File Components)

Generate Livewire v4 single-file components (SFCs):

```bash
php artisan crudify:generate Post \
  --fields="title:string|body:text|is_published:boolean" \
  --volt
```

This generates SFCs directly in `resources/views/pages/posts/`:

- `index.blade.php` — list with search, sort, pagination
- `create.blade.php` — inline validation with `#[Validate]`
- `edit.blade.php` — edit form with file upload support
- `show.blade.php` — detail view

Routes are auto-discovered and registered by Crudify's service provider.

### YAML

```bash
php artisan crudify:generate --file=post.yaml
```

**`post.yaml`**

```yaml
model: Post

fields:
  title:
    type: string
    nullable: false
    unique: true

  slug:
    type: string
    nullable: false
    unique: true
    index: true

  body:
    type: text
    nullable: false

  is_published:
    type: boolean
    default: false

  published_at:
    type: datetime
    nullable: true

  # Single image upload
  featured_image:
    type: image
    nullable: true

  # Multiple file uploads
  gallery:
    type: image
    multiple: true
    nullable: true

  # Single file upload
  attachment:
    type: file
    nullable: true

relationships:
  user:
    type: belongsTo
    model: User

  category:
    type: belongsTo
    model: Category

  tags:
    type: belongsToMany
    model: Tag

  comments:
    type: hasMany
    model: Comment

searchable:
  - title
  - body

options:
  soft_deletes: true
```

Then run:

```bash
php artisan migrate
```

Visit `/{resource}` (e.g. `/posts`).

---

## Field Syntax

### CLI Format

```
name:type:modifier1:modifier2
```

**Examples:**

| Field Definition | Result |
|---|---|
| `title:string` | `string` column |
| `body:text` | `text` column |
| `email:string:unique` | `string` with unique index |
| `published_at:datetime:nullable` | nullable `datetime` |
| `status:string:default:draft` | `string` with default value |
| `user_id:foreign:users` | `foreignId` constrained to `users` table |
| `views:integer:index` | `integer` with index |
| `photo:image` | `string` column + file upload input |
| `documents:file:multiple` | `json` column + multiple file upload input |

### Available Types

`string`, `text`, `integer`, `bigint`, `float`, `double`, `decimal`, `boolean`, `date`, `datetime`, `timestamp`, `time`, `json`, `uuid`, `email`, `foreign`, `image`, `file`

### Modifiers

- `nullable` — allows NULL values
- `unique` — adds unique constraint
- `index` — adds database index
- `default:value` — sets default value
- `foreign:table` — creates foreign key constraint
- `multiple` — enables multiple file uploads (for `image` and `file` types)

---

## File Uploads

Crudify supports single and multiple file/image uploads out of the box — for both standard Livewire components and Volt SFCs.

### Single Upload

```bash
php artisan crudify:generate Product \
  --fields="name:string|price:decimal|photo:image" \
  --volt
```

Generates:
- `string` column for the file path
- `WithFileUploads` trait in Volt components
- File input with `accept="image/*"`
- Automatic file storage to `storage/app/public/`
- Old file deletion on edit

### Multiple Uploads

```bash
php artisan crudify:generate Gallery \
  --fields="title:string|photos:image:multiple" \
  --volt
```

Generates:
- `json` column for storing multiple paths
- Array-cast in the model
- Multiple file input (`<input type="file" multiple>`)
- `removePhotosFile()` method for selective removal
- Gallery preview grid on edit/show views

---

## Relationships

Define Eloquent relationships in your model with a simple syntax.

### CLI

```bash
php artisan crudify:generate Post \
  --fields="title:string|user_id:foreign:users" \
  --relationships="user:belongsTo:User|category:belongsTo:Category|tags:belongsToMany:Tag|comments:hasMany:Comment"
```

### YAML

```yaml
relationships:
  author:
    type: belongsTo
    model: User
  comments:
    type: hasMany
    model: Comment
  profile:
    type: hasOne
    model: Profile
  tags:
    type: belongsToMany
    model: Tag
```

### Supported Types

- `belongsTo` — generates dropdown in forms, eager-loaded in index
- `hasMany` — generates model method only
- `hasOne` — generates model method only
- `belongsToMany` — generates checkbox group in forms, syncs on save

Relationships are automatically:
- Added to the model with proper return types
- Eager-loaded in controllers and Livewire index components
- Validated with `Rule::exists()` for foreign key fields in form requests
- Displayed in index tables and show views
- For `belongsToMany`, generates pivot migration and missing related model when needed

### belongsToMany Example

For a `tags:belongsToMany:Tag` relationship, the generator produces:

**YAML:**
```yaml
model: Post

fields:
  title: string
  body: text

relationships:
  tags:
    type: belongsToMany
    model: Tag
```

Run:
```bash
php artisan crudify:generate --file=post.yaml
```

Generated artifacts:
- `app/Models/Post.php`
- `app/Models/Tag.php` if missing
- `database/migrations/*_create_posts_table.php`
- `database/migrations/*_create_post_tag_table.php`
- Livewire create/edit checkbox UI
- index badges for related tags
- form request validation for `selectedTagsIds` and `selectedTagsIds.*`

**Create/Edit Forms:**
- Checkbox group for selecting tags
- `selectedTagsIds` array property with `#[Validate]` attribute

**Save/Update:**
```php
$post = Post::create($validated);
$post->tags()->sync($this->selectedTagsIds);
```

**Factory:**
```php
public function configure(): static
{
    return $this->afterCreating(function (Post $model) {
        $model->tags()->attach(Tag::inRandomOrder()->take(rand(1, 3))->pluck('id'));
    });
}
```

---

## Generated Files

For a model named `Post`, Crudify generates:

| File | Description |
|---|---|
| `app/Models/Post.php` | Eloquent model with `$fillable`, `$casts`, traits, and relationships |
| `database/factories/PostFactory.php` | Factory with Faker methods mapped to field types |
| `database/seeders/PostSeeder.php` | Seeder calling `Post::factory()->count(10)->create()` |
| `database/migrations/xxxx_create_posts_table.php` | Migration with all columns and indexes |
| `app/Http/Controllers/PostsController.php` | Resource controller with CRUD actions |
| `app/Http/Requests/StorePostRequest.php` | Form request with validation rules |
| `app/Http/Requests/UpdatePostRequest.php` | Form request with unique rule ignoring current model |
| `app/Policies/PostPolicy.php` | Authorization policy |
| `app/Livewire/Pages/Posts/Index.php` | Livewire component with search, sort, pagination |
| `app/Livewire/Pages/Posts/Create.php` | Livewire create component with `#[Validate]` |
| `app/Livewire/Pages/Posts/Edit.php` | Livewire edit component with `#[Validate]` |
| `app/Livewire/Pages/Posts/Show.php` | Livewire show component |
| `resources/views/livewire/pages/posts/*.blade.php` | Flux UI + Tailwind styled Blade views |
| `routes/web.php` | Livewire v4 routes with fully-qualified class names |

### Livewire 4 Compatible

All generated components use Livewire 4 syntax:
- `#[Validate]` attributes on properties (fixes `MissingRulesException`)
- `#[Layout]` and `#[Title]` attributes
- `WithFileUploads` trait for file handling
- `wire:confirm` for delete confirmations

---

## UI Framework

Generated views use **Flux UI components** with **Tailwind CSS**. Generated Blade files include `flux:*` components plus Tailwind utility classes:

```html
<flux:card>
  <flux:input wire:model.live="search" />
  <flux:table>...</flux:table>
</flux:card>
```

Install Flux in generated app:

```html
composer require livewire/flux

@fluxAppearance
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxScripts
```

Bootstrap setup files with:

```bash
php artisan crudify:setup
```

This command:
- creates `resources/css/app.css` with Tailwind + Flux imports
- creates `resources/js/app.js`
- creates or patches common app layouts with `@fluxAppearance`, `@vite(...)`, `@livewireScripts`, and `@fluxScripts`
- patches Vite config to add `@tailwindcss/vite` when a Vite config file exists

Install and bootstrap everything automatically:

```bash
php artisan crudify:install
php artisan crudify:install --volt
```

`crudify:install`:
- installs `livewire/flux`
- installs `livewire/volt` when `--volt` used
- installs `tailwindcss` and `@tailwindcss/vite` via npm when `package.json` exists
- runs `crudify:setup`

---

## Command Options

```bash
php artisan crudify:generate {model}
  --fields=          # Field definitions, separated by `|` or `;`
  --file=            # Path to YAML file (overrides --fields)
  --relationships=   # Relationships, separated by `|` or `;`
  --only=            # Generate only specific types (`model|migration` etc.)
  --skip=            # Skip specific types (`controller;policy` etc.)
  --soft-delete      # Add soft deletes
  --searchable=      # Comma-separated searchable fields
  --volt             # Generate Livewire v4 single-file components
  --force            # Overwrite existing files
  --dry-run          # Preview without writing files
```

### Examples

**Generate with file uploads:**

```bash
php artisan crudify:generate Product \
  --fields="name:string|price:decimal|photo:image"
```

**Generate with relationships:**

```bash
php artisan crudify:generate Post \
  --fields="title:string|body:text" \
  --relationships="user:belongsTo:User|tags:belongsToMany:Tag"
```

**Generate only model and migration:**

```bash
php artisan crudify:generate Post --fields="title:string" --only=model|migration
```

**Skip controllers (Livewire-only):**

```bash
php artisan crudify:generate Post --fields="title:string" --skip=controller
```

**Preview changes:**

```bash
php artisan crudify:generate Post --fields="title:string" --dry-run
```

---

## Customizing Stubs

Publish stubs to your application:

```bash
php artisan crudify:stubs
```

Then edit files in `stubs/crudify/`. The package will use your custom stubs on the next generation.

---

## Requirements

- PHP ^8.2
- Laravel ^11.0, ^12.0 or ^13.0
- Livewire ^4.0

---

## Testing

```bash
composer test          # Run all tests
composer test:unit     # Run unit tests only
composer test:feature  # Run feature tests only
composer analyse       # Run static analysis
composer format        # Fix code style
```

---

## License

MIT
