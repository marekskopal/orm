# ORM analysis: bugs, performance, proposed architecture

Analysis date: 2026-09-28. Baseline: 204 tests pass (6 Postgres tests skipped), PHPStan max level clean.
All bugs below were reproduced with probe scripts against SQLite unless marked otherwise.
Benchmarks ran on SQLite in-memory, PHP 8.5, 20k rows.

## Confirmed bugs

Ranked by impact.

1. **`orWhere` inside a nested builder matches every row.** `WhereBuilder::build()` (`src/Query/Where/WhereBuilder.php:99`) substitutes `1` when there is no AND part, producing `WHERE (1 OR name=?)`. A where callback that only calls `orWhere()` silently returns the whole table.

2. **Two relations to the same table, or a self-join, generate broken SQL.** Aliases come from the table name (`src/Schema/Builder/EntitySchemaFactory.php:55`), so `where(['employer.name' => .., 'client.name' => ..])` emits two `LEFT JOIN companies c` and fails with "ambiguous column". `parent.name` on a self-referencing entity joins `people p` onto `people p`. The README's `children` example is exactly this shape.

3. **Int-backed enums cannot be read.** `src/Mapper/Mapper.php:94` forces `checkString`, so an `enum Level: int` on an int column throws "Value is not string" on every hydration.

4. **`where(['col' => false])` matches nothing.** PDO binds `false` as an empty string, so `active = ''` returns zero rows on every driver. `getScalarParamsValues` (`WhereBuilder.php:197`) needs a bool to int step. `where(['col' => null])` throws a TypeError instead of producing `IS NULL`.

5. **`IN` with an empty array emits `IN ()`.** SQLite tolerates it, MySQL and Postgres raise a syntax error (`WhereBuilder.php:134`). It should short-circuit to `1=0`.

6. **`Select` is mutated by terminal calls.** `count()` overwrites the column list permanently (`src/Query/Select.php:206`), so a later `fetchAll()` on the same builder crashes with a TypeError on the missing id. `fetchOne()` sets `LIMIT 1` permanently (`Select.php:160`), so a later `fetchAll()` yields one row.

7. **Inserted entities never enter the identity map.** After `persist()` of a new entity, `findOne()` for that id hydrates a second object, so `===` fails and two divergent copies of the same row exist. Deletes also never evict.

8. **Manually assigned primary keys are impossible.** `insertableColumns` always excludes the PK (`src/Schema/EntitySchema.php:47`), and `isAutoIncrement` is never read anywhere. On MySQL, `Insert.php:101` then overwrites the user's PK with `lastInsertId()`. UUID primary keys also cannot work because `EntityCache` is typed `int $id`.

9. **Bare where keys are column names, dotted keys are property names.** `where(['employer' => 1])` fails with "no such column", while `where(['employer.name' => ..])` resolves `employer` as a property. The last segment of a dotted path is looked up by column name (`Select.php:282`). The README's `first_name` examples reflect this. It also means `parseColumn` cannot express anything for camelCase columns without `RawExpression`.

10. **`Collection` cannot be iterated in a nested loop.** It implements `Iterator` on a shared array pointer (`src/Mapper/Collection.php:29`), so an inner `foreach` over the same instance exhausts the outer one. A 3x3 nested loop produced 3 iterations, not 9.

11. **Documented API does not exist.** README shows `select()->where()->orWhere()` and a 3-argument `where('address_id', 'in', $subquery)`. Neither compiles against `Select`.

12. **Postgres boolean columns likely crash on read.** pdo_pgsql returns native `bool`, but `mapToProperty` is typed `string|int|float|null` under strict types. Untested here since the Postgres fixture uses `SMALLINT`, so treat as probable.

Smaller issues:

- Hydrating with `columns()` on a subset throws "not nullable" for every omitted column, so partial selects only work through `fetchAssoc*`.
- Cascade persist is one level deep because `persistEntity()` (`src/Repository/AbstractRepository.php:175`) does not recurse.
- The identity map returns stale objects after external updates with no `refresh()`.
- `ClassScanner` calls `new ReflectionClass` on every class in the entity path, so one non-autoloadable class in that directory breaks schema build.

## Performance findings

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

