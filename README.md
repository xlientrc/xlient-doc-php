# Xlient PHP Markdown Documentation Generation Library

This library is used to generate documentation files for your PHP code in GitHub compatible Markdown.

Requires PHP 8.1 or higher.

## Install

```composer require xlient/doc-php```

## Setup
Any php files you wish to generate documents for must be already loaded or autoloadable.

It is recommended to install them with composer and then point the srcDir parameter to your package folder in /vendor.

```php
use Xlient\Doc\Php\Configuration;
use Xlient\Doc\Php\PhpDocumentor;

use const DIRECTORY_SEPARATOR as DS;

// Set current working directory to composer.json directory.
chdir(dirname(__DIR__));

// Include composer autoload file.
require_once getcwd() . DS . 'vendor' . DS . 'autoload.php';

// Configure the documentor.
$config = new Configuration(...);

// Instanciate the documentor.
$documentor = new PhpDocumentor(
    srcDirs: [
        getcwd() . '/vendor/package/name1/src',
        getcwd() . '/vendor/package/name2/src',
    ],
    destDir: getcwd() . '/docs',
    config: $config,
);

// Generate the documentation files.
$files = $documentor->make();
```

## Configuration
All configuration is set using the Configuration class.

The parameter order of the Configuration class `__construct` method is not guaranteed to remain consistent between versions. For this reason it is recommended to use named parameters.

```php
$config = new Configuration(
    baseNamespaces: [
        '\\Xlient'
    ],
    basePaths [
        '\\Xlient' => 'xlient'
    ],
    ...
);
```

### baseNamespaces

Only files within these namespaces will be documented.

When creating directories, the equivalent directory path of this namespace will be trimmed from the start of the path.

#### Default

```php
baseNamespace: [],
```

#### Example

```php
baseNamespace: [
    '\\Xlient'
],
```

With `baseNamespace: ['\\Xlient']`, the resulting directory path of `\\Xlient\\Doc\\Php` will become `/doc/php`.

### basePaths

These paths will be prepended to the start of any resulting relative documentation file path.

The array key is the namespace and the value the corresponding path.

#### Default

```php
basePaths: [],
```

#### Example

```php
basePaths: [
    '\\Xlient' => '/help'
],
```

With `basePaths: ['\\Xlient' => '/help']`, the resulting path of  `\\Xlient\\Doc\\Php\Configuration` will become `{destDir}/help/doc/php/configuration.md`

### baseUrls

This URL will be prepended to any URL paths generated for items that match a base namespace.

The array key is the namespace to match and the value the corresponding URL.

#### Default
```php
baseUrls: [],
```

#### Example

```php
baseUrls: [
    '\\Xlient' => 'https://xlient.com'
],
```

### pathFixes

An array of key value pairs to override the default path name generation.

By default, namespaces will be broken up based on case. So *MyName* will become *my-name*.

This isn't always the desirable outcome, so this option allows you to modify the namespace value to an alternative.

#### Default

```php
pathFixes: [],
```

#### Example
```php
pathFixes: [
    'MyName' => 'myname'
],
```

### namespaceUrls

An array of URLs to use for linking to items outside the base namespaces.

{php\_doc} will get replaced with a php.net style document path.

With `\\Random\\Engine\\Secure`, the resulting filename will be `class.random-engine-secure.php`

#### Default

