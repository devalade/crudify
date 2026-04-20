# Crudify

Generate full CRUD scaffolding for Laravel with a single command. Built for **Livewire v4** and **Laravel 11/12**.

Crudify creates models, migrations, controllers, form requests, policies, Livewire components + views, routes, factories, and seeders — with support for relationships, soft deletes, and searchable fields.

---

## Installation

```bash
composer require devalade/crudify
```

---

## Quick Start

### CLI

```bash
php artisan crudify:generate Post \
  --fields="title:string,body:text,is_published:boolean,published_at:datetime" \
  --soft-delete
```

### YAML

```bash
php artisan crudify:generate --file=post.yaml
```

**`post.yaml`**

```yaml
model: Post
fields:
  title: string
  slug: string:unique
  body: text
  is_published: boolean:default:false
  published_at: datetime:nullable
  category_id: foreign:categories
options:
  soft_deletes: true
relationships:
  category:
    type: belongsTo
    model: Category
  tags:
    type: belongsToMany
    model: Tag
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

### Available Types

`string`, `text`, `integer`, `bigint`, `float`, `double`, `decimal`, `boolean`, `date`, `datetime`, `timestamp`, `time`, `json`, `uuid`, `email`, `foreign`

### Modifiers

- `nullable` — allows NULL values
- `unique` — adds unique constraint
- `index` — adds database index
- `default:value` — sets default value
- `foreign:table` — creates foreign key constraint

---

## Relationships

Define Eloquent relationships in your model with a simple syntax.

### CLI

```bash
php artisan crudify:generate Post \
  --fields="title:string,user_id:foreign:users" \
  --relationships="user:belongsTo:User,comments:hasMany:Comment"
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

- `belongsTo`
- `hasMany`
- `hasOne`
- `belongsToMany`

Relationships are automatically:
- Added to the model with proper return types
- Eager-loaded in controllers and Livewire index components
- Validated with `Rule::exists()` for foreign key fields in form requests

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
| `app/Livewire/Pages/Posts/Create.php` | Livewire create component |
| `app/Livewire/Pages/Posts/Edit.php` | Livewire edit component |
| `app/Livewire/Pages/Posts/Show.php` | Livewire show component |
| `resources/views/livewire/pages/posts/*.blade.php` | Tailwind-styled views |
| `routes/web.php` | Livewire v4 routes appended with collision detection |

---

## Command Options

```bash
php artisan crudify:generate {model}
  --fields=          # Comma-separated field definitions
  --file=            # Path to YAML file (overrides --fields)
  --relationships=   # Comma-separated relationships (name:type:Model)
  --only=            # Generate only specific types (comma-separated)
  --skip=            # Skip specific types (comma-separated)
  --soft-delete      # Add soft deletes
  --searchable=      # Comma-separated searchable fields
  --force            # Overwrite existing files
  --dry-run          # Preview without writing files
```

### Examples

**Generate only model and migration:**

```bash
php artisan crudify:generate Post --fields="title:string" --only=model,migration
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