- **Relation proxies dominate hydration.** Each row with a ManyToOne allocates a lazy proxy, a closure capturing the mapper, and a fresh `ReflectionProperty` via `getProperty()` (`Mapper.php:205`). Each OneToMany allocates a lazy ghost `Collection` plus closure. That is the 45 ms to 174 ms jump and the 10x memory growth.
- **Per-column dispatch.** `EntityFactory::create` walks `ReflectionParameter` objects and calls the `match` in `mapToProperty` for every cell. That is the 4 ms to 45 ms gap on a trivial entity.
- **Cache hits still pay for the query.** A re-fetch of 20k already-cached rows took 19 ms because the SELECT runs and the rows are fetched, then discarded.
- **Every query is a fresh prepare.** With `ATTR_EMULATE_PREPARES=false` on MySQL and Postgres, each `prepare` is a server round trip. Lazy loads issue the identical `WHERE id = ?` SQL hundreds of times with no statement reuse.
- **Writes are all-or-nothing.** `Update` writes every column on every `persist()` with no change detection, and the repository inserts one row per call even though `Insert` supports multi-row. ManyToMany sync deletes all join rows then inserts one by one (`AbstractRepository.php:225`).
- **Schema is rebuilt per request.** `SchemaBuilder` tokenizes every file and reflects every class each time. 2.5 ms for 14 entities scales linearly and is pure waste under opcache.
- **Connection opens eagerly** in `AbstractDatabase::__construct` (`AbstractDatabase.php:18`), so container warm-up pays for a TCP handshake even on requests that never query.

## Proposed architecture

Breaking changes are acceptable, so the proposal restructures around five ideas. Together they address every performance item above and most of the bug list.

1. **Compiled schema with generated hydrators.** Keep the attribute scan as a build step, but have it emit one PHP file per entity containing a `Closure` bound to the entity's scope: `static fn(array $row): Entity`. The closure does direct casts, `Enum::from`, and `new DateTimeImmutable`, with no reflection or `match` at runtime. Also emit an extractor closure `static fn(Entity $e): array` for writes. Opcache then serves the schema for free, private and readonly properties become supported, and the hydrator measured above shows roughly 15x headroom.

2. **Relation resolution outside the entity.** Stop allocating a proxy per row. Store the raw foreign key in the row snapshot and create at most one proxy per distinct related id per class, registered in the identity map so the proxy is the canonical instance. Add a per-class pending set so that initializing any one proxy loads all pending ids of that class in a single `IN` query. That removes N+1 without `with()`, and it generalizes `with()` to OneToMany and ManyToMany by grouping child rows by foreign key. Keep `with('author.company')` nested paths as an explicit option.

3. **Unit of work with snapshots.** Keep the raw row array alongside each cached entity in a `WeakMap`. `persist()` registers the entity, `flush()` diffs extracted values against the snapshot, and emits only changed columns, batched multi-row inserts, and batched `DELETE ... IN`. Inserts register in the identity map and deletes evict. This fixes bug 7, makes cascade recursive, and turns 2000 prepares into a handful. The identity map should key by string so UUID and manual PKs work, and honor `isAutoIncrement` when deciding whether to send the PK.

4. **Immutable query builder with path-based aliases.** Make `where()`, `limit()`, `columns()` return clones, so `count()` and `fetchOne()` cannot poison the builder. Resolve every where key as a property path against the schema and assign aliases per relation path (`t0`, `t0_employer`, `t0_parent`) rather than per table. That fixes bugs 2, 6, and 9 in one change. In the same pass, add `IS NULL` handling, bool to int binding, empty `IN` short-circuit, and drop the `1 OR` fallback.

5. **Connection layer with statement cache.** Make `DatabaseInterface` connect lazily and expose `execute(string $sql, array $params)` that caches `PDOStatement` objects by SQL string. Lazy loads and batch loads then reuse one server-side prepared statement. Return `list<T>` from `fetchAll()` by default and offer `iterate()` for the streaming generator, since the one-shot `Iterator` is a recurring footgun for callers.

Suggested incremental order: 4 first, since it is where most user-visible bugs live and it does not depend on anything else; then 1 and 2 together, since the hydrator is where relation resolution lives; then 3; then 5. Items 1 and 2 are the ones that change the benchmark numbers materially.
