# ORM

A lightweight Object-Relational Mapping (ORM) library for PHP.

## Features

- Simple and intuitive declaration of entities by adding `Column` Attributes to class properties
- Supports various property types including Uuid, DateTime and Enums
- Handles one-to-many, many-to-one, one-to-one, and many-to-many relationships
- Query provider for database interactions
- [Migration module](https://github.com/marekskopal/orm-migrations) for creating and updating database schema
- Very fast in comparison to other ORM libraries - see [benchmarks](https://github.com/marekskopal/orm-benchmark)

## Supported Databases

- MySQL
- PostgreSQL
- SQLite

## Installation

Install via Composer:

```bash
composer require marekskopal/orm
```

## Basic Usage
```php
//Create DB connection - MySQL
$database = new MysqlDatabase('localhost', 'root', 'password', 'database');

//Create DB connection - PostgreSQL
$database = new PostgresDatabase('localhost', 'postgres', 'password', 'database');

//Create DB connection - SQLite
$database = new SqliteDatabase('/path/to/database.sqlite');

//Create schema
$schema = new SchemaBuilder()
    ->addEntityPath(__DIR__ . '/Entity')
    ->build();
    
$orm = new ORM($database, $schema);

//Create new entity
$user = new User(
    'John',
    'Doe',
);
$orm->getRepository(User::class)->persist($user);

//Find entity by id
$user = $orm->getRepository(User::class)
    ->findOne(['id' => 1]);

//Update entity
$user->firstName = 'Jane';
$orm->getRepository(User::class)->persist($user);

//Delete entity
$orm->getRepository(User::class)->delete($user);
```

## Entity Declaration

You can declare entities by adding `Entity` attribute to class and `Column` attribute to class properties.

```php
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;

#[Entity]
final class User
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::Timestamp)]
        public DateTimeImmutable $createdAt,
        #[Column(type: Type::String)]
        public string $name,
        #[Column(type: Type::String, nullable: true, size: 50)]
        public ?string $email,
        #[Column(type: Type::Boolean)]
        public bool $isActive,
        #[ColumnEnum(enum: UserTypeEnum::class)]
        public UserTypeEnum $type,
    ) {
    }
}
```

Table and column names are derived from class name and parameters, but can be customized by providing additional parameters to attributes.

```php
#[Entity(table: 'users')]
final class User
{
    #[Column(type: Type::String, name: 'my_last_name_column')]
    public string $lastName;
}
```

### Relationships

#### ManyToOne and OneToMany

```php
#[Entity]
final class User
{
    #[ManyToOne(entityClass: Address::class)]
    public Address $address;

    #[OneToMany(entityClass: User::class)]
    public \Iterator $children;
}
```

#### OneToOne

Use `#[OneToOne]` for a unique relationship between two entities. The owning side holds the foreign key column; the inverse side uses `mappedBy` pointing to the owning property name.

```php
#[Entity]
final class User
{
    // Owning side — stores profile_id column
    #[OneToOne(entityClass: Profile::class)]
    public Profile $profile;
}

#[Entity]
final class Profile
{
    // Inverse side — no column, loaded via User::$profile FK
    #[OneToOne(entityClass: User::class, mappedBy: 'profile')]
    public ?User $user;
}
```

#### ManyToMany

Use `#[ManyToMany]` for a join-table relationship. The owning side declares the join table and column names; the inverse side uses `mappedBy`.

Column names default to the entity short name with an `Id` suffix (e.g. `user_id`, `tag_id`) when not specified explicitly.

```php
#[Entity]
final class User
{
    // Owning side — manages the user_tags join table
    #[ManyToMany(
        entityClass: Tag::class,
        joinTable: 'user_tags',
        joinColumn: 'user_id',        // defaults to user_id
        inverseJoinColumn: 'tag_id',  // defaults to tag_id
    )]
    public Collection $tags;
}

#[Entity]
final class Tag
{
    // Inverse side — loaded via User::$tags join table info
    #[ManyToMany(entityClass: User::class, mappedBy: 'tags')]
    public Collection $users;
}
```

### Cascade Operations

Relations support cascade operations via the `cascade` parameter. Supported values are `CascadeEnum::Persist` and `CascadeEnum::Remove`.

```php
use MarekSkopal\ORM\Schema\Enum\CascadeEnum;

#[Entity]
final class Author
{
    #[OneToMany(entityClass: Post::class, cascade: [CascadeEnum::Persist, CascadeEnum::Remove])]
    public Collection $posts;
}
```

**`CascadeEnum::Persist`** — when `persist()` is called on the owning entity, all related entities are persisted automatically:

```php
$post1 = new Post('First Post', $author);
$post2 = new Post('Second Post', $author);
$author = new Author('John', new Collection([$post1, $post2]));

// Persists author and both posts in the correct order
$orm->getRepository(Author::class)->persist($author);
```

**`CascadeEnum::Remove`** — when `delete()` is called on the owning entity, all related entities are deleted automatically before the owner (to avoid FK constraint violations):

```php
$author = $orm->getRepository(Author::class)->findOne(['id' => 1]);

// Deletes all posts belonging to the author, then deletes the author
$orm->getRepository(Author::class)->delete($author);
```

Cascade is supported on `OneToMany`, `ManyToOne`, `OneToOne`, and `ManyToMany` relations. For `ManyToMany`, cascade remove deletes the join table rows; cascade persist syncs the join table after persisting.

### Dates

You can use `DateTime` or `DateTimeImmutable` properties in entities. The library will automatically convert datetime or timestamp columns values to those to objects.

```php
#[Entity]
final class User
{
    #[Column(type: Type::Timestamp)]
    public DateTimeImmutable $createdAt;
    
    #[Column(type: Type::DateTime)]
    public DateTime $updatedAt;
}
```

### Enums

You can use native PHP enums in entities. The library will automatically convert enum column values to enum objects.

```php
use MarekSkopal\ORM\Attribute\ColumnEnum;

#[Entity]
final class User
{
    #[ColumnEnum(enum: UserTypeEnum::class)]
    public UserTypeEnum $type;
}
```

## Repository declaration

You can declare you repositories by extending `AbstractRepository` class and providing repository class in `Entity` attribute on entity class.

```php
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<User> */
class UserRepository extends AbstractRepository
{

}
```

```php
#[Entity(repositoryClass: UserRepository::class)]
final class User
{

}
```

## Queries

You can use `QueryProvider` to create queries.

```php
$queryProvider = $orm->getQueryProvider();
```

### Select

You can create select queries using `Select` builder.

```php
$user = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->fetchOne();
```

#### Where

You can use `where` method to filter results. 

Multiple AND conditions can be crated either by passing array of conditions or by chaining `where` method.

```php

//Array of conditions
$user = $queryProvider->select(User::class)
    ->where([
        'id' => 1,
        'isActive' => true
    ])
    ->fetchOne();

//Chained conditions
$user = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->where(['isActive' => true])
    ->fetchOne();
```

OR conditions can be created by using `orWhere` method.

```php
$user = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->orWhere(['first_name' => 'John'])
    ->fetchOne();
```

You can also use `where` method with nested conditions by passing function.

```php

// Create nested condition: (id = 1 AND (first_name = 'John' OR last_name = 'Doe'))
$user = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->where(function (Where $where) {
        $where->where(['first_name' => 'John'])
            ->orWhere(['last_name' => 'Doe']);
    })
    ->fetchOne();
 ```   
You can pass another instance of `Select` object to where method to create subquery.

```php
$subquery = $queryProvider->select(Address::class)
    ->columns(['user_id'])
    ->where(['city' => 'Brno']);
    
$user = $queryProvider->select(User::class)
    ->where('address_id', 'in', $subquery)
    ->fetchOne();
```

### Insert

You can insert entities using `Insert` builder.

```php
$user = new User(
    'John',
    'Doe',
);

$queryProvider->insert(User::class)
    ->entity($user)
    ->execute();

//created entity will have id set automatically
```

#### Insert multiple entities

You can insert multiple entities at once in one insert query.

```php
   
$userA = new User(
    'John',
    'Doe',
);

$userB = new User(
    'Jane',
    'Doe',
);

$queryProvider->insert(User::class)
    ->entity($userA)
    ->entity($userB)
    ->execute();
```    

### Update

You can update entities using `Update` builder.

```php
$user = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->fetchOne();

$user->firstName = 'Jane';

$queryProvider->update(User::class)
    ->entity($user)
    ->execute();
```

### Delete

You can delete entities using `Delete` builder.

```php
$user = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->fetchOne();
    
$queryProvider->delete(User::class)
    ->entity($user)
    ->execute();
```

#### Delete multiple entities

You can delete multiple entities at once in one delete query.

```php
   
$userA = $queryProvider->select(User::class)
    ->where(['id' => 1])
    ->fetchOne();

$userB = $queryProvider->select(User::class)
    ->where(['id' => 2])
    ->fetchOne();

$queryProvider->delete(User::class)
    ->entity($userA)
    ->entity($userB)
    ->execute();
```

## Transactions

Use `getTransactionProvider()` to run operations inside a database transaction. The callback is committed on success and automatically rolled back if any exception is thrown.

```php
$orm->getTransactionProvider()->transaction(function () use ($orm): void {
    $orm->getRepository(User::class)->persist($userA);
    $orm->getRepository(User::class)->persist($userB);
});
```

You can also manage the transaction manually:

```php
$tp = $orm->getTransactionProvider();

$tp->beginTransaction();

try {
    $orm->getRepository(User::class)->persist($user);
    $tp->commit();
} catch (\Throwable $e) {
    $tp->rollback();
    throw $e;
}
```

Nesting transactions is not supported — calling `transaction()` inside an active transaction throws a `TransactionException`.

## Long-running applications

If you are using ORM in long-running PHP applications like FrankenPHP, Roadrunner or Swoole, you should call `clear` method on ORM cache after each request to free memory.

```php
$orm->getEntityCache()->clear();
```

