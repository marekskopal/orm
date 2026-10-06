# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-10-06

### Fixed
- `fetchOne()`, `fetchAssocOne()` and `count()` left the cached statement open after reading its row. On SQLite this kept the database locked, and other connections failed to write with "database is locked" ([#27](https://github.com/marekskopal/orm/issues/27)). Statements are now closed once their result is read, including `iterate()` and `iterateAssoc()` generators abandoned early.

## [2.0.0] - 2026-10-06

Version 2.0 contains breaking changes; [UPGRADE-2.0.md](UPGRADE-2.0.md) explains how to upgrade from 1.x.

### Added
- `Select::iterate()` and `Select::iterateAssoc()` stream results one row at a time as generators, for result sets too large to hold in memory.
- Cloning a `Select` copies its where conditions, including nested condition groups, so the clone can be changed without affecting the original.
- `MySqlDatabase` accepts a `port` constructor parameter (default `3306`), matching `PostgresDatabase`.
- CI runs the integration tests against MySQL 8 and PostgreSQL 16 service containers as well as SQLite. The MySQL and PostgreSQL suites share one set of tests, and CI fails rather than skips when a database is unreachable.
- Generated hydrators and extractors: each entity gets straight-line code that converts a row to an entity and back, with types, enum backing types and relation kinds resolved once instead of per row. Hydrating 20,000 rows is about 2.5 times faster and uses about 75% less memory per entity.
- `SchemaBuilder::dump()` writes the schema, including the generated code, to a PHP file; `Schema::fromFile()` loads it. Under opcache, loading needs no attribute scan, reflection or code generation, and each entity's schema is built on first use.
- Entities with `private`, `protected` or `readonly` properties can be hydrated.
- `Select::with()` accepts every relation kind (`OneToMany`, `ManyToMany` and both inverse sides, besides `ManyToOne` and `OneToOne`) and dotted paths such as `posts.tags`, with one query per relation level. `fetchOne()` honours `with()` too.
- Lazy `ManyToOne` and `OneToOne` relations load in batches: when one proxy is read, every pending proxy of the same class loads with one `WHERE id IN (...)` query.
- `Collection::isInitialized()` tells whether a lazy collection has been loaded.
- `UnitOfWork`, available from `ORM::getUnitOfWork()`: `persist()` and `remove()` schedule entities, `flush()` writes them. Inserts of a class are grouped into multi-row statements and deletes into one `DELETE ... IN`, ordered so parents are inserted before their children and deleted after them. 2,000 new entities are written with one statement. `refresh()` reloads an entity and discards unflushed changes.
- Change detection: an update writes only the columns that changed since the entity was read or last written, and an unchanged entity costs no query. It relies on a snapshot recorded at hydration, which adds about 3 ms and 45 bytes per mapped column per 20,000 hydrated entities.
- Entities with UUID or other non-integer primary keys, including UUID foreign keys, can be read, related and written.
- `Update::values()` restricts an update to the given columns.
- Database connections open lazily, on the first query or on `connect()`; constructing a database object or an ORM no longer connects. `isConnected()` reports the state.
- Prepared statements are cached per SQL string in a least-recently-used map (256 by default, configurable through a new last constructor argument of each database class, `0` disables it). Repeated query shapes skip the prepare round trip: 2,000 lookups by id take about half the time on MySQL and a third on PostgreSQL, and 200 lazy collection loads prepare once. `clearStatementCache()` drops the cache after schema changes.
- `DatabaseInterface::execute()` runs SQL through the cache and throws `QueryException` or `ConstrainException` with the SQL; every query of the ORM, including join-table writes, goes through it.

### Changed
- **Breaking:** `Select::fetchAll()` and `Select::fetchAssocAll()` return a `list` instead of a one-shot `Iterator`, so the result can be counted, indexed and iterated more than once. Code that streamed large results should switch to `iterate()` or `iterateAssoc()`. Wrapping calls in `iterator_to_array()` no longer works and should be removed.
- **Breaking:** `RepositoryInterface::findAll()` and `AbstractRepository::findAll()` return `list<T>` instead of `iterable<T>` / `Iterator<T>`. Custom repositories that override `findAll()` must change their return type to `array`.
- **Breaking:** `MapperInterface::mapToProperty()` accepts `string|int|float|bool|null`. Extension mappers must widen the `$value` parameter to include `bool`.
- **Breaking:** `Collection` implements `IteratorAggregate` instead of `Iterator`. The cursor methods `current()`, `next()`, `key()`, `valid()` and `rewind()` are removed and `toArray()` is added. Relation properties must be typed `Collection` or `iterable`, not `\Iterator`.
- README documents `Select` as a mutable builder whose terminal methods leave it unchanged.
- **Breaking:** a `ManyToOne` or `OneToOne` proxy is shared by every entity that references the same id, and it is the instance the identity map returns for that id. Fetching that entity afterwards returns the proxy, so `$post->author === $authorRepository->findOne(['id' => $id])`. An entity reached through a relation may be loaded by a batch query instead of its own `WHERE id = ?`.
- **Breaking:** lazy collections are no longer PHP lazy ghosts. `ReflectionClass::isUninitializedLazyObject()` returns `false` for them; use `Collection::isInitialized()`.
- **Breaking:** `QueryProvider`, `SelectFactory`, `InsertFactory`, `UpdateFactory`, `Select`, `Insert` and `Update` take a `RelationResolver` or `SchemaProvider` instead of `EntityFactory` or `Mapper` in their constructors. `SchemaProvider` is no longer `readonly`.
- **Breaking:** `ExtensionMapperProvider` takes a `SchemaProvider` in its constructor.
- `Update` binds positional parameters instead of named ones.
- A lazy `ManyToMany` collection loads with one query joining the join table instead of two queries.
- A `NULL` foreign key on a nullable `OneToOne` owning side hydrates as `null`. Previously it produced a proxy for id `0`.
- An inverse `OneToOne` property that accepts `null` is loaded with its parent (one query per relation for a whole result) and is `null` when there is no related row. Previously it was a proxy that threw on access when the row was missing. A non-nullable inverse side stays lazy.
- `SchemaBuilder` scans entity files in sorted path order, so table aliases, and the SQL that uses them, are the same on every filesystem. They used to follow directory listing order, which differs between macOS and Linux.
- A failed `flush()` rolls back and undoes its in-memory effects (assigned ids, identity map entries, snapshots), so it can be retried as is.
- **Breaking:** `DatabaseInterface` gains `connect()`, `isConnected()`, `execute()`, `prepareCached()` and `clearStatementCache()`. `AbstractDatabase` and the database classes are no longer `readonly`, and their constructors do not connect, so connection errors surface on the first query instead.
- **Breaking:** `EntityCache` is renamed `IdentityMap`, and `ORM::getEntityCache()` is now `ORM::getIdentityMap()`. Its methods are `get()`, `add()`, `contains()`, `remove()` and `clear()`, and keys are normalised so integer, string and UUID keys work. Long-running applications call `$orm->getIdentityMap()->clear()` after each request.
- **Breaking:** repository `persist()` and `delete()` go through the unit of work: they flush it, including anything scheduled on it directly, and run in a transaction when they write more than one statement. `AbstractRepository` takes a `UnitOfWork` as a fourth constructor argument, and `Delete` takes a `SchemaProvider` as a fifth.
- **Breaking:** an unmanaged entity with a primary key that is not auto-increment is inserted with that key when no row has it, and updated in full when one does (checked with one query per class); previously it was always sent as an `UPDATE`, which wrote nothing for a new row. A primary key that is not auto-increment is included in inserts and never overwritten from `lastInsertId()`.
- Cascades are followed recursively, and a persisted entity is registered in the identity map; a deleted one is evicted, so `findOne()` never returns a deleted instance.
- `ManyToMany` join rows are synced by difference instead of delete-all and insert-each. Deleting an entity deletes the join rows referencing it from either side of a `ManyToMany` relation, with or without cascade.

### Removed
- **Breaking:** `EntityFactory`, `EntityReflection` and `Mapper` are replaced by the generated hydrators and extractors and by `RelationResolver`. Custom extension mappers implementing `MapperInterface` keep working.

## [1.4.0] - 2026-09-28

### Added
- `Select::orWhere()` adds an OR condition group directly on the query builder; previously this was only possible inside a nested `where()` closure.
- `Select::getCountSql()` exposes the SQL used by `count()`.
- Where conditions accept `null`: `['column' => null]` renders `IS NULL` and `['column', '!=', null]` renders `IS NOT NULL`. Any other operator with `null` throws `InvalidArgumentException`.
- Column paths in `where()`, `orderBy()`, `columns()` and `groupBy()` accept property names as well as database column names (`firstName` and `first_name` address the same column), and a `ManyToOne` property name (`address`) resolves to its foreign key column (`address_id`). Bare names that match neither still pass through unchanged, so unmapped database columns remain addressable.
- Int-backed enums are supported on `#[ColumnEnum]` properties; the column value is cast to the enum's backing type before `from()`, so both `1` and `"1"` from the driver work.
- README documents the `[column, operator, value]` form, relation paths, `IS NULL` and subqueries with examples that match the API.

### Changed
- `count()` ignores `ORDER BY`, `LIMIT` and `OFFSET`. A builder with a limit or offset set now returns the total number of matching rows instead of no row at all.
- CI runs for `release/**` branches as well as `main`.

### Fixed
- A nested `where()` closure that only called `orWhere()` rendered `WHERE (1 OR ...)` and matched every row.
- Two relations to the same table (`address` and `secondAddress`) or a self-referencing relation (`parent`) produced duplicate join aliases and "ambiguous column" errors. Join aliases are now assigned per relation path (`a`, `a_secondAddress`, `c_parent`), and aliases passed to `join()` are never reused.
- `where(['column' => false])` bound `false` as an empty string and never matched an integer column. Booleans are bound as `0`/`1`, including inside `IN` lists.
- `IN` / `NOT IN` with an empty array emitted `IN ()`, a syntax error on MySQL and PostgreSQL. They now render `1=0` / `1=1` and bind no parameters.
- `count()` permanently replaced the selected columns with `count(*)`, and `fetchOne()` / `fetchAssocOne()` permanently set `LIMIT 1`, so later `fetchAll()` calls on the same builder failed or returned one row.
- A nested `where()` closure without a return value stored `null` in the condition list and crashed at build time. The closure now configures the builder in place and its return value is ignored.
- Hydrating an entity with a `BOOLEAN` column on PostgreSQL threw a `TypeError`, because pdo_pgsql returns native `bool` and the mapper accepted only `string|int|float|null`. Values are normalised before mapping; the PostgreSQL test fixtures now use a real `BOOLEAN` column.

## [1.3.0] - 2026-07-02

### Changed
- Performance: `ManyToOne`/`OneToOne` lazy proxies are created with their primary key pre-seeded, so reading the id (including mapping foreign key columns during insert/update) no longer triggers a query to initialize the proxy.
- Performance: cascade persist skips relations whose lazy proxy or ghost collection was never initialized — persisting a parent no longer loads and rewrites untouched child rows or rewrites unchanged `ManyToMany` join-table rows.

### Fixed
- Entities whose primary key property name differs from its column name (`#[Column(name: '...')]`) were broken in relation mapping and `persist()`: the primary key was read using the column name as a property name, causing repeated inserts instead of updates.

## [1.2.0] - 2026-06-12

### Added
- `RawExpression` value object for raw SQL fragments in `columns()`, `groupBy()`, `orderBy()`, and the column position of `where()` tuples.
- `MySqlDatabase` charset constructor parameter (default `utf8mb4`).
- "Security considerations" section in the README.
- GitHub Actions and GitLab CI pipelines (PHPStan, PHPCS, PHPUnit).

### Changed
- **Breaking:** column names passed to `where()`, `orderBy()`, `columns()`, and `groupBy()` must match `[A-Za-z0-9_]+`; SQL expressions (e.g. `count(*) as c`) must now be wrapped in `RawExpression` instead of being passed as plain strings.
- **Breaking:** where-condition operators are validated against an allowlist (`=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`); any other operator throws `InvalidArgumentException`.
- MySQL connections now set `charset=utf8mb4` in the DSN and disable emulated prepares, so values are bound server-side.
- SQLite inserts read generated primary keys back via `INSERT ... RETURNING` (requires SQLite 3.35+), same as PostgreSQL.

### Fixed
- SQL injection via the operator position of `where()` condition tuples.
- SQL injection via column names: identifier quoting now doubles embedded quote characters, and the verbatim passthrough of column strings containing `(` was removed.
- Multi-entity insert on SQLite assigned wrong primary keys (`lastInsertId()` returns the last rowid of a batch, not the first).
- `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION` was silently dropped on PostgreSQL connections (PDO options were merged with array spread, which re-indexes integer keys).
- `NOT IN` and `NOT LIKE` conditions now generate correct SQL.

## [1.1.0] - 2026-04-15

### Added
- `Select::with()` for eager-loading `ManyToOne`/`OneToOne` relations in a single batched query, avoiding N+1 queries.

### Changed
- Performance: reuse the prepared statement in `ManyToMany` join-table sync, single-pass column scan in `AbstractRepository::persist()`, and removal of `array_merge` calls from hot paths in `WhereBuilder` and `Update`.

## [1.0.1] - 2026-03-09

### Changed
- Minor performance improvements.

## [1.0.0] - 2026-03-08

### Added
- PostgreSQL support (`PostgresDatabase`).
- `ManyToMany` and `OneToOne` relations.
- Transactions via `TransactionProvider`.
- Cascade operations (`persist`/`remove`) on relations.

## [0.9.7] - 2026-03-08

### Changed
- SQL identifier escaping is database-specific (backtick on MySQL, double quote on PostgreSQL/SQLite).
- `ExceptionFactory::create()` is static; removed circular setter injection between `Mapper` and `EntityFactory`.
- Database connections use `PDO::ERRMODE_EXCEPTION`.

### Fixed
- `SchemaBuilder::setTableCase()` assigned the column case instead of the table case.
- `Delete` queries were missing SQL identifier escaping.
- `Delete::getIds()` read the entity by column name instead of property name.
- `Update::getValues()` hardcoded the `id` key for the primary column binding.
- Multi-entity insert assigned the same ID to every entity.
- Typo in `Join::referenceTableAlias` property name.

## [0.9.6] - 2026-02-17

### Fixed
- Custom column name handling in the schema factory.

## [0.9.5] - 2026-01-16

### Fixed
- `fetchOne()` performance.

## [0.9.4] - 2025-05-05

### Fixed
- `WHERE` with multiple joins.
- `LIKE` conditions in `where()`.
- `SELECT` combining joins and `where()`.

## [0.9.3] - 2025-05-04

### Fixed
- `WHERE` join handling when multiple joins are involved.

## [0.9.2] - 2025-03-01

### Fixed
- `SELECT` with aggregate functions.

## [0.9.1] - 2025-01-04

### Fixed
- Query API.

## [0.9.0] - 2024-12-31

Initial release.

### Added
- Schema building from PHP attributes (`#[Entity]`, `#[Column]`, `#[ColumnEnum]`, `#[ManyToOne]`, `#[OneToMany]`, `#[ForeignKey]`) with snake_case naming defaults.
- Fluent query builders: `Select` (where/orWhere with nesting and subqueries, joins, order by, group by, limit/offset, count), `Insert`, `Update`, `Delete`.
- Repositories with `findAll()`, `findOne()`, `persist()`, and `delete()`; custom repository classes per entity.
- Entity hydration with identity-map caching; lazy proxies for `ManyToOne` and lazy collections for `OneToMany` relations.
- Column types: int, float, string, bool, UUID, date/time, timestamp, enum, text, blob; defaults, size, precision, and scale.
- Extension mapper support for custom property mapping.
- MySQL and SQLite database drivers.

[2.0.1]: https://github.com/marekskopal/orm/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/marekskopal/orm/compare/v1.4.0...v2.0.0
[1.4.0]: https://github.com/marekskopal/orm/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/marekskopal/orm/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/marekskopal/orm/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/marekskopal/orm/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/marekskopal/orm/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/marekskopal/orm/compare/v0.9.7...v1.0.0
[0.9.7]: https://github.com/marekskopal/orm/compare/v0.9.6...v0.9.7
[0.9.6]: https://github.com/marekskopal/orm/compare/v0.9.5...v0.9.6
[0.9.5]: https://github.com/marekskopal/orm/compare/v0.9.4...v0.9.5
[0.9.4]: https://github.com/marekskopal/orm/compare/v0.9.3...v0.9.4
[0.9.3]: https://github.com/marekskopal/orm/compare/v0.9.2...v0.9.3
[0.9.2]: https://github.com/marekskopal/orm/compare/v0.9.1...v0.9.2
[0.9.1]: https://github.com/marekskopal/orm/compare/v0.9.0...v0.9.1
[0.9.0]: https://github.com/marekskopal/orm/releases/tag/v0.9.0
