# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Query/SelectTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName tests/Query/SelectTest.php

# Static analysis
vendor/bin/phpstan analyse

# Code style check
vendor/bin/phpcs

# Code style fix
vendor/bin/phpcbf
```

## Architecture

This is a lightweight PHP ORM library (PHP 8.4+, namespace `MarekSkopal\ORM`). Source is in `src/`, tests in `tests/`.

### Core flow

1. **Schema building** (`src/Schema/Builder/`) — `SchemaBuilder` scans entity class paths, reads PHP Attributes (`#[Entity]`, `#[Column]`, `#[ManyToOne]`, `#[OneToMany]`), and produces a `Schema` containing `EntitySchema` and `ColumnSchema` objects. Table/column names default to snake_case derived from class/property names.

2. **ORM entry point** (`src/ORM.php`) — wires together `SchemaProvider`, `EntityCache`, `EntityReflection`, `EntityFactory`, `Mapper`, and `QueryProvider`. `getRepository()` returns a typed repository; `getQueryProvider()` gives direct query access.

3. **Query layer** (`src/Query/`) — `QueryProvider` creates fluent query builders (`Select`, `Insert`, `Update`, `Delete`) via factory classes. `Select` supports `where()`, `orWhere()`, `join()`, `orderBy()`, `limit()`, and fetches via `fetchOne()` / `fetchAll()`. Where conditions accept arrays, callables (for nesting), or raw `Select` subqueries.

4. **Mapping** (`src/Mapper/Mapper.php`) — converts between PHP property values and database column values. Handles `string`, `int`, `float`, `bool`, `Uuid`, `DateTime`/`DateTimeImmutable`, enums, and relations. Relations use PHP lazy proxies/ghosts: `ManyToOne` loads via lazy proxy, `OneToMany` loads via lazy ghost `Collection`.

5. **Entity caching** (`src/Entity/EntityCache.php`) — identity map keyed by entity class + primary key, preventing duplicate hydration.

6. **Repositories** (`src/Repository/`) — `AbstractRepository` provides `findAll()`, `findOne()`, `persist()` (insert or update based on primary key presence), and `delete()`. Custom repositories extend `AbstractRepository` and are referenced in `#[Entity(repositoryClass: MyRepository::class)]`.

7. **Database layer** (`src/Database/`) — `DatabaseInterface` abstraction over PDO; implementations for `MySqlDatabase` and `SqliteDatabase`.

### Key conventions

- All attributes live in `src/Attribute/`: `Entity`, `Column`, `ColumnEnum`, `ManyToOne`, `OneToMany`, `ForeignKey`.
- `src/Enum/Type.php` defines column types (`Type::Int`, `Type::String`, `Type::Timestamp`, etc.).
- `ColumnSchema` is keyed by **property name** in `EntitySchema::$columns`; column name is a separate field.
- Tests use fixtures in `tests/Fixtures/` (entity, schema, repository fixtures) rather than a database; `IntegrationTest.php` uses SQLite.
- PHPStan runs at max level; all new code must be fully typed.
- Tests require `#[CoversClass]` attributes (strict coverage metadata is enforced).
