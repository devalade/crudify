# Crudify YAML Example

Use YAML when you want one file to define model fields, relationships, and generator options.

## Usage

```bash
php artisan crudify:generate --file=post.yaml
```

Crudify generates Volt single-file page components by default. To opt out and generate classic Livewire pages, set `options.volt: false`.

## Full Example

```yaml
model: Post

fields:
  title:
    type: string
    unique: true

  slug:
    type: string
    unique: true
    index: true

  body:
    type: text

  excerpt:
    type: text
    nullable: true

  is_published:
    type: boolean
    default: false

  published_at:
    type: datetime
    nullable: true

  view_count:
    type: integer
    default: 0

  rating:
    type: decimal
    nullable: true

  featured_image:
    type: image
    nullable: true

  attachment:
    type: file
    nullable: true

  gallery:
    type: image
    multiple: true
    nullable: true

  documents:
    type: file
    multiple: true
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
  volt: false
```

## What This Generates

- model, migration, factory, seeder
- policy
- Volt index/create/edit/show pages
- Flux UI pages with Tailwind classes
- index page with search, sortable columns, and pagination
- relationship-aware forms and show/index rendering
- file and image upload handling

If `options.volt: false`, Crudify generates classic Livewire page classes, Blade views, controllers, form requests, and routes instead.

## Relationships

Supported relationship types:

- `belongsTo` - generates model method and dropdown field in forms
- `hasMany` - generates model method
- `hasOne` - generates model method
- `belongsToMany` - generates model method, checkbox UI, sync on save, and pivot migration

Optional relationship keys:

- `display` - attribute shown in selects, checkbox labels, index badges, and show pages
- `label` - user-facing label for forms, table headers, and detail sections

### belongsToMany Example

```yaml
model: Post

fields:
  title: string
  body: text

relationships:
  tags:
    type: belongsToMany
    model: Tag
    display: slug
    label: Topics
```

Generated extras:

- `app/Models/Tag.php` if missing
- `*_create_post_tag_table.php` pivot migration
- `selectedTagsIds` validation rules
- checkbox UI in create/edit
- related tags shown in index and show views

### More Many-to-Many Examples

#### Posts and Tags

```yaml
model: Post

fields:
  title: string
  body: text
  is_published: boolean

relationships:
  user:
    type: belongsTo
    model: User

  tags:
    type: belongsToMany
    model: Tag
```

This is a good fit for blog posts, articles, and knowledge base entries.

#### Products and Collections

```yaml
model: Product

fields:
  name: string
  slug:
    type: string
    unique: true
  description: text
  price: decimal
  is_active: boolean

relationships:
  collections:
    type: belongsToMany
    model: Collection
```

Crudify generates a `collection_product` pivot migration and checkbox selection UI for collections.

#### Users and Roles

```yaml
model: User

fields:
  name: string
  email:
    type: string
    unique: true
  password: string

relationships:
  roles:
    type: belongsToMany
    model: Role
```

This is useful for admin panels where a user can have multiple roles.

## Field Types

- `string`
- `text`
- `integer`
- `bigint`
- `float`
- `decimal`
- `boolean`
- `date`
- `datetime`
- `timestamp`
- `json`
- `uuid`
- `foreign`
- `image`
- `file`

## Field Options

```yaml
fields:
  name:
    type: string
    nullable: true
    unique: true
    index: true
    default: Example

  author_id:
    type: foreign
    foreign: users
```

Supported field options:

- `nullable`
- `unique`
- `index`
- `default`
- `foreign`
- `multiple`

## Shorthand Syntax

Simple fields can use shorthand:

```yaml
model: Product

fields:
  name: string
  description: text
  price: decimal
  is_active: boolean
```

Equivalent long form:

```yaml
model: Product

fields:
  name:
    type: string
  description:
    type: text
  price:
    type: decimal
  is_active:
    type: boolean
```

## Volt Example

```yaml
model: Article

fields:
  title: string
  body: text

options:
  volt: false
```

This generates classic Livewire files under `app/Livewire/Pages/...` and `resources/views/livewire/pages/...`.
