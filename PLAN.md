# ORM v2.0.0 plan

This branch collects the breaking changes for the next major release. Compatible bug fixes keep
landing on `main` and are merged into this branch periodically. The plan is derived from the
codebase review of 2026-09-28; the numbers below are its baseline measurements.

## Status

| # | Workstream | Status |
|---|---|---|
| 1 | Query and collection API cleanup | done |
| 2 | Compiled schema and generated hydrators | done |
| 3 | Relation loading without per-row proxies | done |
| 4 | Unit of work and identity map | not started |
| 5 | Connection layer with statement cache | not started |
| 6 | CI coverage for MySQL and PostgreSQL | done |

Suggested order: 1, then 2 and 3 together (the hydrator is where relation resolution lives),
then 4, then 5. 6 can land at any point and should land before 2 so hydrator changes are
verified on all three drivers.

## Why

Measured on SQLite in-memory, PHP 8.5, 20k rows, before any v2 work:

| Scenario | Result |
|---|---|
| Raw PDO fetchAll, 2-column table | 4 ms |
| ORM hydration, same table, no relations | 45 ms |
| ORM hydration, 5 columns + 3 relations | 174 ms, 2.2 KB per entity |
| Hand-written hydrator closure, same entity | 10 ms, 218 B per entity |
| 200 rows, accessing a ManyToOne, no `with()` | 201 queries |
| 200 rows, accessing a OneToMany | 401 queries |
| 2000 `persist()` calls | 2000 prepares, 18 ms |
| Same 2000 rows via one `Insert` | 1 prepare, 3 ms |
| Schema build, 14 entities | 2.5 ms per request |

Where the time goes:

- Relation proxies dominate hydration. Each row with a ManyToOne allocates a lazy proxy, a
  closure, and a fresh `ReflectionProperty`; each OneToMany allocates a lazy ghost `Collection`
  plus closure. That is the 45 ms to 174 ms jump and the 10x memory growth.
- Per-column dispatch. `EntityFactory::create` walks `ReflectionParameter` objects and runs the
  `match` in `Mapper::mapToProperty` for every cell.
- Cache hits still pay for the query: a re-fetch of cached rows runs the SELECT, fetches the
  rows, then discards them.
- Every query is a fresh server-side prepare. Lazy loads issue the identical `WHERE id = ?`
  hundreds of times with no statement reuse.
- Writes are all-or-nothing: `Update` writes every column, the repository inserts one row per
  call, and ManyToMany sync deletes all join rows then inserts one at a time.
- The schema is rebuilt from tokenised source and reflection on every request.
- The connection opens eagerly in the `Database` constructor.

## Breaking changes

Landed on this branch:

- `Collection` implements `IteratorAggregate` instead of `Iterator`. The cursor methods
  `current()`, `next()`, `key()`, `valid()`, `rewind()` are gone; `toArray()` is added. Relation
  properties must be typed `Collection` or `iterable`, not `\Iterator`. (PR #8)
- `Select::fetchAll()` and `Select::fetchAssocAll()` return lists; streaming moves to
  `Select::iterate()` and `Select::iterateAssoc()`. `findAll()` returns `list<T>`.
- `MapperInterface::mapToProperty()` accepts `bool`; custom extension mappers must widen
  their signature.
- (2) `SchemaBuilder::dump()` and `Schema::fromFile()` write and load a compiled schema;
  `EntitySchema` gains hydrator and extractor closures. `EntityFactory`, `EntityReflection` and
  `Mapper` are removed. Query classes, their factories and `QueryProvider` take a
  `RelationResolver` or `SchemaProvider` instead.
- (3) ManyToOne and OneToOne proxies are shared per related id and are the identity-mapped
  instance; `===` between a proxy held by two parents now holds. Entities obtained through a
  relation may be initialised by a batch load rather than a single-row query. Lazy collections
  are no longer PHP lazy ghosts; `Collection::isInitialized()` replaces
  `ReflectionClass::isUninitializedLazyObject()` for them.

Planned, by workstream:

- (4) `persist()` and `delete()` register work; `flush()` executes it. `EntityCache` is keyed by
  string. `persist()` of an entity with a non-auto-increment primary key sends that key.
- (5) `DatabaseInterface` gains `connect()`, `execute()` and `prepareCached()`; `getPdo()`
  connects lazily.

## Workstream 1: query and collection API cleanup

Goal: make the public query surface predictable before the internals change underneath it.

Done on `main` (compatible) and already in this branch: path-based join aliases, property-name
resolution, `IS NULL`, bool binding, empty `IN`, non-mutating `count()` and `fetchOne()`,
`Select::orWhere()`, nested closures without a return value.

Done here:

- `Collection` as `IteratorAggregate`.
- `fetchAll()` and `fetchAssocAll()` return lists; `iterate()` and `iterateAssoc()` stream.
  `RepositoryInterface::findAll()` returns `list<T>`. With `with()`, `iterate()` still buffers
  the raw rows so the relation preload can run first.
- `MapperInterface::mapToProperty()` accepts `string|int|float|bool|null`. The bool
  normalisation in `EntityFactory` stays until workstream 2 replaces the factory.
- No clone-on-write: the builder is documented as mutable. `clone $select` now copies the
  where conditions, including nested groups rebound to the clone, so branching a query works.

Carried into workstream 2: drop the bool normalisation in `EntityFactory::columnValue()`.

Files: `src/Query/Select.php`, `src/Repository/RepositoryInterface.php`,
`src/Repository/AbstractRepository.php`, `src/Mapper/MapperInterface.php`, `README.md`.

Acceptance: existing integration tests pass with `fetchAll()` results used twice; README
examples compile against the API.

## Workstream 2: compiled schema and generated hydrators

Goal: bring hydration within 2x of raw PDO and make the schema free under opcache.

Design:

- Keep the attribute scan as a build step. `SchemaBuilder::build()` still produces a `Schema`,
  but the schema is a plain data structure that `var_export`s cleanly. Add
  `SchemaBuilder::dump(string $path)` and `Schema::fromFile(string $path)`. Applications call
  `dump()` in their build or deploy step; opcache then serves the schema with no tokenising or
  reflection at runtime.
- For each entity, generate a hydrator: `static fn(array $row, RelationResolver $r): Entity`,
  bound into the entity's scope with `Closure::bind` so private and readonly properties work.
  The body is straight-line code: `(int) $row['id']`, `(string) $row['name']`,
  `UserTypeEnum::from($row['type'])`, `new DateTimeImmutable($row['created_at'])`,
  `$r->manyToOne(User::class, $row['author_id'])`. No `match`, no `ReflectionParameter`.
- Generate the matching extractor `static fn(Entity $e): array` for inserts, updates and
  dirty checking.
- Extension mappers (`MapperInterface`) stay supported: the generated code calls them for
  columns that declare one, so the hot path only pays for extensions where they are used.
- Enum backing type, DateTime class and relation kind are resolved at generation time, not per
  row.

Steps:

1. Add `HydratorGenerator` and `ExtractorGenerator` under `src/Schema/Compiler/` that emit PHP
   source from an `EntitySchema`.
2. Store the generated closures on `EntitySchema` (`hydrate`, `extract`) when the schema is
   built in-process; when dumped, emit them as functions in the schema file.
3. Replace `EntityFactory::create()` with `$schema->hydrate($row, $resolver)` in `Select`.
   Keep the identity-map check in front of it.
4. Replace the per-column `Mapper::mapToColumn()` loops in `Insert` and `Update` with
   `$schema->extract($entity)`.
5. Delete `EntityReflection`; delete the reflection caches in `Mapper`.

Files: `src/Schema/Builder/SchemaBuilder.php`, `src/Schema/EntitySchema.php`,
`src/Schema/ColumnSchema.php`, new `src/Schema/Compiler/*`, `src/Entity/EntityFactory.php`
(removed), `src/Query/Select.php`, `src/Query/Insert.php`, `src/Query/Update.php`.

Tests: a golden-file test per fixture entity for the generated source; the existing
`EntityFactoryTest` cases re-targeted at the generated hydrators; a test that a dumped schema
loads and hydrates identically to an in-process one.

Acceptance: 20k-row hydration of the 2-column entity under 10 ms; entities with private or
readonly properties hydrate; schema build from a dumped file under 0.1 ms.

Done. Measured on SQLite in-memory, PHP 8.5, 20k rows, no coverage extension loaded:

| Scenario | Before | After |
|---|---|---|
| 2-column entity | 20.1 ms | 8.5 ms |
| 5 columns + 3 relations | 78 ms, 2,981 B per entity | 21.3 ms, 392 B per entity |
| Same, one column a `DateTimeImmutable` | 100.8 ms, 3,351 B | 33.2 ms, 768 B |
| Load dumped schema, 22 entities, opcache | | 0.004 ms |

A `DateTimeImmutable` alone is about 350 B and 0.45 µs to parse, so entities with date columns
cannot reach the 400 B target. Baselines measured with pcov loaded are roughly 2x slower and
are not comparable.

How it differs from the steps above:

- Generators live in `src/Schema/Compiler/`: `HydratorGenerator`, `ExtractorGenerator`,
  `SchemaCompiler` (in-process `eval` of generated source) and `SchemaDumper`. Closures are
  compiled lazily by `SchemaProvider` on first use rather than in `SchemaBuilder::build()`, so a
  request only compiles the entities it touches. Only `EntitySchema` gains closures;
  `ColumnSchema` is unchanged.
- The dumped file wraps each `EntitySchema` in a lazy proxy, so loading the file builds no schema
  objects until an entity is used. Opcache does not cache a file modified in the last two seconds
  (`opcache.file_update_protection`), which matters only right after a dump.
- `Mapper` is deleted rather than slimmed down: after the generators took over, nothing called
  it. Extension mappers run through `ExtensionMapperProvider`, which the generated code calls
  only for columns that declare one.
- `fetchAll()` hydrates through `RelationResolver::hydrateAll()`, which binds the `EntityCache`
  identity map by reference. Going through `EntityCache` method calls cost more than the
  hydrator itself on the 2-column entity.
- The bool normalisation in `EntityFactory` is gone with the class; the generated casts accept
  native booleans directly.

## Workstream 3: relation loading without per-row proxies

Goal: remove the N+1 by default and cut per-entity memory to the hand-written baseline.

Design:

- `RelationResolver` (passed to hydrators) owns relation loading. It keeps, per entity class,
  a map of related id to proxy, so a parent row referencing an already-known id gets the same
  object, and it registers each proxy in the identity map so the proxy is the canonical
  instance for that id.
- Batched lazy loading: the resolver also keeps a per-class set of pending ids. When any one
  proxy initialises, the resolver loads all pending ids of that class with a single
  `WHERE id IN (...)` query and initialises every proxy from the result. Iterating 200 rows and
  touching each `->author` costs 2 queries, not 201, with no `with()` call.
- `with()` becomes an explicit pre-load that runs before hydration, and is extended to
  OneToMany and ManyToMany: load child rows with `WHERE fk IN (...)`, group by foreign key,
  and seed the collections. Nested paths (`with('author.company')`) run one query per level.
- ManyToMany loads use one query joining the join table instead of two.
- OneToMany collections are still lazy, but the ghost is created once per parent with a
  reference to the resolver rather than a closure capturing the mapper.

Steps:

1. Extract the relation branches of `Mapper::mapRelationToProperty()` into
   `src/Relation/RelationResolver.php`.
2. Add the per-class proxy map and pending-id set; register proxies in `EntityCache`.
3. Implement batch initialisation; the proxy initialiser calls `$resolver->initialise($class, $id)`.
4. Extend `Select::with()` to OneToMany, ManyToMany and dotted paths.
5. Replace the two-query ManyToMany load with a join.

Files: `src/Mapper/Mapper.php` (relation code removed), new `src/Relation/RelationResolver.php`,
`src/Query/Select.php`, `src/Entity/EntityCache.php`.

Tests: query-count assertions using a counting `DatabaseInterface` stub (see the review's probe
for the pattern); identity assertions that two parents share one proxy; `with()` on each
relation kind and on a nested path.

Acceptance: 200 rows touching a ManyToOne, no `with()`: at most 2 queries. 20k-row hydration
of the 3-relation entity under 40 ms and under 400 B per entity.

Done. 200 rows touching a ManyToOne take 2 queries (100 distinct ids before: 101 queries).
`with('comments', 'author')` on 200 rows takes 3 queries; before, `with()` rejected collections.
Hydration numbers are in workstream 2.

How it differs from the steps above:

- `RelationResolver` also owns the identity-map check and hydration (`hydrate()`,
  `hydrateAll()`), since every relation callback needs the identity map.
- Batch initialisation keeps loaded rows instead of hydrating them eagerly: when one proxy
  initialises, the rows of every pending id of its class are fetched and each proxy hydrates its
  own row when first read. A row that arrives through any later `Select` is handed to the
  matching uninitialised proxy, so that proxy needs no query either.
- Collections are lazy by themselves instead of PHP lazy ghosts. A ghost plus the side table
  needed to find its owner cost about 430 B per row; the collection holding the resolver, a
  literal relation key and the owner id costs about 120 B.
- `with()` also handles the inverse side of `OneToOne` and of `ManyToMany`, and `fetchOne()`
  applies it. Lazy OneToMany collections are not batched across parents; only `with()` loads
  several parents' collections in one query.
- Query-count and identity tests run on SQLite (`tests/Relation/RelationResolverTest.php`) and
  on MySQL and PostgreSQL (`tests/AbstractDriverIntegrationTestCase.php`). Queries are counted
  with `tests/Fixtures/Database/CountingStatement.php`, attached through
  `PDO::ATTR_STATEMENT_CLASS`.

## Workstream 4: unit of work and identity map

Goal: write only what changed, batch writes, and make the identity map correct.

Design:

- `EntityCache` becomes `IdentityMap`, keyed by `class` and a string key, so UUID and manual
  primary keys work. It stores a `WeakMap<object, array>` snapshot of the extracted row at
  hydration time. Entities the caller drops are collected together with their snapshot, which
  bounds memory in long-running processes.
- `UnitOfWork` with `persist()`, `remove()`, `flush()`. `flush()` orders work as: inserts of
  owning-side relations, inserts, updates, join-table sync, deletes of collection-side
  relations, deletes. Cascade is recursive.
- `flush()` extracts each managed entity, diffs against the snapshot, and emits `UPDATE` with
  only the changed columns, skipping entities with no changes. Inserts of the same class are
  grouped into multi-row statements. Deletes of the same class are grouped into one
  `DELETE ... IN`.
- Inserted entities are registered in the identity map with their new key; deleted entities
  are evicted. (Review bug 7.)
- `isAutoIncrement` is honoured: a primary key column that is not auto-increment is included in
  the insert and never overwritten from `lastInsertId()`. (Review bug 8.)
- `AbstractRepository::persist()` and `delete()` keep working as immediate operations by
  delegating to a unit of work and flushing at once, so the simple usage in the README stays
  valid. `ORM::getUnitOfWork()` exposes the deferred form.
- ManyToMany sync diffs the current join rows against the collection instead of delete-all
  plus insert-each.

Steps:

1. Rename and re-key `EntityCache`; add the snapshot `WeakMap`; add `refresh()`.
2. Introduce `UnitOfWork` and move the cascade logic out of `AbstractRepository`.
3. Change detection in `Update`: accept a column subset.
4. Multi-row grouping in `Insert` and `Delete`.
5. Honour `isAutoIncrement` in `EntitySchema::insertableColumns` and `Insert::updateId()`.
6. Join-table diffing.

Files: `src/Entity/EntityCache.php`, new `src/UnitOfWork/*`,
`src/Repository/AbstractRepository.php`, `src/Query/Insert.php`, `src/Query/Update.php`,
`src/Query/Delete.php`, `src/Schema/EntitySchema.php`, `src/ORM.php`.

Tests: `persist()` then `findOne()` returns the same instance; `delete()` then `findOne()`
returns null even if a row with that id reappears; an update with no changes issues no query;
a UUID-keyed entity round-trips; 2000 persists in one flush issue a single insert statement.

Acceptance: 2000 new entities persisted and flushed in at most 2 statements; updating one
field of one entity emits an `UPDATE` with one column.

## Workstream 5: connection layer with statement cache

Goal: cut the per-query round trips and stop paying for connections that are never used.

Design:

- `DatabaseInterface` gains `connect()`, `execute(string $sql, array $params): PDOStatement`
  and `prepareCached(string $sql): PDOStatement`. `getPdo()` connects on first call.
- `prepareCached()` keeps prepared statements keyed by SQL string in a bounded LRU map
  (default 256). The batch loader in workstream 3 and the identity lookups in workstream 4
  issue a handful of distinct SQL strings, so they hit the cache almost always.
- `AbstractQuery::query()` and the ad-hoc `prepare()` calls in `Mapper` and
  `AbstractRepository` all go through `execute()`.
- `ATTR_EMULATE_PREPARES` stays `false`: typed results (ints as int) are what the hydrators
  rely on, and the statement cache removes the round-trip cost that emulation would otherwise
  buy.

Steps:

1. Add the three methods to the interface and `AbstractDatabase`; make the constructor store the
   DSN and options only.
2. Route every `prepare()` in `src/` through `execute()`.
3. Add cache size to the database constructors.

Files: `src/Database/*`, `src/Query/AbstractQuery.php`, `src/Query/Select.php`,
`src/Query/Insert.php`, `src/Query/Update.php`, `src/Query/Delete.php`,
`src/Mapper/Mapper.php`, `src/Repository/AbstractRepository.php`.

Tests: constructing a `Database` does not open a connection; two executions of the same SQL
prepare once; the cache evicts at its bound.

Acceptance: 200 lazy loads of the same SQL shape prepare at most once.

## Workstream 6: CI coverage for MySQL and PostgreSQL

The PostgreSQL integration tests exist and read `POSTGRES_*` environment variables but skip in
CI because the workflow has no database service. Bug 12 (native booleans on PostgreSQL) was
invisible to CI for that reason.

Done:

- `postgres:16` and `mysql:8` services run in the PHPUnit job of `.github/workflows/ci.yml`,
  which exports the connection variables and sets `ORM_TEST_REQUIRE_DATABASES=1` so an
  unreachable database fails the build instead of skipping.
- `MySqlIntegrationTest` and `PostgresIntegrationTest` both extend
  `tests/AbstractDriverIntegrationTestCase.php`, so every driver test runs on both servers.
  Fixtures are `database_<name>_<driver>.sql`. Beyond the original CRUD cases, the shared
  suite covers empty `IN`, bool binding, `IS NULL`, `orWhere`, `count()` with limits,
  relation-path joins and `with()`.
- `MySqlDatabase` gained a `port` parameter.

Remaining, owned by workstreams 3 and 4: add their query-count and identity assertions to
`AbstractDriverIntegrationTestCase` (alongside the SQLite `IntegrationTest`) so they run on
all three drivers.

## Migration notes for the release

To be written before tagging, covering: the `Collection` interface change, `fetchAll()` return
type, extension mapper signature, schema dumping, shared relation proxies and identity,
`Collection::isInitialized()`, the removed `EntityFactory` / `EntityReflection` / `Mapper` and
the changed query constructors, deferred flushing, and the lazy connection.