```php
namespaceUrls: [
    '\\' => 'https://www.php.net/manual/en/{php_doc}',
    '\\Random\\' => 'https://www.php.net/manual/en/{php_doc}',
    '\\Psr\\' => 'https://www.php-fig.org',
    '\\Psr\\Cache\\' => 'https://www.php-fig.org/psr/psr-6',
    '\\Psr\\Clock\\' => 'https://www.php-fig.org/psr/psr-20',
    '\\Psr\\Container\\' => 'https://www.php-fig.org/psr/psr-11',
    '\\Psr\\EventDispatcher\\' => 'https://www.php-fig.org/psr/psr-14',
    '\\Psr\\Http\\Client\\' => 'https://www.php-fig.org/psr/psr-18',
    '\\Psr\\Http\\Message\\RequestFactoryInterface' => 'https://www.php-fig.org/psr/psr-17',
    '\\Psr\\Http\\Message\\ResponseFactoryInterface' => 'https://www.php-fig.org/psr/psr-17',
    '\\Psr\\Http\\Message\\ServerRequestFactoryInterface' => 'https://www.php-fig.org/psr/psr-17',
    '\\Psr\\Http\\Message\\StreamFactoryInterface' => 'https://www.php-fig.org/psr/psr-17',
    '\\Psr\\Http\\Message\\UploadedFileFactoryInterface' => 'https://www.php-fig.org/psr/psr-17',
    '\\Psr\\Http\\Message\\UriFactoryInterface' => 'https://www.php-fig.org/psr/psr-17',
    '\\Psr\\Http\\Message\\' => 'https://www.php-fig.org/psr/psr-7',
    '\\Psr\\Http\\Server\\' => 'https://www.php-fig.org/psr/psr-15',
    '\\Psr\\Link\\' => 'https://www.php-fig.org/psr/psr-13',
    '\\Psr\\Log\\' => 'https://www.php-fig.org/psr/psr-3',
    '\\Psr\\SimpleCache\\' => 'https://www.php-fig.org/psr/psr-16',
],
```

Any `namespaceUrls` set in the configuration will be merged with the default.

### urlCallback

A more precise method of providing external URLs.

#### Default

```php
urlCallback: null,
```

#### Example

`$url` will contain the any matching `namespaceUrls` value or `null` if no matches.

```php
urlCallback: function(string $name, ?string $url): ?string {
    if (str_starts_with($name, '\\Psr\\')) {
        $url = 'https://www.php-fig.org';
    }

    return $url;
},
```

### methodUrlCallback

A method of providing external URLs for external class methods.

#### Default

```php
methodUrlCallback: null,
```

#### Example

```php
methodUrlCallback: function(string $class, string $method): ?string {
    $url = null;
    // ...
    return $url;
},
```

### classMethodFiles

When true, separate files will be generated for each class method.

#### Default

```php
classMethodFiles: true,
```

### classPublic

When true, public class items will be included.

#### Default

```php
classPublic: true,
```

### classProtected

When true protected class items will be included.

#### Default

```php
classProtected: true,
```

### classPrivate

When true private class items will be included.

#### Default

```php
classPrivate: true,
```

### classSeparator

A string value to use to separate class names in the inheritance list.

#### Default

```php
classSeparator: ' » ',
```

### functionFiles

When true, separate files will be generated for each function.

```php
functionFiles: true,
```

### classFilenamePrefix

A value to prepend to a class documentation filename.

#### Default

```php
classFilenamePrefix: '',
```

#### Example

```php
classFilenamePrefix: 'class-',
```

### classFilenameSuffix

A value to append to a class documentation filename.

#### Default

```php
classFilenameSuffix : '',
```

### enumFilenamePrefix

A value to prepend to an enum documentation filename.

#### Default

```php
enumFilenamePrefix : '',
```

### enumFilenameSuffix

A value to append to an enum documentation filename.

#### Default

```php
enumFilenameSuffix : '',
```

### interfaceFilenamePrefix

A value to prepend to an interface documentation filename.

#### Default

```php
interfaceFilenamePrefix : '',
```

### interfaceFilenameSuffix

A value to append to an interface documentation filename.

#### Default

```php
interfaceFilenameSuffix : '',
```

### traitFilenamePrefix

A value to prepend to a trait documentation filename.

#### Default

```php
traitFilenamePrefix : '',
```

### traitFilenameSuffix

A value to append to a trait documentation filename.

#### Default

```php
traitFilenameSuffix : '',
```

### constantsFilename

The filename to use for a constants documentation file.

#### Default

```php
constantsFilename : 'constants',
```

### functionsFilename

The filename to use for a functions documentation file.

#### Default

```php
functionsFilename : 'functions',
```

### labels

An array of key value pairs to use for documentation labels.

#### Default

