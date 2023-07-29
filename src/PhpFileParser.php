<?php
namespace Xlient\Doc\Php;

use RuntimeException;
use UnexpectedValueException;
use Xlient\Doc\Php\PhpClassDoc;
use Xlient\Doc\Php\PhpConstant;
use Xlient\Doc\Php\PhpConstantsDoc;
use Xlient\Doc\Php\PhpEnumDoc;
use Xlient\Doc\Php\PhpFileMeta;
use Xlient\Doc\Php\PhpFunction;
use Xlient\Doc\Php\PhpFunctionsDoc;
use Xlient\Doc\Php\PhpInterfaceDoc;
use Xlient\Doc\Php\PhpTraitDoc;

use function Xlient\Doc\Php\clean_dir as xlient_clean_dir;

/**
 * Parses a PHP file into individual documentation classes.
 */
final class PhpFileParser
{
    /**
     * @var array<PhpClassDoc> An array of PHPClassDoc instances.
     */
    private array $classes = [];

    /**
     * @var array<PhpInterfaceDoc> An array of PhpInterfaceDoc instances.
     */
    private array $interfaces = [];

    /**
     * @var array<PhpTraitDoc> An array of PhpTraitDoc instances.
     */
    private array $traits = [];

    /**
     * @var array<PhpEnumDoc> An array of PhpEnumDoc instances.
     */
    private array $enums = [];

    /**
     * @var array<PhpFunctionsDoc> An array of PhpFunctionsDoc instances.
     */
    private array $functions = [];

    /**
     * @var array<PhpConstantsDoc> An array of PhpConstantsDoc instances.
     */
    private array $constants = [];

    /**
     * @var PhpFileMeta A meta class for storing additional information about
     *  a PHP file.
     */
    private PhpFileMeta $meta;

    /**
     * @var int The curly bracket depth of items contained within this
     *  file's namespace.
     */
    private int $namespaceDepth = 0;

    /**
     * @var int How many curly brackets deep the current parse point
     *  is at.
     */
    private int $depth = 0;

    /**
     * @var string The namespace the current parsepoint is contained in.
     */
    private string $namespace = '';

    /**
     * @var string|null The last PHPDoc comment found while parsing.
     */
    private ?string $docComment = null;

    /**
     * @param string $file The PHP file to parse.
     * @param string $destDir The destination directory to save documentation
     *  files.
     * @param Configuration $config The configuration to use to generate the
     *  documentation.
     */
    public function __construct(
        protected string $file,
        protected string $destDir,
        protected Configuration $config,
    ) {
        $this->destDir = xlient_clean_dir($this->destDir);
        if (!is_dir($this->destDir)) {
            throw new RuntimeException(
                'The specified destination directory, ' . $this->destDir . ', was not found.'
            );
        }

        $this->meta = new PhpFileMeta($config);
    }

    /**
     * Gets an array of PHPClassDoc instances of all classes in this file.
     *
     * @return array<PhpClassDoc> An array of PHPClassDoc instances.
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Gets an array of PhpInterfaceDoc instances of all classes in this file.
     *
     * @return array<PhpInterfaceDoc> An array of PhpInterfaceDoc instances.
     */
    public function getInterfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * Gets an array of PhpTraitDoc instances of all classes in this file.
     *
     * @return array<PhpTraitDoc> An array of PhpTraitDoc instances.
     */
    public function getTraits(): array
    {
        return $this->traits;
    }

    /**
     * Gets an array of PhpEnumDoc instances of all classes in this file.
     *
     * @return array<PhpEnumDoc> An array of PhpEnumDoc instances.
     */
    public function getEnums(): array
    {
        return $this->enums;
    }

    /**
     * Gets an array of PhpFunctionsDoc instances of all classes in this file.
     *
     * @return array<PhpFunctionsDoc> An array of PhpFunctionsDoc instances.
     */
    public function getFunctions(): array
    {
        return array_values($this->functions);
    }

    /**
     * Gets an array of PhpConstantsDoc instances of all classes in this file.
     *
     * @return array<PhpConstantsDoc> An array of PhpConstantsDoc instances.
     */
    public function getConstants(): array
    {
        return array_values($this->constants);
    }

