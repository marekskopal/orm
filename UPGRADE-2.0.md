# Upgrading from 1.x to 2.0

Version 2.0 rewrites how entities are read, written and related, and makes the connection
lazy. Most applications need only the changes in the checklist below. The sections after it
explain each change, show the code before and after, and list behaviour that changed without
a change in the API.

PHP 8.4 or newer is still required.

## Checklist

1. Replace `$orm->getEntityCache()` with `$orm->getIdentityMap()`.
2. Remove `iterator_to_array()` around `fetchAll()`, `fetchAssocAll()` and `findAll()`. Use
   `iterate()` where you streamed a large result.
3. In custom repositories that override `findAll()`, change the return type to `array`. If they
   override the constructor, pass the new fourth argument, a `UnitOfWork`, to the parent.
4. Type relation properties as `Collection` (or `iterable`), not `\Iterator`. Replace
   `current()`/`next()`/`valid()` calls on collections with `foreach` or `toArray()`.
5. In extension mappers, widen the `$value` parameter of `mapToProperty()` to include `bool`.
6. If you construct `Select`, `Insert`, `Update`, `Delete`, `QueryProvider` or a query factory
   yourself, or implement `DatabaseInterface`, update those calls (see
   [Internal classes](#internal-classes-and-constructors)).
7. In production, dump the schema in your build step and load it with `Schema::fromFile()`
   (optional, but it removes the schema build from every request).
8. Read [Behaviour changes](#behaviour-changes): identity of related entities, what `persist()`
   writes, and when connection errors appear all changed.

## Fetching results

`Select::fetchAll()`, `Select::fetchAssocAll()` and `findAll()` return a list instead of a
one-shot iterator. The result can be counted, indexed and iterated more than once.

```php
// 1.x
$users = iterator_to_array($repository->findAll());

// 2.0
$users = $repository->findAll();
$first = $users[0] ?? null;
```

To process a result too large for memory, stream it. The generator can be consumed once.

```php
foreach ($queryProvider->select(User::class)->iterate() as $user) {
    // ...
}
```

`iterateAssoc()` streams raw rows the same way.

Custom repositories that override `findAll()` change the return type:

```php
// 1.x
public function findAll(array|callable $where = []): Iterator

// 2.0
/** @return list<User> */
public function findAll(array|callable $where = []): array
```

## Collections

`Collection` implements `IteratorAggregate` instead of `Iterator`, so every `foreach` gets its
own cursor and nested loops over one collection work. The cursor methods `current()`, `next()`,
`key()`, `valid()` and `rewind()` are gone; use `foreach` or `toArray()`.

Relation properties must be typed `Collection` or `iterable`:

```php
// 1.x
#[OneToMany(entityClass: Post::class)]
public \Iterator $posts;

// 2.0
/** @var Collection<Post> */
#[OneToMany(entityClass: Post::class)]
public Collection $posts;
```

A collection loaded from the database is lazy until first used. It is no longer a PHP lazy
ghost, so check its state with `isInitialized()`:

```php
// 1.x
new ReflectionClass(Collection::class)->isUninitializedLazyObject($author->posts);

// 2.0
!$author->posts->isInitialized();
```

## Extension mappers

`MapperInterface::mapToProperty()` receives native booleans, which pdo_pgsql returns for
`BOOLEAN` columns. Widen the parameter:

```php
// 1.x
public function mapToProperty(
    EntitySchema $entitySchema,
    ColumnSchema $columnSchema,
    string|int|float|null $value,
): string|int|float|bool|object|null

// 2.0
public function mapToProperty(
    EntitySchema $entitySchema,
    ColumnSchema $columnSchema,
    string|int|float|bool|null $value,
): string|int|float|bool|object|null
```

Extension mappers are otherwise unchanged and keep working.

## Identity map

`EntityCache` is now `IdentityMap`:

| 1.x | 2.0 |
|---|---|
| `$orm->getEntityCache()` | `$orm->getIdentityMap()` |
| `getEntity($class, $id)` | `get($class, $id)` |
| `addEntity($entity, $id)` | `add($entity, $id)` |
| `clear()` | `clear()` |

Keys may be integers, strings or UUIDs. Long-running applications (FrankenPHP, RoadRunner,
Swoole) still clear it after each request; it now also holds the snapshots used to detect
changes, so clearing matters more:

```php
$orm->getIdentityMap()->clear();
```

## Writing entities

Repository `persist()` and `delete()` still write immediately, but they now go through the unit
of work:

- `persist()` of an entity read from the database writes only the columns that changed. An
  unchanged entity costs no query.
- A new entity is registered in the identity map, so `findOne()` for its id returns the same
  instance. A deleted entity is removed from it.
- Cascades are followed recursively, and relations that were never loaded are skipped.
- A write of more than one statement (cascades, join rows) runs in a transaction unless one is
  already open.

To write many entities at once, schedule them and flush. 2,000 new entities of one class are
written with one statement:

```php
$unitOfWork = $orm->getUnitOfWork();
foreach ($rows as $row) {
    $unitOfWork->persist(new User($row['name']));
}
$unitOfWork->remove($obsoleteUser);
$unitOfWork->flush();
```

Repository `persist()` and `delete()` flush the unit of work, including anything scheduled on it
directly.

### Primary keys that are not auto-increment

A primary key without `autoIncrement: true`, such as a UUID, is now included in the insert and
never overwritten. An entity with such a key that was not read from the database is inserted:

```php
#[Column(type: Type::Uuid, primary: true)]
public UuidInterface $id;

$repository->persist(new Item(Uuid::uuid4(), 'Name'));
```

In 1.x the key was left out of the insert, and an entity whose key was already set was sent as
an `UPDATE`, which wrote nothing. If you relied on `persist()` updating an existing row through
a hand-built entity with a non-auto-increment key, load the entity first and change it.

Hand-built entities with an auto-increment id already set are still treated as existing rows and
updated in full.

## Relations

### Shared proxies and identity

A `ManyToOne` or `OneToOne` relation that is not loaded yet holds a proxy. There is now one proxy
per related id, and it is the instance the identity map returns for that id:

```php
$posts = $postRepository->findAll();
$posts[0]->author === $posts[1]->author;                       // true when both have author 1
$posts[0]->author === $authorRepository->findOne(['id' => 1]); // true
```

In 1.x each parent got its own proxy, and fetching the related entity directly returned yet
another instance. Code that compared instances, or that relied on two parents holding
different objects, must be checked.

### Batch loading

When one proxy is first read, every pending proxy of the same class is loaded with a single
`WHERE id IN (...)` query. Iterating 200 posts and reading `$post->author->name` costs two queries
instead of 201. An entity reached through a relation may therefore be loaded by a batch query
rather than its own `WHERE id = ?`; nothing changes in the entities you get.

### Eager loading

`with()` now accepts every relation kind and dotted paths, with one query per relation level.
In 1.x it threw for collections.

```php
// Authors and all their posts: two queries
$authors = $queryProvider->select(Author::class)->with('posts')->fetchAll();

// Tags, their users, and those users' addresses: three queries
$tags = $queryProvider->select(Tag::class)->with('users.address')->fetchAll();
```

## Hydration

Entities are hydrated by generated code that runs in the entity's scope, so `private`,
`protected` and `readonly` properties work. As before, the constructor is called with the mapped
values and constructor parameter names must match their property names.

## Schema caching

Building the schema scans entity files and reads attributes on every request. In production,
dump it once in a build or deploy step:

```php
// Build step
new SchemaBuilder()
    ->addEntityPath(__DIR__ . '/Entity')
    ->dump(__DIR__ . '/var/schema.php');

// Every request
$orm = new ORM($database, Schema::fromFile(__DIR__ . '/var/schema.php'));
```

Under opcache, loading the file needs no scan, reflection or code generation. Dump it again
whenever an entity changes. This step is optional: without it, `SchemaBuilder::build()` works
as before, and the generated code is compiled on first use.

## Connections

### The connection opens lazily

Creating `MySqlDatabase`, `PostgresDatabase`, `SqliteDatabase` or an `ORM` no longer connects.
The connection opens on the first query. As a result, a wrong host or password no longer throws
in the constructor but on the first query. To fail early, connect explicitly:

```php
$database = new MySqlDatabase('localhost', 'user', 'password', 'app');
$database->connect(); // throws PDOException if the server is unreachable
```

### Statement cache

Each distinct SQL string is prepared once and reused, from a cache of the 256 most recently used
statements. Set the size with the last constructor argument, or `0` to disable it:

```php
$database = new PostgresDatabase('localhost', 'user', 'password', 'app', statementCacheSize: 512);
```

After changing the schema on an open connection, for example in a migration run in the same
process, clear the cache, since cached statements may refer to the old table definitions:

```php
$database->clearStatementCache();
```

## Internal classes and constructors

These changes only matter if you construct the ORM's internal classes yourself or implement its
interfaces.

- `EntityFactory`, `EntityReflection` and `Mapper` are removed. Hydration is generated per entity
  and relations are resolved by `RelationResolver`.
- `QueryProvider`, `SelectFactory` and `Select` take a `RelationResolver` instead of an
  `EntityFactory`. `InsertFactory`, `UpdateFactory`, `Insert` and `Update` take a
  `SchemaProvider` instead of a `Mapper`. `Delete` takes a `SchemaProvider` as a fifth argument.
- `ExtensionMapperProvider` takes a `SchemaProvider`.
- `SchemaProvider`, `AbstractDatabase` and the database classes are no longer `readonly`.
  Subclasses must drop `readonly` too.
- `AbstractRepository` takes a `UnitOfWork` as its fourth constructor argument. `ORM` passes it.
  Repositories that override the constructor must accept and forward it.
- `DatabaseInterface` gains `connect()`, `isConnected()`, `execute()`, `prepareCached()` and
  `clearStatementCache()`. A custom implementation is easiest to keep working by extending
  `AbstractDatabase`, which implements all of them.
- The `Update` builder binds positional parameters (`SET "email"=?`) instead of named ones, and
  `Update::values()` restricts it to the given columns.

## Behaviour changes

These need no code change, but they change what the ORM does:

- **Related entities are shared.** See [Shared proxies and identity](#shared-proxies-and-identity).
- **Updates write only changed columns.** A column changed in the database by someone else
  after you read the entity, and not changed by you, is no longer overwritten when you persist.
- **Join rows follow deletes.** Deleting an entity deletes the `ManyToMany` join rows referencing
  it, from either side of the relation, with or without cascade remove. In 1.x only a
  cascade-remove owning side did.
- **Join rows are synced by difference.** Persisting a changed `ManyToMany` collection deletes
  and inserts only the rows that differ, instead of deleting all and inserting each.
- **Nullable `OneToOne` foreign keys.** A `NULL` foreign key on a nullable owning `OneToOne`
  hydrates as `null`. In 1.x it produced a proxy for id `0`.
- **Connection errors.** They appear on the first query instead of in the constructor.
- **Reads cost a little more memory.** Change detection keeps a snapshot of each hydrated
  entity, about 45 bytes per mapped column, until the identity map is cleared.
- **Cloning a `Select`** copies its where conditions, so a clone can be changed without
  affecting the original. In 1.x the clone shared them.
