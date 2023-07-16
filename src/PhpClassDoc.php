<?php
namespace Xlient\Doc\Php;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Xlient\Doc\Php\AbstractPhpDoc;
use Xlient\Doc\Php\Configuration;
use Xlient\Doc\Php\DocComment;
use Xlient\Doc\Php\PhpFileMeta;
use Xlient\Doc\Php\PhpAccessModifier;

use function Xlient\Doc\Php\indent as xlient_indent;
use function Xlient\Doc\Php\make_dir as xlient_make_dir;
use function Xlient\Doc\Php\markdown_escape as xlient_markdown_escape;

use const DIRECTORY_SEPARATOR as DS;

/**
 * Class for generating documentation for PHP classes.
 */
class PhpClassDoc extends AbstractPhpDoc
{
    /**
     * @var ReflectionClass A class reflector.
     */
    protected ReflectionClass $reflector;

    /**
     * @inheritDoc
     */
    public function __construct(
        string $name,
        string $destDir,
        Configuration $config,
        PhpFileMeta $meta,
    ) {
        parent::__construct($name, $destDir, $config, $meta);

        $this->initializeReflector();

        $this->setDocComment($this->reflector->getDocComment() ?: null);
    }

    /**
     * Initializes a class reflector for the class being documented.
     */
    protected function initializeReflector(): void
    {
        $this->reflector = new ReflectionClass($this->getName());
    }

    /**
     * @inheritDoc
     */
    public function make(): array
    {
        $content = [
            ...$this->makeClass(),
            ...$this->makeConstructor(),
            ...$this->makeSynopsis(),
            ...$this->makeConstants(),
            ...$this->makeProperties(),
            ...$this->makeMethods(),
            ...$this->makeConstantDetails(),
            ...$this->makePropertyDetails(),
            ...$this->makeMethodDetails(),
        ];

        if (!$content) {
            return [];
        }

        $content = [
            ...$this->makeName(),
            ...$content,
        ];

        $content = implode("\n\n", $content);

        $file = $this->getFile();

        file_put_contents($file, $content);

        return [
            $file,
            ...$this->makeMethodFiles(),
            ...$this->makeSynopsisMeta(),
        ];
    }

