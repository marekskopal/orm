# Security Analysis — MarekSkopal\ORM

Date: 2026-06-12
Scope: full `src/` tree at commit `8bfabb2`. Focus areas: SQL injection in the query
layer, identifier handling, connection configuration, data integrity, and information
disclosure.

## Summary

Data **values** are handled safely throughout the library — every query builder binds
values via PDO prepared-statement placeholders, `LIMIT`/`OFFSET` are strictly typed as
`int`, and `ORDER BY` direction is validated through a backed enum. The main risk
surface is **identifiers and operators**: column names, the where-operator string, and
any column expression containing `(` are concatenated into SQL with no sanitization.
An application that forwards user input into `orderBy()`, `columns()`, `groupBy()`,
or the column/operator positions of `where()` is injectable. Since ORMs are commonly
assumed to be safe in exactly these places, these should be fixed or loudly documented.

| # | Finding | Severity | Status |
|---|---------|----------|--------|
| 1 | Raw passthrough of column expressions containing `(` | High | **Fixed** — identifiers validated, explicit `RawExpression` API added |
| 2 | Identifier quoting does not escape embedded quote characters | High | **Fixed** — `QuoteUtils::quote()` doubles embedded quote chars |
| 3 | Where-condition operator concatenated into SQL unchecked | High | **Fixed** — operator allowlist in `WhereBuilder` |
| 4 | MySQL connection: no charset in DSN, emulated prepares left enabled | Medium | **Fixed** — `charset=utf8mb4` in DSN, native prepares |
| 5 | Batch insert ID assignment via `lastInsertId() + i` (wrong-row writes) | Medium | **Mitigated** — SQLite uses `RETURNING`; MySQL keeps the multi-row insert by design (requires `innodb_autoinc_lock_mode` ≤ 1, documented in README) |
| 6 | Full SQL retained on exceptions (disclosure if surfaced to users) | Low | **Fixed** — documented in README "Security considerations" |
| 7 | `LIKE` values: wildcards not escaped | Info | **Fixed** — documented in README "Security considerations" |

While fixing finding 4, the tests surfaced an additional pre-existing bug: both
database drivers merged PDO options with array spread, which re-indexes integer
keys, so `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION` was silently dropped in
`PostgresDatabase`. Fixed with `array_replace()`.

---

## 1. Raw passthrough of column expressions containing `(` — **High**

`Select::parseColumn()` returns the input string **verbatim** whenever it contains a
parenthesis (`src/Query/Select.php:238-241`):

```php
if ($partsCount === 1) {
    if (str_contains($column, '(')) {
        return $column;          // <-- no escaping, no validation
    }
    ...
```

This is presumably an escape hatch for expressions like `count(*)`, but `parseColumn()`
is the single entry point for column names from `where()`, `orderBy()`, `columns()`,
and `groupBy()`. Any of these is therefore a direct SQL injection sink when fed user
input — the common pattern of `->orderBy($_GET['sort'])` is exploitable:

```php
$select->orderBy('(SELECT CASE WHEN (SELECT password FROM users LIMIT 1) > "m" THEN id ELSE name END)');
// or simply: ->columns(['(SELECT password FROM users LIMIT 1) AS name'])
```

In the `where()` column position it composes with finding 3 (the bound `?` still
follows, so the attacker only needs an expression that consumes it).

**Recommendation:** remove the implicit passthrough. Provide an explicit opt-in API for
raw expressions (e.g. a `RawExpression` value object or `Select::columnsRaw()`), so the
unsafe path cannot be reached by passing a plain string. For plain identifiers, validate
against `^[A-Za-z0-9_]+$` before quoting.

## 2. Identifier quoting does not escape embedded quote characters — **High**

`AbstractQuery::escape()` only wraps the name in the driver quote character
(`src/Query/AbstractQuery.php:31-34`):

```php
protected function escape(string $name): string
{
    return $this->identifierQuoteChar . $name . $this->identifierQuoteChar;
}
```

A name containing the quote character itself (backtick on MySQL, `"` on
Postgres/SQLite) breaks out of the quoted context. So even the "escaped" path of
`parseColumn()` is injectable:

```php
$select->orderBy('name` , (SELECT ...) -- ');   // MySQL: backtick closes the identifier
```

The same pattern is repeated for join-table identifiers built with `sprintf` in
`Mapper::mapRelationManyToManyToProperty()` (`src/Mapper/Mapper.php:244-252`) and
`AbstractRepository::syncManyToManyJoinTable()` (`src/Repository/AbstractRepository.php:199-209`).
Those names come from PHP attributes, so they are developer-controlled and low risk in
practice, but the helper itself is unsafe by construction.

**Recommendation:** double the quote character inside the name
(`str_replace($q, $q.$q, $name)`) — and/or reject identifiers that don't match
`^[A-Za-z0-9_]+$`. Apply it in `escape()` and in the two `sprintf`-based join-table
helpers.

## 3. Where-condition operator concatenated unchecked — **High**

`WhereBuilder::buildWhere()` places the operator string (`$condition[1]`) into the SQL
text as-is (`src/Query/Where/WhereBuilder.php:123-145`):

```php
$query[] = $column . $condition[1] . '?';
```

The tuple form `where([column, operator, value])` is exactly the shape applications use
to build filter endpoints from request data. A user-supplied operator such as
`'= ? OR 1=1 -- '` (which consumes the bound parameter itself) turns into a classic
injection. There is no allowlist — `IN` and `LIKE` are special-cased by string
comparison, everything else passes through.

**Recommendation:** validate the operator against an allowlist
(`=, !=, <>, <, <=, >, >=, LIKE, NOT LIKE, IN, NOT IN, IS, IS NOT`) and throw
`InvalidArgumentException` otherwise. This is cheap and breaks no legitimate usage.

## 4. MySQL connection hardening — **Medium**

`MySqlDatabase::getDsn()` builds `mysql:host=...;dbname=...` with **no `charset`**
(`src/Database/MySqlDatabase.php:14-17`), and unlike `PostgresDatabase`
(`src/Database/PostgresDatabase.php:40`), it does not set
`PDO::ATTR_EMULATE_PREPARES => false`, so the PDO MySQL default of **emulated prepares**
applies. Two consequences:

- With emulated prepares, "bound" values are actually escaped client-side and spliced
  into the SQL string. If the connection charset ends up as a vulnerable multibyte
  charset (e.g. GBK via `SET NAMES` or server default), the classic escaping-bypass
  injection becomes possible even for parameterized values.
- The effective connection charset is whatever the server defaults to, which is also a
  correctness problem for non-ASCII data.

**Recommendation:** append `;charset=utf8mb4` to the DSN (ideally as a constructor
parameter with that default) and add `PDO::ATTR_EMULATE_PREPARES => false` in a
`getOptions()` override, mirroring the Postgres driver. Native prepares also make the
parameter-count errors from findings 1–3 fail closed more often.

## 5. Batch insert ID assignment race — **Medium** (integrity)

`Insert::updateId()` assigns primary keys for multi-entity inserts as
`lastInsertId() + $i` when the driver has no `RETURNING` clause — i.e. on MySQL and
SQLite (`src/Query/Insert.php:90-96`). Auto-increment IDs are not guaranteed
contiguous: concurrent inserts, MySQL `innodb_autoinc_lock_mode=2`, or any trigger
inserting rows will interleave IDs. Entities then carry **another row's primary key**,
and a subsequent `persist()`/`delete()` silently updates or deletes the wrong record —
a data-integrity defect with real security impact (e.g. modifying another user's row).

**Recommendation:** on MySQL, rely on the documented guarantee only within a single
multi-row `INSERT` under the default lock mode and document that constraint — or fetch
IDs back explicitly; on SQLite, insert rows one at a time or use `RETURNING`
(supported since SQLite 3.35, and the codepath already exists via
`getInsertReturningClause()`).

## 6. SQL retained on exceptions — **Low**

`QueryException`/`ConstrainException` carry the full SQL text
(`src/Exception/QueryException.php:9-17`) and the message propagates the raw PDO
message. That is useful for debugging and fine for a library, but consuming
applications must not render these to end users (schema disclosure, query structure).

**Recommendation:** note in the README that `QueryException::getQuery()` and exception
messages are sensitive and should only be logged server-side.

## 7. `LIKE` wildcards not escaped — **Info**

`where([col, 'LIKE', $userInput])` binds the value safely, but `%` and `_` inside the
value act as wildcards (`src/Query/Where/WhereBuilder.php:140-143`). Not injection, but
can defeat "exact prefix" checks and enable expensive scans. Worth a documentation note
or an `escapeLike()` helper.

---

## Things that are done well

- **Values are consistently parameterized** — `WhereBuilder` collects all condition
  values (including subquery params, `DateTimeInterface`, `UuidInterface`,
  `BackedEnum`) into positional bindings; `Insert`/`Update`/`Delete` bind all data via
  placeholders.
- **`LIMIT`/`OFFSET` are `int`-typed** (`src/Query/Select.php:105-116`) and `ORDER BY`
  direction is validated through `DirectionEnum::from()` — no string interpolation.
- **Strict value/type validation** in `Mapper`/`ValidationUtils` before values reach
  the database; enum round-trips use `BackedEnum::from()`, rejecting unknown values.
- **Credentials** use `#[SensitiveParameter]` (`src/Database/AbstractDatabase.php:15-16`),
  keeping them out of stack traces; PDO runs with `ERRMODE_EXCEPTION`.
- **No dynamic code execution paths**: `ClassScanner` tokenizes files rather than
  including them; entity hydration goes through declared constructors/properties, so
  there is no PHP-object-injection style deserialization surface.
- **Identity map** (`EntityCache`) is keyed by class + primary key with no
  cross-entity leakage.

## Priority of fixes

1. Operator allowlist in `WhereBuilder` (finding 3) — smallest change, closes the most
   likely real-world misuse.
2. Identifier validation/quote-doubling in `escape()` + removal of the `(` passthrough
   in favor of an explicit raw-expression API (findings 1, 2).
3. MySQL DSN charset + native prepares (finding 4).
4. Batch-insert ID strategy (finding 5).
