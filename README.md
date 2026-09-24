# x-laravel/embedding — PostgreSQL Driver

[![Tests](https://github.com/x-laravel/embedding-pgsql-driver/actions/workflows/tests.yml/badge.svg)](https://github.com/x-laravel/embedding-pgsql-driver/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

PostgreSQL pgvector driver for [x-laravel/embedding](https://github.com/x-laravel/embedding).

## How It Works

- Implements `SimilarityDriver` — registers as the `pgsql` driver, similarity search runs entirely in PostgreSQL using pgvector's `<=>` cosine distance operator
- No custom `VectorStore` needed — pgvector's text format (`[1.0, 2.0, ...]`) is valid JSON, so the core JSON storage works transparently
- Translates payload `filter:` constraints to type-strict `jsonb` SQL against the `embeddables` table

## Requirements

- PHP ^8.3
- Laravel ^12.0 | ^13.0
- `x-laravel/embedding ^1.0`
- PostgreSQL with [pgvector](https://github.com/pgvector/pgvector) extension

## Installation

```bash
composer require x-laravel/embedding-pgsql-driver
```

The `PgsqlEmbeddingServiceProvider` is auto-discovered and registers the `pgsql` driver automatically.

## Setup

### 1. Configure x-laravel/embedding

Publish the config if you haven't already:

```bash
php artisan vendor:publish --tag=embedding-config
```

Set the similarity driver and database connection in `config/embedding.php`:

```php
'database' => [
    'connection' => env('EMBEDDINGS_DATABASE_CONNECTION', env('DB_CONNECTION', 'pgsql')),
    'embeddings_table' => env('EMBEDDINGS_DB_TABLE', 'embeddings'),
    'embeddables_table' => env('EMBEDDABLES_DB_TABLE', 'embeddables'),
],

'similarity' => [
    'driver' => env('EMBEDDING_SIMILARITY_DRIVER', 'auto'),
],
```

### 2. Create the tables

This driver ships its own PostgreSQL-native migrations that **replace** the default ones from `x-laravel/embedding`: `embeddings` enables the pgvector extension and creates a `vector(1536)` column, `embeddables` creates a native `jsonb` payload column.

Publish and run the migrations (migrations are not loaded automatically — publish the driver migrations, **not** the core ones):

```bash
php artisan vendor:publish --tag=embedding-pgsql-migrations
php artisan migrate
```

The published files are plain migrations in `database/migrations/` — customise the DDL there if needed (e.g. an HNSW index on `vector`) before running `migrate`.

> **Note:** `CREATE EXTENSION IF NOT EXISTS vector` requires `CREATE` privilege on the database. On managed platforms (RDS, Supabase, Cloud SQL), enable the extension separately with elevated privileges before running the migration.

### 3. Model

Follow the standard `x-laravel/embedding` setup. No PostgreSQL-specific changes are needed on your models.

```php
use XLaravel\Embedding\Attributes\EmbedOn;
use XLaravel\Embedding\Concerns\Embeddable;
use XLaravel\Embedding\Contracts\HasEmbeddings;

#[EmbedOn(['title', 'body'])]
class Post extends Model implements HasEmbeddings
{
    use Embeddable;

    public function toEmbeddingText(string $slot = 'default'): string
    {
        return $this->title.' '.$this->body;
    }
}
```

## Usage

The driver is transparent — use the standard `x-laravel/embedding` API:

```php
Post::similarToText('web framework', limit: 10);
Post::similarTo($vector, limit: 10, threshold: 0.8);
Post::rankByRelevance($posts, 'web framework');

$post->mostSimilar(limit: 5);
$post->similarityTo($otherPost);
```

All methods set a `similarity_score` float attribute on each returned model.

### Payload filtering

Models using `#[EmbedPayload]` can filter similarity searches at the database level. The driver translates `filter:` to a `whereExists` subquery comparing `payload->'key'` with `jsonb` values. `jsonb` equality never matches across JSON types, so comparisons are type-strict (`34` never matches `"34"`, `true` never matches `1`):

```php
use XLaravel\Embedding\Attributes\EmbedPayload;

#[EmbedOn('name')]
#[EmbedPayload(['province_id', 'category_id', 'active'])]
class Venue extends Model implements HasEmbeddings { ... }

Venue::similarTo($vector, limit: 300, filter: ['province_id' => 34]);            // equality
Venue::similarToText('kebap', filter: ['category_id' => [3, 7]]);                // IN
$venue->mostSimilar(limit: 5, filter: ['province_id' => 34, 'active' => true]);  // AND
```

Records without a payload row never match a filtered search.

## Testing

```bash
# Build first (once per PHP version)
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run tests
docker compose --profile php83 up
docker compose --profile php84 up
docker compose --profile php85 up
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
