<?php
namespace Xlient\Doc\Php;

use Closure;
use Xlient\Doc\Php\PhpAccessModifier;

use function array_map;
use function ksort;
use function rtrim;
use function trim;
use function Xlient\Doc\Php\clean_path as xlient_clean_path;
use function Xlient\Doc\Php\markdown_escape as xlient_markdown_escape;

/**
 * Stores configuration for the documentor.
 */
class Configuration
{
    /**
     * @param array<string> $baseNamespaces Only files within this namespace
     * will be documented.
     * @param array<string, string> $basePaths This path will be prepended to
     * the start of any resulting relative documentation file path.
     * @param array<string, string> $baseUrls This url will be prepended to any
     *  urls generated for items that match the base namespace.
     * @param array<string, string> $pathFixes An array of key value pairs to
     *  override the default path name generation.
     * @param array<string, string> $namespaceUrls An array of urls to use for
     *  linking to items outside the base namespace.
     * @param Closure|null $urlCallback A more precise method of providing
     *  external urls.
     * @param Closure|null $methodUrlCallback A more precise method of
     *  providing external urls for class methods.
     * @param bool $classMethodFiles When true, separate files will be
     *  generated for each class method.
     * @param bool $classPublic When true, public class items will be included.
     * @param bool $classProtected When true protected class items will be
     *  included.
     * @param bool $classPrivate When true, private class items will be
     *  included.
     * @param string $classSeparator A string value to use to separate class
     *  names in the inheritance list.
     * @param bool $functionFiles When true, separate files will be generated
     *  for each function.
     * @param string $classFilenamePrefix A value to prepend to a class
     *  documentation filename.
     * @param string $classFilenameSuffix A value to append to a class
     *  documentation filename.
     * @param string $enumFilenamePrefix A value to prepend to an enum
     *  documentation filename.
     * @param string $enumFilenameSuffix A value to append to an enum
     *  documentation filename.
     * @param string $interfaceFilenamePrefix A value to prepend to an
     *  interface documentation filename.
     * @param string $interfaceFilenameSuffix A value to append to an
     *  interface documentation filename.
     * @param string $traitFilenamePrefix A value to prepend to a trait
     *  documentation filename.
     * @param string $traitFilenameSuffix A value to append to a trait
     *  documentation filename.
     * @param string $constantsFilename The filename to use for a constants
     *  documentation file.
     * @param string $functionsFilename The filename to use for a functions
     *  documentation file.
     * @param array<string, string> $labels An array of key value pairs to use
     *  for documentation labels.
     * @param bool $makeClassDescription When true, the class documentation
     *  will include a description of the class.
     * @param bool $makeClassExtends When true, the class documentation will
     *  include a list of parent classes.
     * @param bool $makeClassImplements When true, the class documentation will
     *  include a list of implemented interfaces.
     * @param bool $makeClassUses When true, the class documentation will
     *  include a list of traits used by the class.
     * @param bool $makeClassConstructor When true, the class constructor will
     *  have its own section in the documentation file.
     * @param bool $makeClassSynopsis When true, a php.net style class index
     *  will be generated.
     * @param bool $makeClassCases When true, any case statements in an enum
     *  will be documented.
     * @param bool $makeClassCaseDetails When true, a more detailed overview
     *  of each case in an enum will be documented.
     * @param bool $makeClassConstants When true, any constants in a class
     *  will be documented.
     * @param bool $makeClassConstantDetails When true, a more detailed
     *  overview of each constant in a class will be documented.
     * @param bool $makeClassProperties When true, any properties in a class
     *  will be documented.
     * @param bool $makeClassPropertyDetails When true, a more detailed
     *  overview of each property in a class will be documented.
     * @param bool $makeClassMethods When true, any methods in a class will be
     *  documented.
     * @param bool $makeClassMethodDetails When true, a more detailed overview
     *  of each method in a class will be documented.
     * @param bool $makeConstants When true, any constants that are direct
     *  children of a namespace will be documented.
     * @param bool $makeConstantsDescription When true, the constant
     *  documentation will include a description of each constant.
     * @param bool $makeConstantSynopsis When true, a PHP code synopsis of the
     *  constants will be generated.
     * @param bool $makeConstantDetails When true, a more detailed overview of
     *  each constant will be documented.
     * @param bool $makeDefines When true, constants defined with the define
     *  function will be included in the constant documentation.
     * @param bool $makeFunctions When true, any functions that are direct
     *  children of a namespace will be documented.
     * @param bool $makeFunctionsDescription When true, the function
     *  documentation will include a description for each function.
     * @param bool $makeFunctionSynopsis When true, a PHP code synopsis of the
     *  functions will be generated.
     * @param bool $makeFunctionDetails When true, a more detailed overview of
     *  each function will be documented.
     * @param bool $makeSynopsisMeta When true, a JSON meta file will be
     *  generated with snyopsis URL metadata to allow for injecting links
     *  after code highlighting.
     * @param bool $showDeprecated When true, deprecated items will be
     *  included in the documentation.
     * @param bool $showDeprecatedLabel When true, a deprecated label will
     *  appear under deprecated items in the documentation.
     * @param bool $showInternal When true, internal items will be included in
     *  the documentation.
     * @param bool $showInternalLabel When true, an internal label will appear
     *  under internal items in the documentation.
     * @param bool $showGenerated When true, generated items will be included
     *  in the documentation.
     * @param bool $showGeneratedLabel When true, a generated label will appear
     *  under generated items in the documentation.
     * @param array<PhpAccessModifier> $accessModifierOrder An array of PHP
     *  access modifiers in the order in which they are to appear in.
     * @param array<PhpType> $typeOrder An array of PHP types in the order in
     *  which they are to appear in.
     * @param bool $sortByName When true, items will be sorted by name.
     * @param bool $sortByAccessModifier When true, items will be sorted by
     *  the specified accessModifierOrder order.
     * @param bool $groupByAccessModifier When true, items will be grouped by
     *  access modifiers.
     * @param bool $sortByType When true, types will be sorted by the
     *  specified typeOrder order.
     * @param bool $enableTables When true, certain information will be placed
     *  in tables instead of a more mobile friendly headings and paragraphs.
     * @param bool $inheritDocComment When true, items with \@inheritDoc tags
     *  will inherit documentation from its parent.
     * @param bool $prioritizeDocComment When true, the information contained
     *  in the PHPDoc comment will take precedence over the information gotten
     *  from reflection.
     * @param bool $useNullableSyntax When true, ? will be used instead of
     *  null where appropriate.
     * @param int $indentLength The length in spaces to indent code by.
     * @param bool $escapeDocComments When true, PHPDoc comment text will be
     *  escaped to not interfere with markdown.
     * @param bool $override When true, any existing generated docs will be
     *  removed before remaking.
     */
    public function __construct(
        public array $baseNamespaces = [],
        public array $basePaths = [],
        public array $baseUrls = [],

        public array $pathFixes = [],
        public array $namespaceUrls = [],
        public ?Closure $urlCallback = null,
        public ?Closure $methodUrlCallback = null,

        public bool $classMethodFiles = true,
        public bool $classPublic = true,
        public bool $classProtected = true,
        public bool $classPrivate = true,
        public string $classSeparator = ' » ',

        public bool $functionFiles = true,

        public string $classFilenamePrefix = '',
        public string $classFilenameSuffix = '',
        public string $enumFilenamePrefix = '',
        public string $enumFilenameSuffix = '',
        public string $interfaceFilenamePrefix = '',
        public string $interfaceFilenameSuffix = '',
        public string $traitFilenamePrefix = '',
        public string $traitFilenameSuffix = '',
        public string $constantsFilename = 'constants',
        public string $functionsFilename = 'functions',
        public array $labels = [],

        public bool $makeClassDescription = true,
        public bool $makeClassExtends = true,
        public bool $makeClassImplements = true,
        public bool $makeClassUses = true,
        public bool $makeClassConstructor = true,
        public bool $makeClassSynopsis = true,
        public bool $makeClassCases = true,
        public bool $makeClassCaseDetails = true,
        public bool $makeClassConstants = true,
        public bool $makeClassConstantDetails = true,
        public bool $makeClassProperties = true,
        public bool $makeClassPropertyDetails = true,
        public bool $makeClassMethods = true,
        public bool $makeClassMethodDetails = true,

        public bool $makeConstants = true,
        public bool $makeConstantsDescription = true,
        public bool $makeConstantSynopsis = true,
        public bool $makeConstantDetails = true,

        public bool $makeDefines = true,

        public bool $makeFunctions = true,
        public bool $makeFunctionsDescription = true,
        public bool $makeFunctionSynopsis = true,
        public bool $makeFunctionDetails = true,

        public bool $makeSynopsisMeta = true,

        public bool $showDeprecated = true,
        public bool $showDeprecatedLabel = true,
        public bool $showInternal = true,
        public bool $showInternalLabel = true,
        public bool $showGenerated = true,
        public bool $showGeneratedLabel = true,

        public array $accessModifierOrder = [
            PhpAccessModifier::PUBLIC,
            PhpAccessModifier::PROTECTED,
            PhpAccessModifier::PRIVATE,
        ],
        public array $typeOrder = [
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

        public bool $sortByName = true,
        public bool $sortByAccessModifier = true,
        public bool $groupByAccessModifier = true,
        public bool $sortByType = true,
        public bool $enableTables = true,
        public bool $inheritDocComment = true,
        public bool $prioritizeDocComment = true,
        public bool $useNullableSyntax = true,
        public int $indentLength = 4,
        public bool $escapeDocComments = false,
        public bool $override = false,
    ) {
        foreach ($this->baseNamespaces as $key => $value) {
            $this->baseNamespaces[$key] = '\\' . trim($value, '\\') . '\\';
        }

        $a = function(string $name): ?string {
            return null;
        };

        foreach ($this->basePaths as $key => $value) {
            $this->basePaths[$key] = xlient_clean_path($value);
        }

        foreach ($this->baseUrls as $key => $value) {
            $this->baseUrls[$key] = rtrim($value, '/');
        }

        $this->namespaceUrls = [
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
            ...$this->namespaceUrls,
        ];
        krsort($this->namespaceUrls);

        $this->labels = [
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
            ...$this->labels,
        ];
        $this->labels = array_map(xlient_markdown_escape(...), $this->labels);
    }
}

// ✝