    /**
     * Generates markdown for the name of this document.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeName(): array
    {
        return [
            '# ' . $this->config->labels['class'] . ' ' . $this->getName()
        ];
    }

    /**
     * Generates markdown for general information relating to the class being
     * documented.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeClass(): array
    {
        if (!$this->config->enableTables) {
            return [
                ...$this->makeClassDescription(),
                ...$this->makeExtends(),
                ...$this->makeImplements(),
                ...$this->makeUses(),
            ];
        }

        $content = $this->makeClassDescription();

        $table = [
            ...$this->makeExtends(),
            ...$this->makeImplements(),
            ...$this->makeUses(),
        ];

        if ($table) {
            $table = [
                '| ' . $this->config->labels['name'] . ' | ' . $this->config->labels['value'] . ' |',
                '| :--- | :--- |',
                ...$table,
            ];

            $content[] = implode("\n", $table);
        }

        return $content;
    }

    /**
     * Generates markdown for a code snyopsis of this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeSynopsis(): array
    {
        if (!$this->config->makeClassSynopsis) {
            return [];
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['class_synopsis'];

        $content[] = '```php';
        $content[] = $this->getClassDefinition($this->reflector);
        $content[] = '{';

        $constants = $this->makeConstantSynopsis();
        if ($constants) {
            $content = [
                ...$content,
                ...$constants,
            ];
        }

        $properties = $this->makePropertySynopsis();
        if ($properties) {
            if ($constants) {
                $content[] = '';
            }

            $content = [
                ...$content,
                ...$properties,
            ];
        }

        $methods = $this->makeMethodSynopsis();
        if ($methods) {
            if ($constants || $properties) {
                $content[] = '';
            }

            $content = [
                ...$content,
                ...$methods,
            ];
        }

        $content[] = '}';
        $content[] = '```';

        return [implode("\n", $content)];
    }

    /**
     * Generates markdown for a code synopsis of all class constants for this
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstantSynopsis(): array
    {
        $content = [];

        $filter = 0;

        $constants = [];
        $publicConstants = [];
        $protectedConstants = [];
        $privateConstants = [];

        if ($this->config->sortByAccessModifier) {
            if ($this->config->classPublic) {
                $publicConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PUBLIC
                );
            }

            if ($this->config->classProtected) {
                $protectedConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PROTECTED
                );
            }

            if ($this->config->classPrivate) {
                $privateConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PRIVATE
                );
            }
        } else{
            if ($this->config->classPublic) {
                $filter |= ReflectionClassConstant::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionClassConstant::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionClassConstant::IS_PRIVATE;
            }

            $constants = $this->getClassReflectionConstants(
                $this->reflector,
                $filter
            );
        }

        if (!$constants &&
            !$publicConstants &&
            !$protectedConstants &&
            !$privateConstants
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($constants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($accessModifier === PhpAccessModifier::PUBLIC) {
                $constants = [
                    ...$constants,
                    ...$publicConstants,
                ];
            }

            if ($accessModifier === PhpAccessModifier::PROTECTED) {
                $constants = [
                    ...$constants,
                    ...$protectedConstants,
                ];
            }

            if ($accessModifier === PhpAccessModifier::PRIVATE) {
                $constants = [
                    ...$constants,
                    ...$privateConstants,
                ];
            }
        }

        $content[] = xlient_indent(
            '/* ' . $this->config->labels['constants'] . ' */',
            1,
            $this->config->indentLength
        );

        foreach ($constants as $value) {
            $content[] = xlient_indent(
                $this->getClassConstantDefinition($value),
                1,
                $this->config->indentLength
            );
        }

        return $content;
    }

    /**
     * Generates markdown for a code synopsis of all properties for this
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makePropertySynopsis(): array
    {
        $content = [];

        $properties = [];
        $publicProperties = [];
        $protectedProperties = [];
        $privateProperties = [];

        if ($this->config->sortByAccessModifier) {
            if ($this->config->classPublic) {
                $publicProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PUBLIC
                );
            }

            if ($this->config->classProtected) {
                $protectedProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PROTECTED
                );
            }

            if ($this->config->classPrivate) {
                $privateProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PRIVATE
                );
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionProperty::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionProperty::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionProperty::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $properties = $this->getClassReflectionProperties(
                $this->reflector,
                $filter
            );
        }

        if (!$properties &&
            !$publicProperties &&
            !$protectedProperties &&
            !$privateProperties
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($properties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($accessModifier === PhpAccessModifier::PUBLIC) {
                $properties = [
                    ...$properties,
                    ...$publicProperties,
                ];
            }

            if ($accessModifier === PhpAccessModifier::PROTECTED) {
                $properties = [
                    ...$properties,
                    ...$protectedProperties,
                ];
            }

            if ($accessModifier === PhpAccessModifier::PRIVATE) {
                $properties = [
                    ...$properties,
                    ...$privateProperties,
                ];
            }
        }

        $content[] = xlient_indent(
            '/* ' . $this->config->labels['properties'] . ' */',
            1,
            $this->config->indentLength
        );

        foreach ($properties as $value) {
            $content[] = xlient_indent(
                $this->getPropertyDefinition($value),
                1,
                $this->config->indentLength
            );
        }

        return $content;
    }

    /**
     * Generates markdown for a code synopsis of all methods for this
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeMethodSynopsis(): array
    {
        $content = [];

        $methods = [];
        $publicMethods = [];
        $protectedMethods = [];
        $privateMethods = [];

        if ($this->config->sortByAccessModifier) {
            if ($this->config->classPublic) {
                $publicMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PUBLIC
                );
                $publicMethods = $this->removeIgnoreableMethods($publicMethods);
            }

            if ($this->config->classProtected) {
                $protectedMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PROTECTED
                );
                $protectedMethods = $this->removeIgnoreableMethods($protectedMethods);
            }

            if ($this->config->classPrivate) {
                $privateMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PRIVATE
                );
                $privateMethods = $this->removeIgnoreableMethods($privateMethods);
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionMethod::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionMethod::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionMethod::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $methods = $this->getClassReflectionMethods(
                $this->reflector,
                $filter
            );
        }

        if (!$methods &&
            !$publicMethods &&
            !$protectedMethods &&
            !$privateMethods
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($methods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($accessModifier === PhpAccessModifier::PUBLIC) {
                $methods = [
                    ...$methods,
                    ...$publicMethods,
                ];
            }

            if ($accessModifier === PhpAccessModifier::PROTECTED) {
                $methods = [
                    ...$methods,
                    ...$protectedMethods,
                ];
            }

            if ($accessModifier === PhpAccessModifier::PRIVATE) {
                $methods = [
                    ...$methods,
                    ...$privateMethods,
                ];
            }
        }

        $content[] = xlient_indent(
            '/* ' . $this->config->labels['methods'] . ' */',
            1,
            $this->config->indentLength
        );

        foreach ($methods as $value) {
            $content[] = xlient_indent(
                $this->getFunctionDefinition($value),
                1,
                $this->config->indentLength
            );
        }

        return $content;
    }

    /**
     * Generates meta JSON files to allow for linking in code sysnopsis after
     * syntax highlighting.
     *
     * @return array<string> An array of meta JSON files.
     */
    public function makeSynopsisMeta(): array
    {
        if (!$this->config->makeClassSynopsis ||
            !$this->config->makeSynopsisMeta
        ) {
            return [];
        }

        $content = [
            ...$this->makeConstantSynopsisMeta(),
            ...$this->makePropertySynopsisMeta(),
            ...$this->makeMethodSynopsisMeta(),
        ];

        if (!$content) {
            return [];
        }

        $content = json_encode($content);

        $file = substr($this->getFile(), 0, -3) . '.json';

        file_put_contents($file, $content);

        return [$file];
    }

    /**
     * Generates meta data relating to class constants to allow for linking in
     * code sysnopsis after syntax highlighting.
     *
     * @return array<string> An array of class constant meta data.
     */
    protected function makeConstantSynopsisMeta(): array
    {
        if (!$this->config->makeClassConstantDetails) {
            return [];
        }

        $content = [];

        $filter = 0;

        if ($this->config->classPrivate) {
            $filter |= ReflectionClassConstant::IS_PUBLIC;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionClassConstant::IS_PROTECTED;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionClassConstant::IS_PRIVATE;
        }

        $constants = $this->getClassReflectionConstants(
            $this->reflector,
            $filter
        );

        foreach ($constants as $value) {
            $name = $value->getName();

            $url = '#' . $this->getAnchor($value->getName());

            $content[$name] = $url;
        }

        return $content;
    }

    /**
     * Generates meta data relating to properties to allow for linking in code
     * sysnopsis after syntax highlighting.
     *
     * @return array<string> An array of property meta data.
     */
    protected function makePropertySynopsisMeta(): array
    {
        if (!$this->config->makeClassPropertyDetails) {
            return [];
        }

        $content = [];

        $filter = 0;

        if ($this->config->classPrivate) {
            $filter |= ReflectionProperty::IS_PUBLIC;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionProperty::IS_PROTECTED;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionProperty::IS_PRIVATE;
        }

        $properties = $this->getClassReflectionProperties(
            $this->reflector,
            $filter
        );

        foreach ($properties as $value) {
            $name = $value->getName();

            $url = '#' . $this->getAnchor($value->getName());

            $content[$name] = $url;
        }

        return $content;
    }

    /**
     * Generates meta data relating to methods to allow for linking in code
     * sysnopsis after syntax highlighting.
     *
     * @return array<string> An array of method meta data.
     */
    protected function makeMethodSynopsisMeta(): array
    {
        if (!$this->config->classMethodFiles &&
            !$this->config->makeClassMethodDetails
        ) {
            return [];
        }

        $content = [];

        $filter = 0;

        if ($this->config->classPrivate) {
            $filter |= ReflectionMethod::IS_PUBLIC;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionMethod::IS_PROTECTED;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionMethod::IS_PRIVATE;
        }

        $methods = $this->getClassReflectionMethods(
            $this->reflector,
            $filter
        );

        $methods = $this->removeIgnoreableMethods($methods);

        foreach ($methods as $value) {
            $name = $value->getName();

            if ($this->config->classMethodFiles) {
                $url = $this->getMethodUrl(
                    $this->getName(),
                    $value->getName()
                );
            } else {
                $url = '#' . $this->getAnchor($value->getName());
            }

            if ($url !== null) {
                $content[$name] = $url;
            }
        }

        return $content;
    }

    /**
     * Generates markdown for the main description of this class documentation
     * file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeClassDescription(): array
    {
        if (!$this->config->makeClassDescription) {
            return [];
        }

        $docComment = $this->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $docComment = $this->inheritDocComment(
            $this->reflector,
            $docComment
        );

        $docComment = new DocComment($docComment);

        $description = $this->makeDescription(
            $docComment->getSummary(),
            $docComment->getDescription(),
        );

        if ($description === null) {
            return [];
        }

        return [$description];
    }

    /**
     * Generates markdown for what parent classes this class extends.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeExtends(): array
    {
        if (!$this->config->makeClassExtends) {
            return [];
        }

        $parentClasses = $this->getParentClasses();
        if (!$parentClasses) {
            return [];
        }

        $content = [];

        if ($this->config->enableTables) {
            $row = '| ' . $this->config->labels['extends'] . ' | ';
        } else {
            $content[] = '## ' . $this->config->labels['extends'];
        }

        $items = [];

        foreach ($parentClasses as $key => $class) {
            $url = $this->getUrl($class);

            if ($url !== null) {
                $url = xlient_markdown_escape($url);

                $items[] = '[' . $class . '](' . $url . ')';
            } else {
                $items[] = $class;
            }
        }

        $items = implode($this->config->classSeparator, $items);

        if ($this->config->enableTables) {
            $row .= $items . ' |';

            $content[] = $row;
        } else {
            $content[] = $items;
        }

        return $content;
    }

    /**
     * Generates markdown for what interfaces this class implements.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeImplements(): array
    {
        if (!$this->config->makeClassImplements) {
            return [];
        }

        $interfaces = $this->getClassInterfaces($this->reflector);
        if (!$interfaces) {
            return [];
        }

        $content = [];

        if ($this->config->enableTables) {
            $row = '| ' . $this->config->labels['implements'] . ' | ';
        } else {
            $content[] = '## ' . $this->config->labels['implements'];
        }

        $items = [];

        foreach ($interfaces as $key => $interface) {
            $url = $this->getUrl($interface);

            if ($url !== null) {
                $url = xlient_markdown_escape($url);

                $items[] = '[' . $interface . '](' . $url . ')';
            } else {
                $items[] = $interface;
            }
        }

        $items = implode(', ', $items);

        if ($this->config->enableTables) {
            $row .= $items . ' |';

            $content[] = $row;
        } else {
            $content[] = $items;
        }

        return $content;
    }

    /**
     * Generates markdown for what traits this class uses.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeUses(): array
    {
        if (!$this->config->makeClassUses) {
            return [];
        }

        $traits = $this->getTraits();
        if (!$traits) {
            return [];
        }

        $content = [];

        if ($this->config->enableTables) {
            $row = '| ' . $this->config->labels['uses'] . ' | ';
        } else {
            $content[] = '## ' . $this->config->labels['uses'];
        }

        $items = [];

        foreach ($traits as $key => $trait) {
            $url = $this->getUrl($trait);

            if ($url !== null) {
                $url = xlient_markdown_escape($url);

                $items[] = '[' . $trait . '](' . $url . ')';
            } else {
                $items[] = $trait;
            }
        }

        $items = implode(', ', $items);

        if ($this->config->enableTables) {
            $row .= $items . ' |';

            $content[] = $row;
        } else {
            $content[] = $items;
        }

        return $content;
    }

    /**
     * Generates markdown for information about the constructor of this class.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstructor(): array
    {
        if (!$this->config->makeClassConstructor) {
            return [];
        }

        $constructor = $this->reflector->getConstructor();
        if (!$constructor) {
            return [];
        }

        return $this->makeFunction(
            function: $constructor,
            name: $this->config->labels['constructor'],
            headingDepth: 1,
        );
    }

    /**
     * Generates markdown for a list of constants contained in this class
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstants(): array
    {
        if (!$this->config->makeClassConstants) {
            return [];
        }

        $constants = [];
        $publicConstants = [];
        $protectedConstants = [];
        $privateConstants = [];

        if ($this->config->groupByAccessModifier ||
            $this->config->sortByAccessModifier
        ) {
            if ($this->config->classPublic) {
                $publicConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PUBLIC
                );
            }

            if ($this->config->classProtected) {
                $protectedConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PROTECTED
                );
            }

            if ($this->config->classPrivate) {
                $privateConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PRIVATE
                );
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionClassConstant::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionClassConstant::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionClassConstant::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $constants = $this->getClassReflectionConstants(
                $this->reflector,
                $filter
            );
        }

        if (!$constants &&
            !$publicConstants &&
            !$protectedConstants &&
            !$privateConstants
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($constants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['constants'];

        if ($constants) {
            $content = [
                ...$content,
                ...$this->makeConstantsPartial(
                    $constants,
                    headingDepth: 2,
                )
            ];
        }

        if ($this->config->groupByAccessModifier) {
            $headingDepth = 3;
        } else {
            $headingDepth = 2;
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($publicConstants &&
                $accessModifier === PhpAccessModifier::PUBLIC
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['public'];
                }

                $content = [
                    ...$content,
                    ...$this->makeConstantsPartial(
                        $publicConstants,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($protectedConstants &&
                $accessModifier === PhpAccessModifier::PROTECTED
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['protected'];
                }

                $content = [
                    ...$content,
                    ...$this->makeConstantsPartial(
                        $protectedConstants,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($privateConstants &&
                $accessModifier === PhpAccessModifier::PRIVATE
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['private'];
                }

                $content = [
                    ...$content,
                    ...$this->makeConstantsPartial(
                        $privateConstants,
                        $headingDepth,
                    )
                ];
            }
        }

        return $content;
    }

    /**
     * Generates markdown for the array of specified constants.
     *
     * @param array<ReflectionClassConstant> $constants An array of constant
     *  reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstantsPartial(
        array $constants,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makeConstantsPartialTable($constants, $headingDepth);
        }

        $content = [];

        $heading = str_repeat('#', $headingDepth);

        foreach ($constants as $value) {
            $data = $this->getConstantData($value);

            if ($data->url !== null) {
                $content[] = $heading . '# ' . $data->type .
                    ' [$' . $data->name . '](' . $data->url . ')';
            } else {
                $content[] = $heading . '# ' . $data->type . ' $' . $data->name;
            }

            if ($data->marks !== null) {
                $content[] = $data->marks;
            }

            if ($data->description !== null) {
                $content[] = $data->description;
            }

            $definition = $this->getClassConstantDefinition($value);
            $content[] = '```php' . "\n" . $definition . "\n" . '```';
        }

        return $content;
    }

    /**
     * Generates markdown for the array of specified constants in a table
     * format.
     *
     * @param array<ReflectionClassConstant> $constants An array of constant
     *  reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstantsPartialTable(
        array $constants,
        int $headingDepth,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        $table = [];
        $table[] = '| ' . $this->config->labels['name'] . ' | ' .
            $this->config->labels['type'] . ' |' .
            $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- | :--- |';

        foreach ($constants as $value) {
            $data = $this->getConstantData($value);

            $marks = $data->marks;

            if ($marks !== null) {
                $marks = '<br>' . $marks;
            }

            $row = '| ';

            if ($data->url !== null) {
                $row .= '[$' . $data->name . '](#' . $data->url . ')' .
                    $marks . ' | ' . $data->type . ' | ';
            } else {
                $row .= '$' . $data->name . $marks . ' | ' . $data->type . ' | ';
            }

            $row .= $data->description ?? '';

            $row .= ' |';

            $table[] = $row;
        }

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Gets the markdown encoded name, type, description, url, and marks of the
     * specified constant.
     *
     * @param ReflectionClassConstant $constant A class constant reflector.
     *
     * @return object{
     *     name: string,
     *     type: string|null,
     *     description: string|null,
     *     url: string|null,
     *     marks: string|null,
     * } An object of class constant data.
     */
    protected function getConstantData(ReflectionClassConstant $constant): object
    {
        $name = xlient_markdown_escape($constant->getName());

        $type = $this->getTypeDefinitionFromValue($constant->getValue());
        if ($type !== null) {
            $type = xlient_markdown_escape($type);
        }

        $docComment = $this->inheritDocComment($constant);
        $docComment = new DocComment($docComment);

        $description = $this->makeDescription(
            $docComment->getSummary(),
            null
        );

        if ($this->config->makeClassConstantDetails) {
            $url = $this->getAnchor($constant->getName());
            $url = xlient_markdown_escape($url);
        } else {
            $url = null;
        }

        $marks = $this->makeMarkLabels($docComment);

        return new class($name, $type, $description, $url, $marks) {
            public function __construct(
                public readonly string $name,
                public readonly ?string $type,
                public readonly ?string $description,
                public readonly ?string $url,
                public readonly ?string $marks,
            ) {}
        };
    }

    /**
     * Generates markdown for more detailed information about the constants in
     * this class documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstantDetails(): array
    {
        if (!$this->config->makeClassConstantDetails) {
            return [];
        }

        $constants = [];
        $publicConstants = [];
        $protectedConstants = [];
        $privateConstants = [];

        if ($this->config->groupByAccessModifier ||
            $this->config->sortByAccessModifier
        ) {
            if ($this->config->classPublic) {
                $publicConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PUBLIC
                );
            }

            if ($this->config->classProtected) {
                $protectedConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PROTECTED
                );
            }

            if ($this->config->classPrivate) {
                $privateConstants = $this->getClassReflectionConstants(
                    $this->reflector,
                    ReflectionClassConstant::IS_PRIVATE
                );
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionClassConstant::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionClassConstant::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionClassConstant::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $constants = $this->getClassReflectionConstants(
                $this->reflector,
                $filter
            );
        }

        if (!$constants &&
            !$publicConstants &&
            !$protectedConstants &&
            !$privateConstants
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($constants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateConstants, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['constant_details'];

        if ($constants) {
            $content = [
                ...$content,
                ...$this->makeConstantDetailsPartial(
                    $constants,
                    headingDepth: 2,
                )
            ];
        }

        if ($this->config->groupByAccessModifier) {
            $headingDepth = 3;
        } else {
            $headingDepth = 2;
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($publicConstants &&
                $accessModifier === PhpAccessModifier::PUBLIC
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['public'];
                }

                $content = [
                    ...$content,
                    ...$this->makeConstantDetailsPartial(
                        $publicConstants,
                        $headingDepth
                    )
                ];

                continue;
            }

            if ($protectedConstants &&
                $accessModifier === PhpAccessModifier::PROTECTED
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['protected'];
                }

                $content = [
                    ...$content,
                    ...$this->makeConstantDetailsPartial(
                        $protectedConstants,
                        $headingDepth
                    )
                ];

                continue;
            }

            if ($privateConstants &&
                $accessModifier === PhpAccessModifier::PRIVATE
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['private'];
                }

                $content = [
                    ...$content,
                    ...$this->makeConstantDetailsPartial(
                        $privateConstants,
                        $headingDepth
                    )
                ];
            }
        }

        return $content;
    }

    /**
     * Generates markdown for more detailed information about the specified
     * constants.
     *
     * @param array<ReflectionClassConstant> $constants An array of constant
     *  reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstantDetailsPartial(
        array $constants,
        int $headingDepth,
    ): array
    {
        $content = [];

        foreach ($constants as $value) {
            $name = $value->getName();
            if (strtoupper($name) === $name) {
                $name = strtolower($name);
            }
            $anchor = $this->getAnchor($name);

            $content = [
                ...$content,
                ...$this->makeConstant(
                    constant: $value,
                    headingDepth: $headingDepth,
                    anchor: $anchor
                ),
            ];
        }

        return $content;
    }

    /**
     * Generates markdown for a list of properties contained in this class
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeProperties(): array
    {
        if (!$this->config->makeClassProperties) {
            return [];
        }

        $properties = [];
        $publicProperties = [];
        $protectedProperties = [];
        $privateProperties = [];

        if ($this->config->groupByAccessModifier ||
            $this->config->sortByAccessModifier
        ) {
            if ($this->config->classPublic) {
                $publicProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PUBLIC
                );
            }

            if ($this->config->classProtected) {
                $protectedProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PROTECTED
                );
            }

            if ($this->config->classPrivate) {
                $privateProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PRIVATE
                );
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionProperty::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionProperty::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionProperty::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $properties = $this->getClassReflectionProperties(
                $this->reflector,
                $filter
            );
        }

        if (!$properties &&
            !$publicProperties &&
            !$protectedProperties &&
            !$privateProperties
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($properties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['properties'];

        if ($properties) {
            $content = [
                ...$content,
                ...$this->makePropertiesPartial(
                    $properties,
                    headingDepth: 2,
                )
            ];
        }

        if ($this->config->groupByAccessModifier) {
            $headingDepth = 3;
        } else {
            $headingDepth = 2;
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($publicProperties &&
                $accessModifier === PhpAccessModifier::PUBLIC
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['public'];
                }

                $content = [
                    ...$content,
                    ...$this->makePropertiesPartial(
                        $publicProperties,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($protectedProperties &&
                $accessModifier === PhpAccessModifier::PROTECTED
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['protected'];
                }

                $content = [
                    ...$content,
                    ...$this->makePropertiesPartial(
                        $protectedProperties,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($privateProperties &&
                $accessModifier === PhpAccessModifier::PRIVATE
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['private'];
                }

                $content = [
                    ...$content,
                    ...$this->makePropertiesPartial(
                        $privateProperties,
                        $headingDepth,
                    )
                ];
            }
        }

        return $content;
    }

    /**
     * Generates markdown for the array of specified properties.
     *
     * @param array<ReflectionProperty> $properties An array of property
     *  reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makePropertiesPartial(
        array $properties,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makePropertiesPartialTable(
                $properties,
                $headingDepth
            );
        }

        $content = [];

        $heading = str_repeat('#', $headingDepth);

        foreach ($properties as $value) {
            $data = $this->getPropertyData($value);

            if ($data->url !== null) {
                $content[] = $heading . '# ' . $data->type .
                    ' [$' . $data->name . '](' . $data->url . ')';
            } else {
                $content[] = $heading . '# ' . $data->type . ' $' . $data->name;
            }

            if ($data->marks !== null) {
                $content[] = $data->marks;
            }

            if ($data->description !== null) {
                $content[] = $data->description;
            }

            $definition = $this->getPropertyDefinition($value);
            $content[] = '```php' . "\n" . $definition . "\n" . '```';
        }

        return $content;
    }

    /**
     * Generates markdown for the array of specified properties in a table
     * format.
     *
     * @param array<ReflectionProperty> $properties An array of property
     *  reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makePropertiesPartialTable(
        array $properties,
        int $headingDepth,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        $table = [];
        $table[] = '| ' . $this->config->labels['name'] . ' | ' .
            $this->config->labels['type'] . ' |' .
            $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- | :--- |';

        foreach ($properties as $value) {
            $data = $this->getPropertyData($value);

            $marks = $data->marks;

            if ($marks !== null) {
                $marks = '<br>' . $marks;
            }

            $row = '| ';

            if ($data->url !== null) {
                $row .= '[$' . $data->name . '](#' . $data->url . ')' .
                    $marks . ' | ' . $data->type . ' | ';
            } else {
                $row .= '$' . $data->name . $marks . ' | ' . $data->type . ' | ';
            }

            $row .= $data->description ?? '';

            $row .= ' |';

            $table[] = $row;
        }

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Gets the markdown encoded name, type, description, url, and marks of the
     * specified property.
     *
     * @param ReflectionProperty $property A property reflector.
     *
     * @return object{
     *     name: string,
     *     type: string|null,
     *     description: string|null,
     *     url: string|null,
     *     marks: string|null,
     * } An object of property data.
     */
    protected function getPropertyData(ReflectionProperty $property): object
    {
        $name = xlient_markdown_escape($property->getName());

        $type = $property->getType();
        if ($type !== null) {
            $type = $this->getTypeDefinition($type);
            $type = xlient_markdown_escape($type);
        }

        $description = null;

        $docComment = $this->inheritDocComment($property);
        $docComment = new DocComment($docComment);

        $tags = $docComment->getVarTagValues();
        if ($tags) {
            if ($type === null || $this->config->prioritizeDocComments) {
                $tagType = strval($tags[0]->type);
                if ($tagType !== '') {
                    $type = $tagType;
                }
            }

            $description = $this->makeDescription(
                null,
                $tags[0]->description,
            );
        }

        if ($this->config->makeClassPropertyDetails) {
            $url = $this->getAnchor($property->getName());
            $url = xlient_markdown_escape($url);
        } else {
            $url = null;
        }

        $marks = $this->makeMarkLabels($docComment);

        return new class($name, $type, $description, $url, $marks) {
            public function __construct(
                public readonly string $name,
                public readonly ?string $type,
                public readonly ?string $description,
                public readonly ?string $url,
                public readonly ?string $marks,
            ) {}
        };
    }

    /**
     * Generates markdown for more detailed information about the properties in
     * this class documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makePropertyDetails(): array
    {
        if (!$this->config->makeClassPropertyDetails) {
            return [];
        }

        $properties = [];
        $publicProperties = [];
        $protectedProperties = [];
        $privateProperties = [];

        if ($this->config->groupByAccessModifier ||
            $this->config->sortByAccessModifier
        ) {
            if ($this->config->classPublic) {
                $publicProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PUBLIC
                );
            }

            if ($this->config->classProtected) {
                $protectedProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PROTECTED
                );
            }

            if ($this->config->classPrivate) {
                $privateProperties = $this->getClassReflectionProperties(
                    $this->reflector,
                    ReflectionProperty::IS_PRIVATE
                );
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionProperty::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionProperty::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionProperty::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $properties = $this->getClassReflectionProperties(
                $this->reflector,
                $filter
            );
        }

        if (!$properties &&
            !$publicProperties &&
            !$protectedProperties &&
            !$privateProperties
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($properties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateProperties, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['property_details'];

        if ($properties) {
            $content = [
                ...$content,
                ...$this->makePropertyDetailsPartial(
                    $properties,
                    headingDepth: 2,
                )
            ];
        }

        if ($this->config->groupByAccessModifier) {
            $headingDepth = 3;
        } else {
            $headingDepth = 2;
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($publicProperties &&
                $accessModifier === PhpAccessModifier::PUBLIC
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['public'];
                }

                $content = [
                    ...$content,
                    ...$this->makePropertyDetailsPartial(
                        $publicProperties,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($protectedProperties &&
                $accessModifier === PhpAccessModifier::PROTECTED
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['protected'];
                }

                $content = [
                    ...$content,
                    ...$this->makePropertyDetailsPartial(
                        $protectedProperties,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($privateProperties &&
                $accessModifier === PhpAccessModifier::PRIVATE
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['private'];
                }

                $content = [
                    ...$content,
                    ...$this->makePropertyDetailsPartial(
                        $privateProperties,
                        $headingDepth,
                    )
                ];
            }
        }

        return $content;
    }

    /**
     * Generates markdown for more detailed information about the specified
     * properties.
     *
     * @param array<ReflectionProperty> $properties An array of property
     *  reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makePropertyDetailsPartial(
        array $properties,
        int $headingDepth,
    ): array
    {
        $content = [];

        foreach ($properties as $value) {
            $anchor = $this->getAnchor($value->getName());

            $content = [
                ...$content,
                ...$this->makeProperty(
                    property: $value,
                    headingDepth: $headingDepth,
                    anchor: $anchor
                ),
            ];
        }

        return $content;
    }

    /**
     * Generates markdown for a list of methods contained in this class
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeMethods(): array
    {
        if (!$this->config->makeClassMethods) {
            return [];
        }

        $methods = [];
        $publicMethods = [];
        $protectedMethods = [];
        $privateMethods = [];

        if ($this->config->groupByAccessModifier ||
            $this->config->sortByAccessModifier
        ) {
            if ($this->config->classPublic) {
                $publicMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PUBLIC
                );
                $publicMethods = $this->removeIgnoreableMethods($publicMethods);
            }

            if ($this->config->classProtected) {
                $protectedMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PROTECTED
                );
                $protectedMethods = $this->removeIgnoreableMethods($protectedMethods);
            }

            if ($this->config->classPrivate) {
                $privateMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PRIVATE
                );
                $privateMethods = $this->removeIgnoreableMethods($privateMethods);
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionMethod::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionMethod::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionMethod::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $methods = $this->getClassReflectionMethods(
                $this->reflector,
                $filter
            );
            $methods = $this->removeIgnoreableMethods($methods);
        }

        if (!$methods &&
            !$publicMethods &&
            !$protectedMethods &&
            !$privateMethods
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($methods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['methods'];

        if ($methods) {
            $content = [
                ...$content,
                ...$this->makeMethodsPartial(
                    $methods,
                    headingDepth: 2,
                )
            ];
        }

        if ($this->config->groupByAccessModifier) {
            $headingDepth = 3;
        } else {
            $headingDepth = 2;
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($publicMethods &&
                $accessModifier === PhpAccessModifier::PUBLIC
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['public'];
                }

                $content = [
                    ...$content,
                    ...$this->makeMethodsPartial(
                        $publicMethods,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($protectedMethods &&
                $accessModifier === PhpAccessModifier::PROTECTED
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['protected'];
                }

                $content = [
                    ...$content,
                    ...$this->makeMethodsPartial(
                        $protectedMethods,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($privateMethods &&
                $accessModifier === PhpAccessModifier::PRIVATE
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['private'];
                }

                $content = [
                    ...$content,
                    ...$this->makeMethodsPartial(
                        $privateMethods,
                        $headingDepth,
                    )
                ];
            }
        }

        return $content;
    }

    /**
     * Removes any methods from the specified array that the current
     * configuration indicates should be removed.
     *
     * @param array<ReflectionMethod> $methods An array of method reflectors.
     *
     * @return array<ReflectionMethod> An array of method reflectors.
     */
    private function removeIgnoreableMethods(array $methods): array
    {
        if ($this->config->makeClassConstructor) {
            foreach ($methods as $key => $value) {
                if ($value->getName() === '__construct') {
                    unset($methods[$key]);
                }
            }
        }

        return $methods;
    }

    /**
     * Generates markdown for the array of specified methods.
     *
     * @param array<ReflectionMethod> $methods An array of method reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeMethodsPartial(
        array $methods,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makeMethodsPartialTable($methods, $headingDepth);
        }

        $content = [];

        $heading = str_repeat('#', $headingDepth);

        foreach ($methods as $value) {
            $data = $this->getMethodData($value);

            if ($data->url !== null) {
                $content[] = $heading . '# [' . $data->name . '()](' . $data->url . ')';
            } else {
                $content[] = $heading . '# ' . $data->name . '()';
            }

            if ($data->marks !== null) {
                $content[] = $data->marks;
            }

            if ($data->description !== null) {
                $content[] = $data->description;
            }
        }

        return $content;
    }

    /**
     * Generates markdown for the array of specified methods in a table format.
     *
     * @param array<ReflectionMethod> $methods An array of method reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeMethodsPartialTable(
        array $methods,
        int $headingDepth,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        $table = [];
        $table[] = '| ' . $this->config->labels['name'] . ' | ' . $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- |';

        foreach ($methods as $value) {
            $data = $this->getMethodData($value);

            $marks = $data->marks;

            if ($marks !== null) {
                $marks = '<br>' . $marks;
            }

            $row = '| ';
            if ($data->url !== null) {
                $row .= '[' . $data->name . '()](' . $data->url . ')' . $marks . ' | ';
            } else {
                $row .= $data->name . '()' . $marks . ' | ';
            }

            $row .= $data->description ?? '';

            $row .= ' |';

            $table[] = $row;
        }

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Gets the markdown encoded name, description, url, and marks of the
     * specified method.
     *
     * @param ReflectionMethod $method A method reflector.
     *
     * @return object{
     *     name: string,
     *     description: string|null,
     *     url: string|null,
     *     marks: string|null,
     * } An object of method data.
     */
    protected function getMethodData(ReflectionMethod $method): object
    {
        $name = xlient_markdown_escape($method->getName());

        $docComment = $this->inheritDocComment($method);
        $docComment = new DocComment($docComment);

        $description = $this->makeDescription(
            $docComment->getSummary(),
            null
        );

        if ($this->config->classMethodFiles) {
            $url = $this->getMethodUrl(
                $this->getName(),
                $method->getName()
            );
        } else {
            $url = '#' . $this->getAnchor($method->getName());
        }

        if ($url !== null) {
            $url = xlient_markdown_escape($url);
        }

        $marks = $this->makeMarkLabels($docComment);

        return new class($name, $description, $url, $marks) {
            public function __construct(
                public readonly string $name,
                public readonly ?string $description,
                public readonly ?string $url,
                public readonly ?string $marks,
            ) {}
        };
    }

    /**
     * Generates markdown for more detailed information about the methods in
     * this class documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeMethodDetails(): array
    {
        if (!$this->config->makeClassMethodDetails) {
            return [];
        }

        $methods = [];
        $publicMethods = [];
        $protectedMethods = [];
        $privateMethods = [];

        if ($this->config->groupByAccessModifier ||
            $this->config->sortByAccessModifier
        ) {
            if ($this->config->classPublic) {
                $publicMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PUBLIC
                );
                $publicMethods = $this->removeIgnoreableMethods($publicMethods);
            }

            if ($this->config->classProtected) {
                $protectedMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PROTECTED
                );
                $protectedMethods = $this->removeIgnoreableMethods($protectedMethods);
            }

            if ($this->config->classPrivate) {
                $privateMethods = $this->getClassReflectionMethods(
                    $this->reflector,
                    ReflectionMethod::IS_PRIVATE
                );
                $privateMethods = $this->removeIgnoreableMethods($privateMethods);
            }
        } else {
            $filter = 0;

            if ($this->config->classPublic) {
                $filter |= ReflectionMethod::IS_PUBLIC;
            }

            if ($this->config->classProtected) {
                $filter |= ReflectionMethod::IS_PROTECTED;
            }

            if ($this->config->classPrivate) {
                $filter |= ReflectionMethod::IS_PRIVATE;
            }

            if ($filter === 0) {
                return [];
            }

            $methods = $this->getClassReflectionMethods(
                $this->reflector,
                $filter
            );
            $methods = $this->removeIgnoreableMethods($methods);
        }

        if (!$methods &&
            !$publicMethods &&
            !$protectedMethods &&
            !$privateMethods
        ) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($methods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($publicMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($protectedMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });

            usort($privateMethods, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['method_details'];

        if ($methods) {
            $content = [
                ...$content,
                ...$this->makeMethodDetailsPartial(
                    $methods,
                    headingDepth: 2,
                )
            ];
        }

        if ($this->config->groupByAccessModifier) {
            $headingDepth = 3;
        } else {
            $headingDepth = 2;
        }

        foreach ($this->config->accessModifierOrder as $accessModifier) {
            if ($publicMethods &&
                $accessModifier === PhpAccessModifier::PUBLIC
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['public'];
                }

                $content = [
                    ...$content,
                    ...$this->makeMethodDetailsPartial(
                        $publicMethods,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($protectedMethods &&
                $accessModifier === PhpAccessModifier::PROTECTED
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['protected'];
                }

                $content = [
                    ...$content,
                    ...$this->makeMethodDetailsPartial(
                        $protectedMethods,
                        $headingDepth,
                    )
                ];

                continue;
            }

            if ($privateMethods &&
                $accessModifier === PhpAccessModifier::PRIVATE
            ) {
                if ($this->config->groupByAccessModifier) {
                    $content[] = '### ' . $this->config->labels['private'];
                }

                $content = [
                    ...$content,
                    ...$this->makeMethodDetailsPartial(
                        $privateMethods,
                        $headingDepth,
                    )
                ];
            }
        }

        return $content;
    }

    /**
     * Generates markdown for more detailed information about the specified
     * methods.
     *
     * @param array<ReflectionMethod> $methods An array of method reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeMethodDetailsPartial(
        array $methods,
        int $headingDepth,
    ): array
    {
        $content = [];

        foreach ($methods as $value) {
            $anchor = $this->getAnchor($value->getName());

            $content = [
                ...$content,
                ...$this->makeFunction(
                    function: $value,
                    headingDepth: $headingDepth,
                    anchor: $anchor,
                )
            ];
        }

        return $content;

    }

    /**
     * Generates documentation files for each method in this class.
     *
     * @return array<string> An array of files.
     */
    protected function makeMethodFiles(): array
    {
        if (!$this->config->makeClassMethods ||
            !$this->config->classMethodFiles
        ) {
            return [];
        }

        $files = [];

        $filter = 0;

        if ($this->config->classPrivate) {
            $filter |= ReflectionMethod::IS_PUBLIC;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionMethod::IS_PROTECTED;
        }

        if ($this->config->classPrivate) {
            $filter |= ReflectionMethod::IS_PRIVATE;
        }

        $methods = $this->getClassReflectionMethods(
            $this->reflector,
            $filter
        );

        $methods = $this->removeIgnoreableMethods($methods);

        foreach ($methods as $value) {
            $content = $this->makeFunction(
                function: $value,
                name: $this->getName() . '::' . $value->getName(),
            );

            $content = implode("\n\n", $content);

            $file = $this->getMethodFile($this->getName(), $value->getName());

            file_put_contents($file, $content);

            $files[] = $file;
        }

        return $files;
    }

    /**
     * Gets an array of all parent class names.
     *
     * @return array<string> An array of fully qualified class names.
     */
    protected function getParentClasses(): array
    {
        $classes = [];

        $class = $this->reflector;

        while (true) {
            $class = $class->getParentClass();

            if ($class === false) {
                break;
            }

            $classes[] = '\\' . $class->getName();
        }

        return $classes;
    }

    /**
     * Gets an array of all traits used by this class.
     *
     * @return array<string> An array of fully qualified class names.
     */
    protected function getTraits(): array
    {
        $traits = $this->reflector->getTraitNames();

        foreach ($traits as $key => $value) {
            $traits[$key] = '\\' . $value;
        }

        return $traits;
    }

    /**
     * Gets a file for the specified class and method name.
     *
     * @param string $class A fully qualified class name.
     * @param string $method A method name.
     *
     * @return string A file.
     */
    protected function getMethodFile(string $class, string $method): string
    {
        $class = '\\' . ltrim($class, '\\') . '\\' . $method;

        $dir = $this->destDir . DS . $this->getDirPath($class);
        if (!file_exists($dir)) {
            xlient_make_dir($dir);
        }

        return $dir . DS . $this->getFilename($class);
    }

    /**
     * Gets a URL for the specified class and method name.
     *
     * @param string $class A fully qualified class name.
     * @param string $method A method name.
     *
     * @return string A URL.
     */
    protected function getMethodUrl(string $class, string $method): ?string
    {
        $class = '\\' . ltrim($class, '\\');

        $matchingNamespace = null;
        foreach ($this->config->baseNamespaces as $namespace) {
            if (str_starts_with($class, $namespace)) {
                $matchingNamespace = rtrim($namespace, '\\');
                break;
            }
        }

        if ($matchingNamespace === null) {
            return $this->getExternalMethodUrl($class, $method);
        }

        $baseUrl = $this->config->baseUrls[$matchingNamespace] ?? null;
        if ($baseUrl === null) {
            return null;
        }

        $class .= '\\' . $method;

        $url = $baseUrl . $this->getUrlPath($class);

        $url .= '/' . $this->getFilename($class);

        return $url;
    }

    /**
     * Gets an external URL for the specified class and method name if one can
     * be determined.
     *
     * @param string $class A fully qualified class name.
     * @param string $method A method name.
     *
     * @return string A URL.
     */
    protected function getExternalMethodUrl(string $class, string $method): ?string
    {
        $url = null;

        if ($this->config->methodUrlCallback !== null) {
            $url = $this->config->methodUrlCallback($class, $method, $url);
        }

        return $url;
    }

    /**
     * @inheritDoc
     */
    protected function getFilename(?string $name = null): string
    {
        $name ??= $this->getName();

        return $this->getClassFilename(
            $name,
            $this->config->classFilenamePrefix,
            $this->config->classFilenameSuffix,
        );
    }
}

// ✝