    /**
     * Parses the PHP file into individual documentation classes.
     *
     * @return void
     */
    public function parse(): void
    {
        $tokens = token_get_all(strval(file_get_contents($this->file)));
        $tokens = array_reverse($tokens);

        while ($token = array_pop($tokens)) {
            if ($token === '{') {
                ++$this->depth;
                continue;
            } elseif ($token === '}') {
                --$this->depth;
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $tokens = $this->parseNamespace($tokens);
            }

            if ($token[0] === T_USE) {
                $tokens = $this->parseUse($tokens);
            }

            // Anything contained deeper than the namespace depth
            // can be skipped
            if ($this->depth > $this->namespaceDepth) {
                continue;
            }

            if ($token[0] === T_DOC_COMMENT) {
                $this->docComment = $token[1];
                continue;
            }

            if ($token[0] === T_STRING) {
                // Parse out defines as constants
                if ($token[1] === 'define') {
                    $tokens = $this->parseDefine($tokens);
                }
            }

            if ($token[0] === T_CLASS ||
                $token[0] === T_INTERFACE ||
                $token[0] === T_TRAIT ||
                $token[0] === T_ENUM
            ) {
                $tokens = $this->parseClass($tokens, $token[0]);
            }

            if ($token[0] === T_FUNCTION) {
                $tokens = $this->parseFunction($tokens);
            }

            if ($token[0] === T_CONST) {
                $tokens = $this->parseConstant($tokens);
            }
        }
    }

    /**
     * Parses out tokens relating to the current namespace.
     *
     * @param array<int, string|array{int,string,int}> $tokens An array of tokens.
     *
     * @return array<int, string|array{int,string,int}> The remaining tokens.
     */
    private function parseNamespace(array $tokens): array
    {
        $this->namespaceDepth = 0;

        while ($token = array_pop($tokens)) {
            if ($token === '{') {
                ++$this->namespaceDepth;
                ++$this->depth;
                break;
            }

            if ($token === ';') {
                break;
            }

            if ($token[0] === T_NAME_QUALIFIED ||
                $token[0] === T_STRING
            ) {
                $this->namespace = '\\' . $token[1] . '\\';
                continue;
            }
        }

        return $tokens;
    }

    /**
     * Parses out tokens relating to class, function, and constant use
     * statements.
     *
     * These use statements are added to the meta class to reconstitute fully
     * qualified names.
     *
     * @param array<int, string|array{int,string,int}> $tokens An array of tokens.
     *
     * @return array<int, string|array{int,string,int}> The remaining tokens.
     */
    private function parseUse(array $tokens): array
    {
        $type = null;
        $name = null;
        $as = null;
        $hasGroupNames = false;
        $groupNames = [];
        $groupAs = [];
        $parseAgain = false;
        $hasAs = false;

        while ($token = array_pop($tokens)) {
            if ($token === ';') {
                break;
            }

            if ($token === '{') {
                $hasGroupNames = true;
                continue;
            }

            if ($hasGroupNames && $token === '}') {
                $hasGroupNames = false;
                continue;
            }

            if ($token === ',') {
                if (!$hasGroupNames) {
                    // Comma separated use statement so run again
                    $parseAgain = true;
                    break;
                }

                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $type = T_FUNCTION;
                continue;
            }

            if ($token[0] === T_CONST) {
                $type = T_CONST;
                continue;
            }

            if ($token[0] === T_NAME_QUALIFIED) {
                $name = $token[1];
                continue;
            }

            if ($token[0] === T_AS) {
                $hasAs = true;
                continue;
            }

            if ($token[0] === T_STRING) {
                // When using non namespaced classes
                if ($name === null) {
                    $name = $token[1];
                    continue;
                }

                if ($hasGroupNames) {
                    if ($hasAs) {
                        $hasAs = false;
                        $groupAs[count($groupAs) - 1] = $token[1];
                    } else {
                        $groupNames[] = $token[1];
                        $groupAs[] = null;
                    }
                } elseif ($hasAs) {
                    $hasAs = false;
                    $as = $token[1];
                }
            }
        }

        if ($name === null) {
            throw new UnexpectedValueException(
                'Use fully qualified name not set.'
            );
        }

        if ($groupNames) {
            foreach ($groupNames as $key => $value) {
                if ($type === T_FUNCTION) {
                    $this->meta->addFunctionUse(
                        $name . '\\' . $value,
                        $groupAs[$key]
                    );
                } elseif ($type === T_CONST) {
                    $this->meta->addConstantUse(
                        $name . '\\' . $value,
                        $groupAs[$key]
                    );
                } else {
                    $this->meta->addClassUse(
                        $name . '\\' . $value,
                        $groupAs[$key]
                    );
                }
            }
        } else {
            if ($type === T_FUNCTION) {
                $this->meta->addFunctionUse($name, $as);
            } elseif ($type === T_CONST) {
                $this->meta->addConstantUse($name, $as);
            } else {
                $this->meta->addClassUse($name, $as);
            }
        }

        if ($parseAgain) {
            // Insert type if not class for next parse
            if ($type === T_FUNCTION || $type === T_CONST) {
                $tokens[] = [
                    $type,
                    '',
                    0,
                ];
            }

            return $this->parseUse($tokens);
        }

        return $tokens;
    }

