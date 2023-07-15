<?php
namespace Xlient\Doc\Php;

/**
 * An enumeration representing the different PHP types.
 */
enum PhpType: string
{
    case NULL = 'null';
    case BOOL = 'bool';
    case TRUE = 'true';
    case FALSE = 'false';
    case INT = 'int';
    case FLOAT = 'float';
    case STRING = 'string';
    case ARRAY = 'array';
    case ITERABLE = 'iterable';
    case CALLABLE = 'callable';
    case OBJECT = 'object';
    case CLASS_NAME = 'class';
    case VOID = 'void';
    case STATIC = 'static';
    case SELF = 'self';
    case NEVER = 'never';
}

// ✝
