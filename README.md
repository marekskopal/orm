# ORM

A lightweight Object-Relational Mapping (ORM) library for PHP.

## Features

- Simple and intuitive declaration of entities by adding `Column` Attributes to class properties
- Supports various property types including Uuid, DateTime and Enums
- Handles one-to-many and many-to-one relationships
- Query provider for database interactions
- [Migration module](https://github.com/marekskopal/orm-migrations) for creating and updating database schema
- Very fast in comparison to other ORM libraries - see [benchmarks](https://github.com/marekskopal/orm-benchmark)

## Installation

Install via Composer:

```bash
composer require marekskopal/orm
```

## Basic Usage
```php
//Create DB connection
$database = new MysqlDatabase('localhost', 'root', 'password', 'database');

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
use MarekSkopal\ORM\Enum\Type

#[Entity]
final class User
{
    #[Column(type: Type::Int, primary: true, autoIncrement: true)]
    public int $id;

    public function __construct(
        #[Column(type: Type::Timestamp)]
        public DateTimeImmutable $createdAt,
        #[Column(type: Type::String)]
        public string $name
        #[Column(type: Type::String, nullable: true, size: 50)]
        public string $email,
        #[Column(type: Type::Boolean)]
        public bool $isActive,
        #[ColumnEnum(enum: UserTypeEnum::class)]
        public UserTypeEnum $type,
    ) {
    }
```

Table and column names are derived from class name and parameters, but can be customized by providing additional parameters to attributes.

```php
#[Entity(table: 'users')]
final class User
{
    #[Column(type: Type::String, name: 'lastest_name')]
    public string $lastName;
}
```

### Relationships

You can define relationships between entities by adding `ManyToOne` or `OneToMany` attributes to class properties.

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
        $where->where(['first_name' => 'John']);
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

You can insert entities using `Insert` builder. Cascade insert is currently not supported.

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

You can update entities using `Update` builder. Cascade update is currently not supported.

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

You can delete entities using `Delete` builder. Cascade delete is currently not supported.

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

## Long-running applications

If you are using ORM in long-running PHP applications like Roadrunner or Swoole, you should call `clear` method on ORM cache after each request to free memory.

```php
$orm->getEntityCache()->clear();
```