    /**
     * Parses out tokens relating to defined constants.
     *
     * This doesn't apply to defines defined in functions or methods.
     *
     * @param array<int, string|array{int,string,int}> $tokens An array of tokens.
     *
     * @return array<int, string|array{int,string,int}> The remaining tokens.
     */
    private function parseDefine(array $tokens): array
    {
        if (!$this->config->makeDefines) {
            // Skip over define tokens
            while ($token = array_pop($tokens)) {
                if ($token === ';') {
                    break;
                }
            }

            return $tokens;
        }

        $name = '';
        $nameValue = null;
        $define = null;
        $inValue = false;
        $value = '';

        while ($token = array_pop($tokens)) {
            if ($inValue) {
                if ($token === ';') {
                    if ($define === null) {
                        throw new UnexpectedValueException(
                            'Xlient PHPConstant instance not set.'
                        );
                    }

                    // Remove closing ')' of define
                    $value = rtrim(substr(trim($value), 0, -1));
                    $define->setValue($value);
                    break;
                }

                if (is_array($token)) {
                    if ($token[0] === T_NAME_FULLY_QUALIFIED ||
                        $token[0] === T_STRING
                    ) {
                        $hash = $this->meta->addName($token[1]);
                        $value .= $hash;
                    } else {
                        $value .= $token[1];
                    }
                } else {
                    $value .= $token;
                }

                continue;
            }

            if ($token === '(') {
                continue;
            }

            if ($token === ',') {
                if ($nameValue === null) {
                    throw new UnexpectedValueException(
                        'The define\'s name value was not set.'
                    );
                }

                // Eval name to get actual name
                eval('$name = ' . $nameValue . ';');

                // Defines that start with '\' are in the default namespace
                // and need to be called with constant()
                if (str_starts_with($name, '\\')) {
                    $defineNamespace = '';
                } else {
                    $defineNamespace = explode('\\', $name);
                    array_pop($defineNamespace);
                    $defineNamespace = implode('\\', $defineNamespace);
                }

                $constants = $this->getPhpConstantsDoc('\\' . $defineNamespace);

                $define = new PhpConstant('\\' . $name);

                $define->setDefined(true);
                $define->setDefinedName(trim($nameValue));

                if ($this->docComment) {
                    $define->setDocComment($this->docComment);
                    $this->docComment = null;
                }

                $constants->add($define);

                $inValue = true;

                continue;
            }

            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $nameValue = $token[1];
                continue;
            }
        }