```php
labels: [
    'class' => 'Class',
    'extends' => 'Extends',
    'implements' => 'Implements',
    'uses' => 'Uses',
    'class_synopsis' => 'Class Synopsis',
    'constructor' => 'Constructor',
    'cases' => 'Cases',
    'case_details' => 'Case Details',
    'constants' => 'Constants',
    'constant_synopsis' => 'Constant Synopsis',
    'constant_details' => 'Constant Details',
    'properties' => 'Properties',
    'property_details' => 'Property Details',
    'methods' => 'Methods',
    'method_details' => 'Method Details',
    'functions' => 'Functions',
    'function_synopsis' => 'Function Synopsis',
    'function_details' => 'Function Details',
    'public' => 'Public',
    'protected' => 'Protected',
    'private' => 'Private',
    'name' => 'Name',
    'value' => 'Value',
    'type' => 'Type',
    'description' => 'Description',
    'returns' => 'Returns',
    'throws' => 'Throws',
    'deprecated' => 'Deprecated',
    'internal' => 'Internal',
    'generated' => 'Generated',
],
```

Any `labels` set in the configuration will be merged with the default.

### makeClassDescription

When true, the class documentation will include a description of the class.

#### Default

```php
makeClassDescription: true,
```

### makeClassExtends

When true, the class documentation will include a list of parent classes.

#### Default

```php
makeClassExtends: true,
```

### makeClassImplements

When true, the class documentation will include a list of implemented interfaces.

#### Default

```php
makeClassImplements: true,
```

### makeClassUses

When true, the class documentation will include a list of traits used by the class.

#### Default

```php
makeClassUses: true,
```

### makeClassConstructor

When true, the class constructor will have its own section in the documentation file.

#### Default

```php
makeClassConstructor: true,
```

### makeClassSynopsis

When true, a php.net style class index will be generated.

#### Default

```php
makeClassSynopsis: true,
```

### makeClassCases

When true, any case statements in an enum will be documented.

#### Default

```php
makeClassCases: true,
```

### makeClassCaseDetails

When true, a more detailed overview of each case in an enum will be documented.

#### Default

```php
makeClassCaseDetails: true,
```

### makeClassConstants

When true, any constants in a class will be documented.

#### Default

```php
makeClassConstants: true,
```

### makeClassConstantDetails

When true, a more detailed overview of each constant in a class will be documented.

#### Default

```php
makeClassConstantDetails: true,
```

### makeClassProperties

When true, any properties in a class will be documented.

#### Default

```php
makeClassProperties: true,
```

### makeClassPropertyDetails

When true, a more detailed overview of each property in a class will be documented.

#### Default

```php
makeClassPropertyDetails: true,
```

### makeClassMethods

When true, any methods in a class will be documented.

#### Default

```php
makeClassMethods: true,
```

### makeClassMethodDetails

When true, a more detailed overview of each method in a class will be documented.

#### Default

```php
makeClassMethodDetails: true,
```

### makeConstants

When true, any constants that are direct children of a namespace will be documented.

#### Default

```php
makeConstants: true,
```

### makeConstantsDescription

When true, the constant documentation will include a description of each constant.

#### Default

```php
makeConstantsDescription: true,
```

### makeConstantSynopsis

When true, a PHP code synopsis of the constants will be generated.

#### Default

```php
makeConstantSynopsis: true,
```

### makeConstantDetails

When true, a more detailed overview of each constant will be documented.

#### Default

```php
makeConstantDetails: true,
```

### makeDefines

When true, constants defined with the define function will be included in the constant documentation.

#### Default

```php
makeDefines: true,
```

### makeFunctions

When true, any functions that are direct children of a namespace will be documented.

#### Default

```php
makeFunctions: true,
```

### makeFunctionsDescription

When true, the function documentation will include a description for each function.

#### Default

```php
makeFunctionsDescription: true,
```

### makeFunctionSynopsis

When true, a PHP code synopsis of the functions will be generated.

#### Default

```php
makeFunctionSynopsis: true,
```

### makeFunctionDetails

When true, a more detailed overview of each function will be documented.

