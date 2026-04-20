# Crudify

Generate full CRUD with Livewire v4 components in Laravel.

## Features

- ✅ Model with fillable and casts
- ✅ Database migration with proper types
- ✅ Resource controller (7 methods)
- ✅ Form Request validation (Store/Update)
- ✅ Policy authorization
- ✅ Livewire v4 single-file components (index, create, edit, show)
- ✅ Automatic route registration
- ✅ Search, sorting, and pagination on index
- ✅ Customizable stubs

## Installation

```bash
composer require crudify/crudify
```

## Usage

### CLI Mode

```bash
php artisan crudify:generate Post --fields="title:string,body:text,is_published:boolean,published_at:datetime"
```

### YAML Mode

Create a YAML file (e.g., `post.yaml`):

```yaml
model: Post

fields:
  title:
    type: string
    nullable: false
    unique: true
  body:
    type: text
    nullable: false
  is_published:
    type: boolean
    default: false

searchable:
  - title
  - body
```

Then run:

```bash
php artisan crudify:generate --file=post.yaml
```

### Field Types

Supported types: `string`, `text`, `integer`, `bigint`, `float`, `boolean`, `date`, `datetime`, `json`, `uuid`, `foreign`

### Field Modifiers

- `nullable` - Column allows NULL
- `unique` - Add unique constraint
- `index` - Add index
- `default:value` - Set default value
- `foreign:table` - Foreign key to specified table

### CLI Examples

```bash
# Simple blog post
php artisan crudify:generate Post --fields="title:string,body:text,is_published:boolean"

# Product with foreign key
php artisan crudify:generate Product --fields="name:string,price:float,description:text,category_id:foreign:categories"

# User profile with nullable fields
php artisan crudify:generate Profile --fields="bio:text,avatar:string:nullable,website:string:nullable"

# Generate only model and migration
php artisan crudify:generate Post --fields="title:string" --only=model,migration

# Skip policy generation
php artisan crudify:generate Post --fields="title:string" --skip=policy
```

### YAML Examples

```bash
# Using YAML file
php artisan crudify:generate --file=post.yaml

# YAML with only option
php artisan crudify:generate --file=post.yaml --only=model,migration
```

See `YAML_EXAMPLE.md` for complete YAML syntax reference.

## Generated Files

For `Post` model, generates:

```
app/
├── Models/
│   └── Post.php
├── Http/
│   ├── Controllers/
│   │   └── PostsController.php
│   └── Requests/
│       ├── StorePostRequest.php
│       └── UpdatePostRequest.php
├── Policies/
│   └── PostPolicy.php
└── Livewire/
    └── Pages/
        └── posts/
            ├── ⚡index.blade.php
            ├── ⚡create.blade.php
            ├── ⚡edit.blade.php
            └── ⚡show.blade.php

database/migrations/
└── xxxx_xx_xx_xxxxxx_create_posts_table.php

resources/views/livewire/pages/posts/
├── index.blade.php
├── create.blade.php
├── edit.blade.php
└── show.blade.php

routes/web.php (auto-appended)
```

## Livewire Components

### Index Component
- Search functionality
- Column sorting (click headers)
- Pagination (10, 25, 50, 100 per page)
- Responsive table layout

### Create/Edit Components
- Auto-generated form fields
- Validation with error display
- Redirect on success

### Show Component
- Display all fields
- Edit button
- Delete with confirmation

## Customizing Stubs

Publish stubs to customize the generated code:

```bash
php artisan crudify:stubs
```

This creates `stubs/crudify/` with all stub files. Modifications here override package defaults.

## Requirements

- PHP ^8.2
- Laravel ^11.0|^12.0
- Livewire ^4.0
- Symfony YAML ^6.0|^7.0 (included)

## Layout Requirement

Ensure you have a layout file at `resources/views/components/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @livewireStyles
</head>
<body>
    {{ $slot }}
    @livewireScripts
</body>
</html>
```

## License

MIT