        return $tokens;
    }

    /**
     * Parses out tokens relating to the current class, interface, trait, or
     * enum.
     *
     * @param array<int, string|array{int,string,int}> $tokens An array of tokens.
     *
     * @return array<int, string|array{int,string,int}> The remaining tokens.
     */
    private function parseClass(array $tokens, int $type): array
    {
        $depth = $this->depth;
        $inBlock = false;
        $class = null;

        // TODO Method meta.
        while ($token = array_pop($tokens)) {
            if ($token === '{') {
                if ($this->depth === $depth) {
                    $inBlock = true;
                }

                ++$this->depth;
                continue;
            } elseif ($token === '}') {
                --$this->depth;

                if ($this->depth === $depth) {
                    break;
                }
                continue;
            }

            if (!$inBlock && $token[0] === T_STRING) {
                if ($class !== null) {
                    continue;
                }

                // In case self reference
                $this->meta->addClassUse($this->namespace . $token[1]);

                if ($type === T_CLASS) {
                    $class = new PhpClassDoc(
                        $this->namespace . $token[1],
                        $this->destDir,
                        $this->config,
                        $this->meta,
                    );

                    if ($this->docComment) {
                        $class->setDocComment($this->docComment);
                        $this->docComment = null;
                    }

                    $this->classes[] = $class;
                } elseif ($type === T_INTERFACE) {
                    $class = new PhpInterfaceDoc(
                        $this->namespace . $token[1],
                        $this->destDir,
                        $this->config,
                        $this->meta,
                    );

                    if ($this->docComment) {
                        $class->setDocComment($this->docComment);
                        $this->docComment = null;
                    }

                    $this->interfaces[] = $class;
                } elseif ($type === T_TRAIT) {
                    $class = new PhpTraitDoc(
                        $this->namespace . $token[1],
                        $this->destDir,
                        $this->config,
                        $this->meta,
                    );

                    if ($this->docComment) {
                        $class->setDocComment($this->docComment);
                        $this->docComment = null;
                    }

                    $this->traits[] = $class;
                } elseif ($type === T_ENUM) {
                    $class = new PhpEnumDoc(
                        $this->namespace . $token[1],
                        $this->destDir,
                        $this->config,
                        $this->meta,
                    );

                    if ($this->docComment) {
                        $class->setDocComment($this->docComment);
                        $this->docComment = null;
                    }

                    $this->enums[] = $class;
                }
            }
        }

        return $tokens;
    }

    /**
     * Parses out tokens relating to the current function.
     *
     * @param array<int, string|array{int,string,int}> $tokens An array of tokens.
     *
     * @return array<int, string|array{int,string,int}> The remaining tokens.
     */
    private function parseFunction(array $tokens): array
    {
        $depth = $this->depth;
        $inBlock = false;
        $function = null;

        // TODO Function meta
        while ($token = array_pop($tokens)) {
            if ($token === '{') {
                if ($this->depth === $depth) {
                    $inBlock = true;
                }

                ++$this->depth;
                continue;
            } elseif ($token === '}') {
                --$this->depth;

                if ($this->depth === $depth) {
                    break;
                }
                continue;
            }

            if (!$inBlock && $function === null && $token[0] === T_STRING) {
                if (!$this->config->makeFunctions) {
                    continue;
                }

                $functions = $this->getPhpFunctionsDoc($this->namespace);

                $function = new PhpFunction(
                    $this->namespace . $token[1],
                );

                if ($this->docComment) {
                    $function->setDocComment($this->docComment);
                    $this->docComment = null;
                }

                $functions->add($function);
            }
        }

        return $tokens;
    }

    /**
     * Parses out tokens relating to constants.
     *
     * This doesn't apply to constants in classes.
     *
     * @param array<int, string|array{int,string,int}> $tokens An array of tokens.
     *
     * @return array<int, string|array{int,string,int}> The remaining tokens.
     */
    private function parseConstant(array $tokens): array
    {
        $inValue = false;
        $value = '';

        if (!$this->config->makeConstants) {
            // Skip over constant tokens
            while ($token = array_pop($tokens)) {
                if ($token === ';') {
                    break;
                }
            }

            return $tokens;
        }

        $constant = null;

        while ($token = array_pop($tokens)) {
            if (!$inValue) {
                if ($token === '=') {
                    $inValue = true;
                    continue;
                }

                if ($token[0] === T_STRING) {
                    $this->meta->addConstantUse($this->namespace . $token[1]);

                    $constants = $this->getPhpConstantsDoc(
                        rtrim($this->namespace, '\\')
                    );

                    $constant = new PhpConstant(
                        $this->namespace . $token[1],
                    );

                    if ($this->docComment) {
                        $constant->setDocComment($this->docComment);
                        $this->docComment = null;
                    }

                    $constants->add($constant);
                    continue;
                }

                continue;
            }

            if ($token === ';') {
                if ($constant === null) {
                    throw new UnexpectedValueException(
                        'Xlient PHPConstant instance not set.'
                    );
                }

                $constant->setValue(trim($value));
                break;
            }

            if (is_array($token)) {
                if ($token[0] === T_NAME_FULLY_QUALIFIED ||
                    $token[0] === T_STRING
                ) {
                    $hash = $this->meta->addName($token[1]);
                    $value .= $hash;
                } else {
                    $value .= $token[1];
                }
            } else {
                $value .= $token;
            }
        }

        return $tokens;
    }

    /**
     * Gets a PhpFunctionsDoc for the specified namespace.
     *
     * This acts as a collection of functions to output to a single
     * documentation file.
     *
     * @param string $namespace The namespace of this PhpFunctionsDoc.
     *
     * @return PhpFunctionsDoc A class representing functions contained in
     *  this namespace.
     */
    private function getPhpFunctionsDoc(string $namespace): PhpFunctionsDoc
    {
        if (!array_key_exists($namespace, $this->functions)) {
            $this->functions[$namespace] = new PhpFunctionsDoc(
                $namespace . '\\' . $this->config->functionsFilename,
                $this->destDir,
                $this->config,
                $this->meta,
            );
        }

        return $this->functions[$namespace];
    }

    /**
     * Gets a PhpConstantsDoc for the specified namespace.
     *
     * This acts as a collection of defines/constants to output to a single
     * documentation file.
     *
     * @param string $namespace The namespace of this PhpConstantsDoc.
     *
     * @return PhpConstantsDoc A class representing defines/constants
     *  contained in this namespace.
     */
    private function getPhpConstantsDoc(string $namespace): PhpConstantsDoc
    {
        if (!array_key_exists($namespace, $this->constants)) {
            $this->constants[$namespace] = new PhpConstantsDoc(
                $namespace . '\\' . $this->config->constantsFilename,
                $this->destDir,
                $this->config,
                $this->meta,
            );
        }

        return $this->constants[$namespace];
    }
}

// ✝
