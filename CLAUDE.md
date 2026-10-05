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

1. **Schema building** (`src/Schema/Builder/`) — `SchemaBuilder` scans entity class paths, reads PHP Attributes (`#[Entity]`, `#[Column]`, `#[ManyToOne]`, `#[OneToMany]`), and produces a `Schema` containing `EntitySchema` and `ColumnSchema` objects. Table/column names default to snake_case derived from class/property names. `SchemaBuilder::dump()` writes the schema plus generated code to a PHP file that `Schema::fromFile()` loads (each `EntitySchema` there is a lazy proxy).

2. **ORM entry point** (`src/ORM.php`) — wires together `SchemaProvider`, `EntityCache`, `RelationResolver`, and `QueryProvider`. `getRepository()` returns a typed repository; `getQueryProvider()` gives direct query access.

3. **Query layer** (`src/Query/`) — `QueryProvider` creates fluent query builders (`Select`, `Insert`, `Update`, `Delete`) via factory classes. `Select` supports `where()`, `orWhere()`, `join()`, `orderBy()`, `limit()`, and fetches via `fetchOne()` / `fetchAll()`. Where conditions accept arrays, callables (for nesting), or raw `Select` subqueries.

4. **Generated hydrators and extractors** (`src/Schema/Compiler/`) — `HydratorGenerator` and `ExtractorGenerator` emit straight-line PHP per entity (row → entity, entity → column values), bound into the entity's scope so private/readonly properties work. `SchemaProvider::getHydrator()` / `getExtractor()` return the dumped closures or compile them on first use (`SchemaCompiler`, via `eval`). Golden files for every fixture entity are in `tests/Fixtures/Compiler/Golden/`; regenerate with `ORM_UPDATE_GOLDEN=1 vendor/bin/phpunit tests/Schema/Compiler/GeneratedCodeTest.php`. Extension mappers (`MapperInterface`) run through `ExtensionMapperProvider`.

5. **Relations and identity** (`src/Relation/RelationResolver.php`, `src/Entity/EntityCache.php`) — `RelationResolver` hydrates rows through the identity map (`EntityCache`, keyed by class + primary key, bound by reference) and resolves relations for the generated hydrators. `ManyToOne`/`OneToOne` get one PHP lazy proxy per related id, registered as the canonical instance and initialised in batches (`WHERE id IN (...)` for all pending ids of a class). Collections (`Mapper\Collection`) are lazy on their own and load through the resolver; `Select::with()` preloads any relation kind and dotted paths with one query per level.

6. **Repositories** (`src/Repository/`) — `AbstractRepository` provides `findAll()`, `findOne()`, `persist()` (insert or update based on primary key presence), and `delete()`. Custom repositories extend `AbstractRepository` and are referenced in `#[Entity(repositoryClass: MyRepository::class)]`.

7. **Database layer** (`src/Database/`) — `DatabaseInterface` abstraction over PDO; implementations for `MySqlDatabase`, `PostgresDatabase` and `SqliteDatabase`.

### Key conventions

- All attributes live in `src/Attribute/`: `Entity`, `Column`, `ColumnEnum`, `ManyToOne`, `OneToMany`, `ForeignKey`.
- `src/Enum/Type.php` defines column types (`Type::Int`, `Type::String`, `Type::Timestamp`, etc.).
- `ColumnSchema` is keyed by **property name** in `EntitySchema::$columns`; column name is a separate field.
- Tests use fixtures in `tests/Fixtures/` (entity, schema, repository fixtures) rather than a database; `IntegrationTest.php` uses SQLite. `MySqlIntegrationTest` and `PostgresIntegrationTest` extend `AbstractDriverIntegrationTestCase` and run against real servers (skipped when unreachable; see README "Running tests"). Driver-specific SQL fixtures are named `database_<name>_<driver>.sql`.
- PHPStan runs at max level; all new code must be fully typed.
- Tests require `#[CoversClass]` attributes (strict coverage metadata is enforced).
