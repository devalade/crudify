# Crudify - YAML Definition Example

## Livewire v4 Features

Generated components use Livewire v4 single-file convention (`⚡component.blade.php`) with:
- `#[Layout('layouts::app')]` attribute for layout
- `#[Title('...')]` attribute for page title
- Type-safe properties with proper type hints
- Void return types for action methods

## Basic Example

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

searchable:
  - title
  - body

options:
  soft_deletes: false
```

## Usage

```bash
php artisan crudify:generate --file=crud.yaml
```

## Many-to-Many Example

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

This generates:
- `app/Models/Tag.php` if missing
- pivot migration like `*_create_post_tag_table.php`
- checkbox UI in Livewire create/edit views
- tag badges in index/show views
- form request rules for `selectedTagsIds` and `selectedTagsIds.*`

## Field Types

- `string` - VARCHAR column
- `text` - TEXT column
- `integer` - INTEGER column
- `bigint` - BIGINT column
- `float` - FLOAT column
- `boolean` - BOOLEAN column
- `date` - DATE column
- `datetime` - DATETIME column
- `timestamp` - TIMESTAMP column
- `json` - JSON column
- `uuid` - UUID column
- `foreign` - Foreign key (requires `foreign:` key)

## Field Options

```yaml
fields:
  name:
    type: string
    nullable: true      # Allow NULL
    unique: true        # Add unique constraint
    index: true         # Add index
    default: 'value'    # Default value
    foreign: users      # Foreign key to 'users' table
```

## Complete Example with Foreign Keys

```yaml
model: Product

fields:
  name:
    type: string
    nullable: false
    unique: true
  slug:
    type: string
    nullable: false
    unique: true
    index: true
  description:
    type: text
    nullable: true
  price:
    type: float
    nullable: false
    default: 0
  stock:
    type: integer
    nullable: false
    default: 0
  is_active:
    type: boolean
    default: true
  category_id:
    type: foreign
    foreign: categories
    nullable: false
  brand_id:
    type: foreign
    foreign: brands
    nullable: true

searchable:
  - name
  - description

options:
  soft_deletes: true
```

## Shorthand Syntax

For simple fields, you can use shorthand:

```yaml
model: Post

fields:
  title: string
  body: text
  is_published: boolean
  published_at: datetime
```

This is equivalent to:

```yaml
model: Post

fields:
  title:
    type: string
  body:
    type: text
  is_published:
    type: boolean
  published_at:
    type: datetime
```
