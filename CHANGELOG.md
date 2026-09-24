# Changelog

All notable changes to `x-laravel/embedding-pgsql-driver` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The package's major version follows `laravel/ai`.

## 1.0.0 - 2026-09-24

Initial release. Requires PHP ^8.3, Laravel ^12.0 | ^13.0, `x-laravel/embedding` ^1.0 and PostgreSQL with the pgvector extension.

### Added

- `PgsqlDriver` — `pgsql` similarity driver running cosine search in PostgreSQL with pgvector's `<=>` operator. Distance is converted to `similarity_score = 1 - distance`, and the distance cutoff applies only when `threshold > 0.0`. Soft-deleting models are loaded with `withTrashed()`.
- Payload `filter` translation for `similarTo()` / `similarToText()` / `mostSimilar()`: a `whereExists` subquery against `embeddables` comparing `payload->'key'` with `jsonb` values. `jsonb` equality is type-strict, so `34` never matches `"34"`; array values compile to `IN`, an empty array matches nothing, and filter keys are validated before being interpolated into the JSON path.
- Core `JsonVectorStore` compatibility — pgvector's text format is valid JSON, so no custom `VectorStore` is needed.
- `PgsqlVectorStoreMetrics` and `PgsqlPayloadStoreMetrics` — storage figures from `pg_total_relation_size`, `pg_relation_size` / `pg_table_size` and `pg_indexes_size`.
- PostgreSQL-native migrations with the core package's filenames: `embeddings` with a pgvector `vector(n)` column (enabling the extension) and `embeddables` with a native `jsonb` payload column, published under the `embedding-pgsql-migrations` tag.
