<?php

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
    case Text = 'text';
    case Boolean = 'boolean';
    case Uuid = 'uuid';
    case Binary = 'binary';
    case Blob = 'blob';
    case Date = 'date';
    case DateTime = 'datetime';
    case Time = 'time';
    case Timestamp = 'timestamp';
    case Enum = 'enum';
    case Json = 'json';
}
