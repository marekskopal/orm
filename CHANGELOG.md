# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
