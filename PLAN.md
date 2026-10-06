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
| 4 | Unit of work and identity map | done |
| 5 | Connection layer with statement cache | done |
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

- (4) `UnitOfWork` schedules and flushes work; repository `persist()` and `delete()` flush it.
  `EntityCache` is now `IdentityMap` (`ORM::getIdentityMap()`) with normalised keys.
  `persist()` of an unmanaged entity with a non-auto-increment primary key inserts it with that
  key. `AbstractRepository` and `Delete` take extra constructor arguments.

- (5) `DatabaseInterface` gains `connect()`, `isConnected()`, `execute()`, `prepareCached()` and
  `clearStatementCache()`; `getPdo()` connects lazily. The database classes are no longer
  `readonly`.

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

Done. 2000 new entities flush with 1 statement (5 ms on SQLite; 2000 statements before), an
unchanged entity costs no query, and changing one field emits `UPDATE ... SET "email"=?`. Every
test listed above exists in `tests/UnitOfWork/UnitOfWorkTest.php`; batching, one-column updates
and join-table diffing also run on MySQL and PostgreSQL. Review bugs 7 and 8 are fixed.

Cost of change tracking, 20k rows, same benchmark as workstream 2:

| Scenario | Before | After |
|---|---|---|
| 2-column entity | 8.7 ms | 11.7 ms |
| 5 columns + 3 relations | 21.6 ms, 392 B | 24 ms, 673 B |
| Same, with a `DateTimeImmutable` column | 33.4 ms, 768 B | 37.4 ms, 1,097 B |

So the workstream 2 and 3 targets (under 10 ms, under 400 B) hold for hydration without tracking,
but not with it. A read-only query mode that skips snapshots would win this back.

How it differs from the design above:

- `flush()` writes only the entities passed to `persist()` or `remove()` and those their cascades
  reach (explicit change tracking), not every entity in the identity map. Otherwise a repository
  `persist($a)` would also write every other modified entity, and each flush would extract every
  loaded entity.
- Snapshots are the raw values of the updatable columns, recorded by the generated hydrator as a
  list. A third generated closure (`NormalizerGenerator`) turns them into the extractor's format on
  flush, only for the entities being flushed, and the diff is a strict comparison per column.
  Extracting every entity at hydration time would have cost more CPU per row.
- Snapshots are keyed by object id in an array, not a `WeakMap`. The identity map holds every
  entity with a snapshot strongly, so weak keys never fire, and writing arrays into a `WeakMap`
  queued each one for the cycle collector: rich hydration took 41 ms instead of 34 ms.
- New or existing is decided by whether the entity is managed (has a snapshot or is the registered
  proxy), not by whether its primary key is set. A hand-built entity with an auto-increment id
  already set is still updated in full, as before.
- `flush()` runs in a transaction when it writes more than one statement and none is open. A
  failed flush rolls back and keeps the work scheduled.
- Insert ordering uses dependency levels rather than a fixed owning-first order, so
  self-referencing entities (a category and its parent) insert correctly in one flush.
- `refresh()` lives on `UnitOfWork`. It cannot rewrite readonly properties, so those keep their
  value.
- Deleting an entity deletes join rows referencing it from either side of a ManyToMany relation,
  with or without cascade; before, only a cascade-remove owning side did.
- Repository `persist()` of a single entity is about 3 µs slower than before (cascade walk,
  managed check, identity registration), while batches through `flush()` are much faster.

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

Done. 200 lazy collection loads issue 200 executions and 1 prepare
(`tests/Database/StatementCacheIntegrationTest.php`). Measured against Docker containers on the
same machine, so real network latency would widen the gap:

| Scenario | Driver | Before | After |
|---|---|---|---|
| 2000 `findOne()` by id | MySQL 8 | 1,237 ms | 603 ms |
| | PostgreSQL 16 | 1,987 ms | 654 ms |
| | SQLite | 12.8 ms | 9.2 ms |
| 200 lazy collection loads | MySQL 8 | 176 ms | 84 ms |
| | PostgreSQL 16 | 190 ms | 62 ms |
| 2000 single `persist()` | MySQL 8 | 2,348 ms | 1,683 ms |
| | PostgreSQL 16 | 2,396 ms | 1,104 ms |

How it differs from the design above:

- A cached statement is shared by every caller with the same SQL, and re-executing it resets its
  cursor. Every path reads its result fully before returning except `Select::iterate()` and
  `iterateAssoc()`, which stream; they bypass the cache (`execute(..., cached: false)`), so a
  nested query with the same SQL cannot reset an outer loop. `execute()` closes a reused
  statement's cursor first, so rows a caller left unread (`fetchOne()`) do not leak into the
  next execution.
- `execute()` also turns driver errors into `QueryException` / `ConstrainException` with the SQL,
  so the query classes lost their own try/catch blocks, and the join-table statements of the
  unit of work (previously raw `PDO::prepare()`) now report errors the same way.
- `isConnected()` and `clearStatementCache()` were added: the first for tests and health checks,
  the second because cached statements can go stale after DDL on an open connection.
- `AbstractQuery` no longer fetches the PDO in its constructor, which would have connected as
  soon as a query object was built.
- `Mapper` no longer exists (workstream 2), and `AbstractRepository` no longer prepares
  statements (workstream 4); their statements moved to `UnitOfWork`, which uses `execute()`.

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

## Final review fixes

The final review of the branch found these, fixed before tagging:

1. A failed flush left assigned ids, identity-map entries and snapshots behind, so a retry turned
   re-inserts into no-op updates. `flush()` now keeps an undo journal and replays it on failure
   (not inside a transaction the caller opened).
2. A nullable inverse OneToOne was a proxy that threw when the row was missing. It now loads with
   its parent, batched in `hydrateAll()`, and is `null` when missing.
3. `refresh()` of a detached entity, and `IdentityMap::add()` replacing an instance, could leave
   snapshots for unregistered objects (object ids can be reused). Both now drop them.
4. `with()` stored preloaded collections and inverse entities for parents already hydrated; they
   were never consumed and later served stale. Existing parents now get their lazy collections
   filled in place, and nothing is stored for them.
5. A detached entity with a manual key was re-inserted (duplicate key). The flush now checks which
   such keys exist, one query per class, and updates those rows.
6. Removing uninitialised proxies loaded them to compute delete order; they are now ordered without
   being read.
7. The extractor ran twice per inserted entity; `Insert::getExtractedValues()` lets the unit of
   work reuse the values for the snapshot.
8. `with()` re-fetched owning relations already loaded; it now skips them unless nested paths
   need their rows.
9. `ExtensionMapperProvider` resolved schema, column and mapper per call; it now caches them.
10. `UnitOfWork::columnsOf()` dispatched on strings decoded with `constant()`; typed helpers
    replace it.

## Migration notes for the release

Written: [UPGRADE-2.0.md](UPGRADE-2.0.md). It opens with an upgrade checklist, then covers every
breaking change in the Unreleased section of CHANGELOG.md with code before and after, plus the
behaviour changes that need no code change. The 1.x behaviour it describes was checked against
the `v1.4.0` tag.