#### Default

```php
makeFunctionDetails: true,
```

### makeSynopsisMeta

When true, a JSON meta file will be generated with snyopsis URL metadata to allow for injecting links after code highlighting.

#### Default

```php
makeSynopsisMeta: true,
```

### showDeprecated

When true, deprecated items will be included in the documentation.

#### Default

```php
showDeprecated: true,
```

### showDeprecatedLabel

When true, a deprecated label will appear under deprecated items in the documentation.

#### Default

```php
showDeprecatedLabel: true,
```

### showInternal

When true, internal items will be included in the documentation.

#### Default

```php
showInternal: true,
```

### showInternalLabel

When true, an internal label will appear under internal items in the documentation.

#### Default

```php
showInternalLabel: true,
```

### showGenerated

When true, generated items will be included in the documentation.

#### Default

```php
showGenerated: true,
```

### showGeneratedLabel

When true, a generated label will appear under generated items in the documentation.

#### Default

```php
showGeneratedLabel: true,
```

### accessModifierOrder

An array of PHP access modifiers in the order in which they are to appear in.

#### Default

```php
use Xlient\Php\Doc\PhpAccessModifier;
//...
accessModifierOrder: [
    PhpAccessModifier::PUBLIC,
    PhpAccessModifier::PROTECTED,
    PhpAccessModifier::PRIVATE,
],
```

### typeOrder

An array of PHP types in the order in which they are to appear in.

#### Default

```php
use Xlient\Php\Doc\PhpType;
//...
typeOrder: [
    PhpType::NULL,
    PhpType::BOOL,
    PhpType::TRUE,
    PhpType::FALSE,
    PhpType::INT,
    PhpType::FLOAT,
    PhpType::STRING,
    PhpType::ARRAY,
    PhpType::ITERABLE,
    PhpType::CALLABLE,
    PhpType::OBJECT,
    PhpType::CLASS_NAME,
    PhpType::VOID,
    PhpType::SELF,
    PhpType::STATIC,
    PhpType::NEVER,
],
```

### sortByName

When true, items will be sorted by name.

#### Default

```php
sortByName: true,
```

### sortByAccessModifier

When true, items will be sorted by the specified `accessModifierOrder` order.

#### Default

```php
sortByAccessModifier: true,
```

### groupByAccessModifier

When true, items will be grouped by access modifiers.

#### Default

```php
groupByAccessModifier: true,
```

### sortByType

When true, types will be sorted by the specified `typeOrder` order.

#### Default

```php
sortByType: true,
```

### inheritDocComments

When true, child class PHPDoc comments will inherit missing information from its corresponding parent PHPDoc comment.

`@inheritDoc` and `{@inheritDoc}` will also be handled accordingly.

#### @inheritdoc / @inheritDoc

Will inherit the entire parent PHPDoc comment.

#### {@inheritdoc} / {@inheritDoc}

Will only inherit the description and inline it in accordance with the standard.

If it is the only text / tag in the PHPDoc comment, it will instead inherit the entire parent PHPDoc comment. This is non-standard, but a lot of code uses `{@inheritdoc}` this way.

#### Default

```php
inheritDocComments: true,
```

### prioritizeDocComments

When true, the information contained in the PHPDoc comment will take precedence over the information gotten from reflection.

#### Default

```php
prioritizeDocComments: true,
```

### escapeDocComments

When true, PHPDoc comment text will be escaped to not interfere with Markdown.

#### Default

```php
escapeDocComments = false,
```

### useNullableSyntax

When true, `?` will be used instead of `null` where appropriate.

#### Default

```php
useNullableSyntax: true,
```

### enableTables

When true, certain information will be placed in tables instead of a more mobile friendly headings and paragraphs.

#### Default

```php
enableTables: true,
```

### indentLength

The length in spaces to indent code by.

#### Default

```php
indentLength: 4,
```

### lineLength

The maximum length in characters a code line should be.

#### Default

```php
lineLength: 80,
```

### override

When true, any existing generated docs in the `destDir` will be removed before making.

#### Default

```php
override = false,
```
