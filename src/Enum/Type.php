<?php

declare(strict_types=1);

namespace MarekSkopal\ORM\Enum;

enum Type: string
{
    case SmallInt = 'smallint';
    case Int = 'int';
    case BigInt = 'bigint';
    case Decimal = 'decimal';
    case Float = 'float';
    case Double = 'double';
    case String = 'string';
    case TinyText = 'tinytext';
    case Text = 'text';
    case MediumText = 'mediumtext';
    case LongText = 'longtext';
    case Boolean = 'boolean';
    case Uuid = 'uuid';
    case Binary = 'binary';
    case TinyBlob = 'tinyblob';
    case Blob = 'blob';
    case MediumBlob = 'mediumblob';
    case LongBlob = 'longblob';
    case Date = 'date';
    case DateTime = 'datetime';
    case Time = 'time';
    case Timestamp = 'timestamp';
    case Enum = 'enum';
    case Json = 'json';
}
