# Crudify

[![Tests](https://github.com/devalade/crudify/actions/workflows/tests.yml/badge.svg)](https://github.com/devalade/crudify/actions/workflows/tests.yml)

Crudify sert a generer rapidement un CRUD Laravel a partir d'une commande ou d'un fichier YAML.

Ce guide est volontairement simple :

- un seul chemin recommande
- un seul exemple complet
- les preconditions expliquees avant de lancer generation
- la reference technique regroupee a la fin

Si vous cherchez le fonctionnement interne du package ou les details pour contribuer, utilisez plutot `README.md`.

## Ce que Crudify genere

Crudify peut generer :

- un modele Eloquent
- une migration
- une factory
- un seeder
- une policy
- des pages CRUD pretes a l'emploi

Fonctionnalites utiles :

- relations `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`
- uploads simples et multiples
- soft deletes
- champs de recherche
- synchronisation many-to-many

## Prerequis

- PHP `^8.2`
- Laravel `^11.0 | ^12.0 | ^13.0`

## Parcours recommande

Ordre conseille :

1. installer le package
2. preparer le front avec `php artisan crudify:install` ou `php artisan crudify:setup`
3. creer un fichier YAML a la racine du projet
4. remplir ce fichier avec votre modele, vos champs et vos relations
5. lancer `php artisan crudify:generate`
6. lancer `php artisan migrate`

## Etape 1 - Installer le package

```bash
composer require devalade/crudify --dev
```

## Etape 2 - Preparer le package

Option la plus simple :

```bash
php artisan crudify:install
```

Cette commande peut :

- installer `livewire/flux`
- installer dependances front si `package.json` existe
- lancer `php artisan crudify:setup`

Si vous avez deja les dependances et que vous voulez seulement preparer les fichiers front :

```bash
php artisan crudify:setup
```

Cette commande prepare notamment :

- `resources/css/app.css`
- `resources/js/app.js`
- les layouts avec `@fluxAppearance`, `@vite(...)` et `@fluxScripts`

## Etape 3 - Comprendre les preconditions avant l'exemple

Dans l'exemple complet plus bas, on reference `User`, `Category`, `Tag` et `Comment`.

Il faut donc etre explicite :

- `User` doit deja exister si vous gardez relation `user`
- `Category` doit deja exister si vous gardez relation `category`
- `Comment` doit deja exister si vous gardez relation `comments`
- `Tag` peut etre cree automatiquement par Crudify dans cas `belongsToMany` si modele `Tag` n'existe pas encore

Il faut aussi penser aux tables et aux migrations :

- `users` doit deja exister si vous gardez relation `user`
- `categories` doit deja exister si vous gardez relation `category`
- `comments` doit deja exister si vous voulez exploiter la relation `comments`
- `tags` est plus souple dans cas many-to-many

Point tres important :

- une relation `belongsTo` peut creer automatiquement le champ `*_id` si ce champ n'est pas declare dans `fields`
- si le champ existe deja dans `fields`, Crudify garde cette definition et ne le cree pas une deuxieme fois
- declarez explicitement `user_id` ou `category_id` dans `fields` quand vous voulez controler la table cible, `nullable`, `index`, `default`, etc.
- si vous utilisez un nom de relation different du modele, par exemple `author` vers `User`, Crudify cree `author_id` et reference la table `users`

Donc, si certains modeles ou certaines tables n'existent pas encore, vous avez deux choix :

- les creer d'abord dans votre projet
- simplifier l'exemple et retirer les relations concernees

## Etape 4 - Creer le fichier YAML

Creez fichier YAML a la racine du projet Laravel.

Exemples selon votre systeme :

```bash
# macOS / Linux
touch post.yaml
```

```powershell
# Windows PowerShell
New-Item -Path .\post.yaml -ItemType File
```

```cmd
:: Windows CMD
type nul > post.yaml
```

Dans ce guide :

- le fichier s'appelle `post.yaml`
- il est place a la racine du projet
- il sert a generer modele `Post`

## Etape 5 - Copier l'exemple complet

Copiez ce contenu dans `post.yaml` :

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

  excerpt:
    type: text

  body:
    type: text

  featured_image:
    type: image
    nullable: true

  gallery:
    type: image
    multiple: true
    nullable: true

  is_published:
    type: boolean
    default: false

  published_at:
    type: datetime
    nullable: true

relationships:
  user:
    type: belongsTo
    model: User
    display: email
    label: Auteur

  category:
    type: belongsTo
    model: Category
    display: name
    label: Categorie

  tags:
    type: belongsToMany
    model: Tag
    display: slug
    label: Tags

  comments:
    type: hasMany
    model: Comment

options:
  soft_deletes: true
  volt: true
```

## Etape 6 - Comprendre l'exemple

### Champs

Dans cet exemple :

- `title`, `slug`, `excerpt`, `body` sont champs texte du post
- `featured_image` gere image principale
- `gallery` gere plusieurs images
- `is_published` et `published_at` gerent publication
- `soft_deletes: true` active corbeille Laravel

### Relations

Dans cet exemple :

- `user` declare la methode Eloquent `user()` et cree automatiquement `user_id`
- `category` declare la methode Eloquent `category()` et cree automatiquement `category_id`
- `tags` cree une relation many-to-many, l'interface de selection, et la migration pivot
- `comments` declare la relation `hasMany`; la cle etrangere est portee par la table `comments`, pas par `posts`

Le plus simple est donc de ne pas repeter `user_id` ou `category_id` dans `fields` si la relation `belongsTo` suffit.

Exemple minimal :

```yaml
fields:
  title:
    type: string

relationships:
  author:
    type: belongsTo
    model: User
    display: email
```

Cet exemple cree `author_id` dans la migration de `posts` et reference la table `users`.

Si vous voulez controler la colonne vous-meme, declarez-la dans `fields` :

```yaml
fields:
  user_id:
    type: foreign
    foreign: users
    nullable: true

relationships:
  user:
    type: belongsTo
    model: User
    display: email
```

Crudify utilisera ce `user_id` existant et ne le creera pas une deuxieme fois.

Si vous voulez utiliser un autre nom de colonne pour une relation :

```yaml
relationships:
  author:
    type: belongsTo
    model: User
    display: email
    foreign_key: user_id
```

`user` :

- le post appartient a un utilisateur
- `display: email` affiche email dans interface
- `label: Auteur` affiche libelle "Auteur"
- ce bloc suppose que modele `User` et table `users` existent deja

`category` :

- le post appartient a une categorie
- `display: name` affiche nom de categorie
- ce bloc suppose que modele `Category` et table `categories` existent deja

`tags` :

- le post peut avoir plusieurs tags
- `display: slug` affiche slug du tag
- `label: Tags` affiche libelle "Tags"
- dans create/edit, utilisateur selectionne plusieurs tags
- dans index/show, tags apparaissent en badges
- si modele `Tag` manque, Crudify peut le generer dans ce cas

`comments` :

- le post possede plusieurs commentaires
- cette relation est ajoutee au modele
- ce bloc suppose que modele `Comment` et table `comments` existent deja

## Etape 7 - Generer puis migrer

Une fois `post.yaml` pret :

```bash
php artisan crudify:generate --file=post.yaml
php artisan migrate
```

## Resultat attendu

Crudify genere par defaut notamment :

- `app/Models/Post.php`
- `database/migrations/*_create_posts_table.php`
- `database/factories/PostFactory.php`
- `database/seeders/PostSeeder.php`
- `app/Policies/PostPolicy.php`
- `resources/views/pages/posts/index.blade.php`
- `resources/views/pages/posts/create.blade.php`
- `resources/views/pages/posts/edit.blade.php`
- `resources/views/pages/posts/show.blade.php`

Dans cas many-to-many avec `tags`, Crudify peut aussi generer :

- `app/Models/Tag.php` si absent
- migration pivot `post_tag`

## Routes

Crudify enregistre automatiquement routes detectees depuis `resources/views/pages/...`.

En pratique :

- vous n'avez pas besoin d'ecrire routes au debut
- c'est utile pour aller vite

## Ejecter les routes

Quand vous voulez reprendre controle manuel :

```bash
php artisan crudify:eject-routes
```

Effet :

- Crudify copie routes dans `routes/web.php`
- auto-decouverte est desactivee pour ces routes
- vous pouvez ensuite proteger ou reorganiser ces routes vous-meme

Exemple de bloc ejecte :

```php
// CRUDify Ejected Routes
Route::livewire('/posts', 'pages::posts.index')->name('posts.index');
Route::livewire('/posts/create', 'pages::posts.create')->name('posts.create');
Route::livewire('/posts/{post}/edit', 'pages::posts.edit')->name('posts.edit');
Route::livewire('/posts/{post}/show', 'pages::posts.show')->name('posts.show');
// End CRUDify Ejected Routes
```

## Proteger les routes apres ejection

Exemple avec `auth` :

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // CRUDify Ejected Routes
    Route::livewire('/posts', 'pages::posts.index')->name('posts.index');
    Route::livewire('/posts/create', 'pages::posts.create')->name('posts.create');
    Route::livewire('/posts/{post}/edit', 'pages::posts.edit')->name('posts.edit');
    Route::livewire('/posts/{post}/show', 'pages::posts.show')->name('posts.show');
    // End CRUDify Ejected Routes
});
```

Exemple avec `auth` et `verified` :

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // CRUDify Ejected Routes
    Route::livewire('/posts', 'pages::posts.index')->name('posts.index');
    Route::livewire('/posts/create', 'pages::posts.create')->name('posts.create');
    Route::livewire('/posts/{post}/edit', 'pages::posts.edit')->name('posts.edit');
    Route::livewire('/posts/{post}/show', 'pages::posts.show')->name('posts.show');
    // End CRUDify Ejected Routes
});
```

Exemple avec prefixe admin :

```php
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::livewire('/posts', 'pages::posts.index')->name('posts.index');
        Route::livewire('/posts/create', 'pages::posts.create')->name('posts.create');
        Route::livewire('/posts/{post}/edit', 'pages::posts.edit')->name('posts.edit');
        Route::livewire('/posts/{post}/show', 'pages::posts.show')->name('posts.show');
    });
```

## Reference rapide

### Format CLI des champs

```text
nom:type:modificateur1:modificateur2
```

Exemples :

- `title:string`
- `body:text`
- `email:string:unique`
- `published_at:datetime:nullable`
- `photo:image`
- `documents:file:multiple`
- `user_id:foreign:users` si vous voulez declarer explicitement une cle etrangere dans les champs

### Types de champs supportes

`string`, `text`, `integer`, `bigint`, `float`, `double`, `decimal`, `boolean`, `date`, `datetime`, `timestamp`, `time`, `json`, `uuid`, `email`, `foreign`, `image`, `file`

### Modificateurs utiles

- `nullable`
- `unique`
- `index`
- `default:value`
- `foreign:table`
- `multiple`

### Format CLI des relations

```text
nom:type:modele[:display[:foreign_key]]
```

Exemples :

- `user:belongsTo:User:email`
- `category:belongsTo:Category:name`
- `author:belongsTo:User:email:user_id`
- `tags:belongsToMany:Tag:slug`
- `comments:hasMany:Comment`

### Types de relations supportes

- `belongsTo`
- `hasMany`
- `hasOne`
- `belongsToMany`

### Options d'affichage des relations

- `display` : champ affiche dans select, checkbox, badges et pages detail
- `label` : libelle visible dans interface
- `foreign_key` : nom de colonne a utiliser pour une relation `belongsTo` si le nom par defaut `relation_id` ne convient pas

### Commandes utiles

```bash
php artisan crudify:install
php artisan crudify:setup
php artisan crudify:generate --file=post.yaml
php artisan crudify:eject-routes
php artisan migrate
```

### Options utiles de `crudify:generate`

```bash
php artisan crudify:generate {model}
  --fields=
  --file=
  --relationships=
  --only=
  --skip=
  --soft-delete
  --searchable=
  --force
  --dry-run
```

## Personnaliser les stubs

```bash
php artisan crudify:stubs
```

Puis modifiez :

```text
stubs/crudify/
```

## Tests et qualite

```bash
composer test
composer test:unit
composer test:feature
composer analyse
composer format
composer format:check
```

## Licence

MIT
