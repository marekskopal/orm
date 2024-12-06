# ORM

A lightweight Object-Relational Mapping (ORM) library for PHP.

## Features

- Simple and intuitive declaration of entities by adding `Column` Attributes to class properties
- Supports various property types including Uuid, DateTime and Enums
- Handles one-to-many and many-to-one relationships
- Query provider for database interactions

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
