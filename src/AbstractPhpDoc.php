<?php
namespace Xlient\Doc\Php;

use InvalidArgumentException;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use ReflectionEnumBackedCase;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Reflector;
use RuntimeException;
use Xlient\Doc\Php\DocComment;
use Xlient\Doc\Php\Configuration;
use Xlient\Doc\Php\PhpFileMeta;

use function Xlient\Doc\Php\make_dir as xlient_make_dir;
use function Xlient\Doc\Php\clean_dir as xlient_clean_dir;
use function Xlient\Doc\Php\to_kebab_case as xlient_to_kebab_case;
use function Xlient\Doc\Php\markdown_escape as xlient_markdown_escape;
use function Xlient\Doc\Php\clean_var_export as xlient_clean_var_export;

use const DIRECTORY_SEPARATOR as DS;

/**
 * A partial documentation file implementation that all others inherit from.
 */
abstract class AbstractPhpDoc
{
    /**
     * @var string|null A PHPDoc comment.
     */
    private ?string $docComment = null;

    /**
     * @param string $name A fully qualified name.
     * @param string $destDir The destination directory to save documentation
     *  files.
     * @param Configuration $config The configuration to use to generate the
     *  documentation.
     * @param PhpFileMeta $meta A meta class for storing additional
     *  information about a PHP file.
     */
    public function __construct(
        private string $name,
        protected string $destDir,
        protected Configuration $config,
        protected PhpFileMeta $meta,
    ) {
        $this->name = '\\' . ltrim($name, '\\');

        $this->destDir = xlient_clean_dir($this->destDir);
        if (!is_dir($this->destDir)) {
            throw new RuntimeException(
                'The specified destination directory, ' . $this->destDir . ', was not found.'
            );
        }
    }

    /**
     * Gets the fully qualified name of this documentation item.
     *
     * @return string A fully qualified name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the PHPDoc comment associated with this documentation item.
     *
     * @return string|null A PHPDoc comment.
     */
    public function getDocComment(): ?string
    {
        return $this->docComment;
    }
    /**
     * Sets the PHPDoc comment associated with this documentation item.
     *
     * @param string|null $value A PHPDoc comment.
     *
     * @return static
     */
    public function setDocComment(?string $value): static
    {
        $this->docComment = $value;
        return $this;
    }

    /**
     * Generates all documentation files for this documentation item.
     *
     * @return array<string> An array of files.
     */
    abstract function make(): array;

    /**
     * Generates markdown for a function or class method definition.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param string|null $name An optional name override if not using the
     *  short name provided by the reflector.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     * @param string|null $anchor An anchor to use to link to this function or class
     *  method.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunction(
        ReflectionFunction|ReflectionMethod $function,
        ?string $name = null,
        int $headingDepth = 0,
        ?string $anchor = null,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        if ($anchor !== null) {
            $anchor = xlient_markdown_escape($anchor);
            $content[] = '<a id="' . $anchor . '"></a>';
        }

        if ($name !== null) {
            $content[] = $heading . '# ' . xlient_markdown_escape($name);
        } else {
            $content[] = $heading . '# ' . xlient_markdown_escape($function->getShortName());
        }

        if ($function instanceof ReflectionMethod) {
            $docComment = $this->inheritDocComment($function);
        } else {
            $docComment = $function->getDocComment() ?: '/** */';
        }

        $docComment = new DocComment($docComment);

        $marks = $this->makeMarkLabels($docComment);
        if ($marks !== null) {
            $content[] = $marks;
        }

        $description = $this->makeDescription(
            $docComment->getSummary(),
            $docComment->getDescription()
        );

        if ($description !== null) {
            $content[] = $description;
        }

        $definition = $this->getFunctionDefinition($function);
        $content[] = '```php' . "\n" . $definition . "\n" . '```';

        $content = array_merge(
            $content,
            $this->makeFunctionParameters(
                $function,
                $docComment,
                $headingDepth,
            ),
            $this->makeFunctionReturn(
                $function,
                $docComment,
                $headingDepth,
            ),
            $this->makeFunctionThrows(
                $function,
                $docComment,
                $headingDepth,
            ),
        );

        return $content;
    }

    /**
     * Generates markdown for any \@param statements found in the specified
     * PHPDoc comment.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param DocComment $docComment A PHPDoc comment.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionParameters(
        ReflectionFunction|ReflectionMethod $function,
        DocComment $docComment,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makeFunctionParametersTable(
                $function,
                $docComment,
                $headingDepth,
            );
        }

        $parameters = $function->getParameters();

        $content = [];

        if (!$parameters) {
            return $content;
        }

        $heading = str_repeat('#', $headingDepth);

        $tags = $docComment->getParamTagValues();
        $content[] = $heading . '## Parameters';

        foreach ($parameters as $value) {
            $data = $this->getFunctionParameterData($value, $tags);

            $content[] = $heading . '### ' . $data->type . ' ' . $data->name;

            if ($data->description !== null) {
                $content[] = $data->description;
            }
        }

        return $content;
    }

    /**
     * Generates markdown for any \@param statements found in the specified
     * PHPDoc comment in a table format.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param DocComment $docComment A PHPDoc comment.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionParametersTable(
        ReflectionFunction|ReflectionMethod $function,
        DocComment $docComment,
        int $headingDepth,
    ): array
    {
        $parameters = $function->getParameters();

        $content = [];

        if (!$parameters) {
            return $content;
        }

        $heading = str_repeat('#', $headingDepth);

        $tags = $docComment->getParamTagValues();
        $content[] = $heading . '## Parameters';

        $table = [];
        $table[] = '| ' . $this->config->labels['name'] . ' | ' .
            $this->config->labels['type'] . ' | ' .
            $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- | :--- |';

        foreach ($parameters as $value) {
            $data = $this->getFunctionParameterData($value, $tags);

            // Content
            $row = '| ' . $data->name . ' | ';
            $row .= $data->type ?? '';
            $row .= ' | ';
            $row .= $data->description ?? '';
            $row .= ' |';

            $table[] = $row;
        }

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Gets the markdown encoded name, type, and description of the specified
     * function parameter.
     *
     * @param ReflectionParameter $parameter A parameter reflector.
     * @param array<ParamTagValueNode> $tags An array of \@param tag values.
     *
     * @return object{
     *     name: string,
     *     type: string|null,
     *     description: string|null,
     * } An object of function parameter data.
     */
    protected function getFunctionParameterData(
        ReflectionParameter $parameter,
        array $tags,
    ): object
    {
        $name = $parameter->getName();

        // Find matching @param tag
        $tag = null;
        foreach ($tags as $value) {
            if ($value->parameterName === '$' . $name) {
                $tag = $value;
                break;
            }
        }

        // Type
        $type = null;

        if ($this->config->prioritizeDocComment) {
            $type = ($tag ? strval($tag->type) : '');
            if ($type === '') {
                $type = null;
            }
        }

        if ($type === null && $parameter->hasType()) {
            $type = $parameter->getType();

            if ($type !== null) {
                $type = $this->getTypeDefinition($type);
            }
        }

        if ($type === null) {
            $type = ($tag ? strval($tag->type) : '');
        }

        $type = xlient_markdown_escape($type);

        // Description
        if ($tag) {
            $description = $this->makeDescription(
                null,
                $tag->description
            );
        } else {
            $description = null;
        }

        // Name
        $name = '$' . $name;

        if ($parameter->isVariadic()) {
            $name = '...' . $name;
        }

        if ($parameter->isPassedByReference()) {
            $name = '&' . $name;
        }
        $name = xlient_markdown_escape($name);

        return new class($name, $type, $description) {
            public function __construct(
                public readonly string $name,
                public readonly ?string $type,
                public readonly ?string $description,
            ) {}
        };
    }

    /**
     * Generates markdown for a \@return statement if found in the specified
     * PHPDoc comment.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param DocComment $docComment A PHPDoc comment.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionReturn(
        ReflectionFunction|ReflectionMethod $function,
        DocComment $docComment,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makeFunctionReturnTable(
                $function,
                $docComment,
                $headingDepth,
            );
        }

        $content = [];

        $tags = $docComment->getReturnTagValues();
        $data = $this->getFunctionReturnData(
            $function,
            $tags ? $tags[0] : null,
        );

        if ($data->type === null && $data->description === null) {
            return $content;
        }

        $heading = str_repeat('#', $headingDepth);

        $content[] = $heading . '## ' . $this->config->labels['returns'];

        if ($data->type !== null) {
            $content[] =  $data->type;
        }

        if ($data->description !== null) {
            $content[] = $data->description;
        }

        return $content;
    }

    /**
     * Generates markdown for a \@return statement if found in the specified
     * PHPDoc comment in a table format.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param DocComment $docComment A PHPDoc comment.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionReturnTable(
        ReflectionFunction|ReflectionMethod $function,
        DocComment $docComment,
        int $headingDepth,
    ): array
    {
        $content = [];

        $tags = $docComment->getReturnTagValues();
        $data = $this->getFunctionReturnData(
            $function,
            $tags ? $tags[0] : null,
        );

        if ($data->type === null && $data->description === null) {
            return $content;
        }

        $heading = str_repeat('#', $headingDepth);

        $content[] = $heading . '## ' . $this->config->labels['returns'];

        $table = [];
        $table[] = '| ' . $this->config->labels['type'] . ' | ' . $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- |';

        $row = '| ';
        $row .= $data->type ?? '';
        $row .= ' | ';
        $row .= $data->description ?? '';
        $row .= ' |';

        $table[] = $row;

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Gets the markdown encoded type, and description of the specified
     * function return.
     *
     * @param ReflectionFunction|ReflectionMethod $function A function reflector.
     * @param ReturnTagValueNode|null $tag An \@return tag value.
     *
     * @return object{
     *     type: string|null,
     *     description: string|null,
     * } An object of function return data.
     */
    protected function getFunctionReturnData(
        ReflectionFunction|ReflectionMethod $function,
        ?ReturnTagValueNode $tag,
    ): object
    {
        $type = null;

        if ($tag !== null && $this->config->prioritizeDocComment) {
            $type = strval($tag->type);
            if ($type === '') {
                $type = null;
            }
        }

        if ($type === null && $function->hasReturnType()) {
            $type = $function->getReturnType();

            if ($type !== null) {
                $type = $this->getTypeDefinition($type);
            }
        }

        if ($tag !== null && $type === null ) {
            $type = strval($tag->type);

            if ($type === '') {
                $type = null;
            }
        }

        if ($type !== null) {
            $type = xlient_markdown_escape($type);
        }

        if ($tag !== null) {
            $description = $this->makeDescription(
                null,
                $tag->description
            );
        } else {
            $description = null;
        }

        return new class($type, $description) {
            public function __construct(
                public readonly ?string $type,
                public readonly ?string $description,
            ) {}
        };
    }

    /**
     * Generates markdown for any \@throw statements found in the specified
     * PHPDoc comment.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param DocComment $docComment A PHPDoc comment.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionThrows(
        ReflectionFunction|ReflectionMethod $function,
        DocComment $docComment,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makeFunctionThrowsTable(
                $function,
                $docComment,
                $headingDepth,
            );
        }

        $tags = $docComment->getThrowsTagValues();

        $content = [];

        if (!$tags) {
            return $content;
        }

        $heading = str_repeat('#', $headingDepth);

        $content[] = $heading . '## ' . $this->config->labels['throws'];

        foreach ($tags as $tag) {
            $type = strval($tag->type);
            $type = xlient_markdown_escape($type);

            $description = $this->makeDescription(
                null,
                $tag->description
            );

            $content[] = $heading . '### ' . $type;

            if ($description !== null) {
                $content[] = $description;
            }
        }

        return $content;
    }

    /**
     * Generates markdown for any \@throw statements found in the specified
     * PHPDoc comment in a table format.
     *
     * @param ReflectionFunction|ReflectionMethod $function A reflector for a
     *  function or class method.
     * @param DocComment $docComment A PHPDoc comment.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionThrowsTable(
        ReflectionFunction|ReflectionMethod $function,
        DocComment $docComment,
        int $headingDepth,
    ): array
    {
        $tags = $docComment->getThrowsTagValues();

        $content = [];

        if (!$tags) {
            return $content;
        }

        $heading = str_repeat('#', $headingDepth);

        $content[] = $heading . '## ' . $this->config->labels['throws'];

        $table = [];
        $table[] = '| ' . $this->config->labels['type'] . ' | ' . $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- |';

        foreach ($tags as $tag) {
            $type = strval($tag->type);
            $type = xlient_markdown_escape($type);

            $description = $this->makeDescription(
                null,
                $tag->description
            );

            $row = '| ' . $type . ' | ';
            $row .= $description ?? '';
            $row .= ' |';

            $table[] = $row;
        }

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Generates markdown for an enum case definition.
     *
     * @param ReflectionEnumUnitCase $case A reflector for an enum case.
     * @param string|null $name An optional name override if not using the
     *  name provided by the reflector.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     * @param string|null $anchor An anchor to use to link to this function or class
     *  method.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCase(
        ReflectionEnumUnitCase $case,
        ?string $name = null,
        int $headingDepth = 0,
        ?string $anchor = null,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        if ($anchor !== null) {
            $anchor = xlient_markdown_escape($anchor);
            $content[] = '<a id="' . $anchor . '"></a>';
        }

        if ($name !== null) {
            $content[] = $heading . '# ' . xlient_markdown_escape($name);
        } else {
            $content[] = $heading . '# ' . xlient_markdown_escape($case->getName());
        }

        $docComment = new DocComment(
            $this->inheritDocComment($case)
        );

        $marks = $this->makeMarkLabels($docComment);
        if ($marks !== null) {
            $content[] = $marks;
        }

        $description = $this->makeDescription(
            $docComment->getSummary(),
            $docComment->getDescription()
        );

        if ($description !== null) {
            $content[] = $description;
        }

        $definition = $this->getClassCaseDefinition($case);
        $content[] = '```php' . "\n" . $definition . "\n" . '```';

        return $content;

    }

    /**
     * Generates markdown for a constant definition.
     *
     * @param ReflectionClassConstant $constant A reflector for a constant.
     * @param string|null $name An optional name override if not using the
     *  name provided by the reflector.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     * @param string|null $anchor An anchor to use to link to this function or class
     *  method.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstant(
        ReflectionClassConstant $constant,
        ?string $name = null,
        int $headingDepth = 0,
        ?string $anchor = null,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        if ($anchor !== null) {
            $anchor = xlient_markdown_escape($anchor);
            $content[] = '<a id="' . $anchor . '"></a>';
        }

        if ($name !== null) {
            $content[] = $heading . '# ' . xlient_markdown_escape($name);
        } else {
            $content[] = $heading . '# ' . xlient_markdown_escape($constant->getName());
        }

        $docComment = new DocComment(
            $this->inheritDocComment($constant)
        );

        $marks = $this->makeMarkLabels($docComment);
        if ($marks !== null) {
            $content[] = $marks;
        }

        $description = $this->makeDescription(
            $docComment->getSummary(),
            $docComment->getDescription()
        );

        if ($description !== null) {
            $content[] = $description;
        }

        $definition = $this->getClassConstantDefinition($constant);
        $content[] = '```php' . "\n" . $definition . "\n" . '```';

        return $content;

    }

    /**
     * Generates markdown for a property definition.
     *
     * @param ReflectionProperty $property A reflector for a property.
     * @param string|null $name An optional name override if not using the
     *  name provided by the reflector.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     * @param string|null $anchor An anchor to use to link to this function or class
     *  method.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeProperty(
        ReflectionProperty $property,
        ?string $name = null,
        int $headingDepth = 0,
        ?string $anchor = null,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        if ($anchor !== null) {
            $anchor = xlient_markdown_escape($anchor);
            $content[] = '<a id="' . $anchor . '"></a>';
        }

        if ($name !== null) {
            $content[] = $heading . '# ' . xlient_markdown_escape($name);
        } else {
            $content[] = $heading . '# $' . xlient_markdown_escape($property->getName());
        }

        $docComment = new DocComment(
            $this->inheritDocComment($property)
        );

        $marks = $this->makeMarkLabels($docComment);
        if ($marks !== null) {
            $content[] = $marks;
        }

        $description = $this->makeDescription(
            $docComment->getSummary(),
            $docComment->getDescription()
        );

        if ($description !== null) {
            $content[] = $description;
        }

        $definition = $this->getPropertyDefinition($property);
        $content[] = '```php' . "\n" . $definition . "\n" . '```';

        return $content;

    }

    /**
     * Generates markdown for a PHPDoc comment's summary and description.
     *
     * @param string|null $summary The PHPDoc comment summary.
     * @param string|null $description The PHPDoc comment description.
     *
     * @return string|null A markdown string.
     */
    protected function makeDescription(
        ?string $summary,
        ?string $description
    ): ?string
    {
        $description = trim($summary . "\n\n" . $description);

        $description = str_replace( "\r", '', $description);

        $lines = explode("\n\n", $description);

        foreach ($lines as $key => $line) {
            $line = explode("\n", $line);
            $line = array_map('trim', $line);
            $line = implode(' ', $line);

            if ($this->config->escapeDocComments) {
                $line = xlient_markdown_escape($line);
            }

            $lines[$key] = $line;
        }

        $description = trim(implode("\n\n", $lines));

        if ($description === '') {
            return null;
        }

        return $description;
    }

    /**
     * Gets a file for the specified fully qualified name.
     *
     * If a name is not speciifed, the documentation file's name will be used.
     *
     * @param string|null $name A fully qualified name.
     *
     * @return string A file.
     */
    protected function getFile(?string $name = null): string
    {
        $name ??= $this->getName();

        $name = '\\' . ltrim($name, '\\');

        $dir = $this->destDir . DS . $this->getDirPath($name);
        if (!file_exists($dir)) {
            xlient_make_dir($dir);
        }

        return $dir . DS . $this->getFilename($name);
    }

    /**
     * Gets a URL for the specified fully qualified name.
     *
     * If a name is not specified, the documentation file's name will be used.
     *
     * @param string|null $name A fully qualified name.
     *
     * @return string|null A URL.
     */
    protected function getUrl(?string $name = null): ?string
    {
        $name ??= $this->getName();

        $name = '\\' . ltrim($name, '\\');

        $matchingNamespace = null;
        foreach ($this->config->baseNamespaces as $namespace) {
            if (str_starts_with($name, $namespace)) {
                $matchingNamespace = rtrim($namespace, '\\');
                break;
            }
        }

        if ($matchingNamespace === null) {
            return $this->getExternalUrl($name);
        }

        $baseUrl = $this->config->baseUrls[$matchingNamespace] ?? null;
        if ($baseUrl === null) {
            return null;
        }

        $url = $baseUrl . $this->getUrlPath($name);

        $url .= '/' . $this->getFilename($name);

        return $url;
    }

    /**
     * Gets an external URL for the specified fully qualified name if one can
     * be determined.
     *
     * @param string $name A fully qualified name.
     *
     * @return string|null An external URL.
     */
    protected function getExternalUrl(string $name): ?string
    {
        $name = '\\' . ltrim($name, '\\');

        $url = null;

        foreach ($this->config->namespaceUrls as $namespace => $externalUrl) {
            $namespace = rtrim($namespace, '\\') . '\\';

            if (!str_starts_with($name, $namespace)) {
                continue;
            }

            $url = $externalUrl;

            if (str_contains($externalUrl, '{php_doc}')) {
                // \Random\Engine\Secure -> class.random-engine-secure.php
                $path = ltrim($name, '\\');
                $path = strtolower($path);
                $path = 'class.' . str_replace('\\', '-', $path) . '.php';

                $url = str_replace('{php_doc}', $path, $url);
            }

            break;
        }

        if ($this->config->urlCallback !== null) {
            $url = $this->config->urlCallback($name, $url);
        }

        return $url;
    }

    /**
     * Gets an anchor from a fully qualified name.
     *
     * @param string $name A fully qualified name.
     *
     * @return string An anchor.
     */
    protected function getAnchor(string $name): string
    {
        // Constants and enum cases
        if (strtoupper($name) === $name && str_contains($name, '_')) {
            $name = strtolower($name);
        }

        $name = str_replace(
            array_keys($this->config->pathFixes),
            array_values($this->config->pathFixes),
            $name
        );

        $name = xlient_to_kebab_case($name);

        return $name;
    }

    /**
     * Gets a URL path from the specified fully qualified name.
     *
     * If a name is not specified, the documentation file's name will be used.
     *
     * @param string|null $name A fully qualified name.
     *
     * @return string A URL path.
     */
    protected function getUrlPath(?string $name): string
    {
        $path = $this->getDirPath($name);

        if (DS !== '/') {
            $path = str_replace(DS, '/', $path);
        }

        return $path;
    }

    /**
     * Gets a directory path for the specified fully qualified name.
     *
     * If a name is not specified, the documentation file's name will be used.
     *
     * @param string|null $name A fully qualified name.
     *
     * @return string A directory path.
     */
    protected function getDirPath(?string $name = null): string
    {
        $name ??= $this->getName();

        $name = '\\' . ltrim($name, '\\');

        $matchingNamespace = null;
        foreach ($this->config->baseNamespaces as $namespace) {
            if (str_starts_with($name, $namespace)) {
                $name = substr($name, strlen($namespace));
                $matchingNamespace = rtrim($namespace, '\\');
                break;
            }
        }

        if ($matchingNamespace === null) {
            throw new InvalidArgumentException(
                'Name is not contained in the base namespace. (' . $name . ')'
            );
        }

        // Prevent \IO\ becoming -i-o- instead of -io-, etc
        $paths = str_replace(
            array_keys($this->config->pathFixes),
            array_values($this->config->pathFixes),
            $name
        );

        $paths = explode('\\', ltrim($paths, '\\'));
        array_pop($paths);

        $basePath = $this->config->basePaths[$matchingNamespace] ?? '';

        if (!$paths) {
            return $basePath;
        }

        foreach ($paths as $key => $value) {
            $value = xlient_to_kebab_case($value);
            $paths[$key] = $value;
        }

        $path = $basePath . DS . implode(DS, $paths);

        return $path;
    }

    /**
     * Gets a filename from the specified fully qualified name.
     *
     * If a name is not specified, the documentation file's name will be used.
     *
     * @param string|null $name A fully qualified name.
     *
     * @return string A filename.
     */
    protected function getFilename( ?string $name = null): string
    {
        $name ??= $this->getName();

        return $this->getClassFilename($name);
    }

    /**
     * Gets a filename from the specified fully qualified name, prefix and
     * suffix.
     *
     * @internal
     *
     * @param string $name A fully qualified name.
     * @param string $prefix A value to prepend before the name.
     * @param string $suffix A value to append after the name.
     *
     * @return string A filename.
     */
    protected function getClassFilename(
        string $name,
        string $prefix = '',
        string $suffix = '',
    ): string
    {
        $name = ltrim($name, '\\');

        $filename = explode('\\', $name);
        $filename = array_pop($filename);

        $filename = str_replace(
            array_keys($this->config->pathFixes),
            array_values($this->config->pathFixes),
            $filename
        );

        $filename = $prefix . xlient_to_kebab_case($filename) . $suffix;
        $filename .= '.md';

        return $filename;
    }

    /**
     * Gets a PHP code definition for the specified class reflector.
     *
     * @param ReflectionClass $class A class reflector.
     *
     * @return string A PHP code definition.
     */
    protected function getClassDefinition(
        ReflectionClass $class
    ): string
    {
        $content = [];

        if ($class->isFinal()) {
            if (!$class instanceof ReflectionEnum) {
                $content[] = 'final';
            }
        } elseif ($class->isAbstract()) {
            $content[] = 'abstract';
        }

        // PHP >= 8.2
        if (method_exists($class, 'isReadOnly')) {
            if ($class->isReadOnly()) {
                $content[] = 'readonly';
            }
        }

        if ($class->isEnum()) {
            $content[] = 'enum';
        } elseif ($class->isInterface()) {
            $content[] = 'interface';
        } elseif ($class->isTrait()) {
            $content[] = 'trait';
        } else {
            $content[] = 'class';
        }

        $name = $class->getShortName();
        if ($class instanceof ReflectionEnum && $class->isBacked()) {
            $type = $class->getBackingType();
            if ($type) {
                $name .= ': ' . $this->getTypeDefinition($type);
            }
        }

        $content[] = $name;

        $parentClass = $class->getParentClass();
        if ($parentClass !== false) {
            $content[] = 'extends \\' . $parentClass->getName();
        }

        $interfaces = $this->getClassInterfaces($class);

        if ($interfaces) {
            $content[] = 'implements';

            $content = [implode(' ', $content)];

            if (count($interfaces) > 1 ||
                strlen($content[0] . ' ' . $interfaces[0]) > 80
            ) {
                $indent = str_pad(' ', $this->config->indentLength, ' ');
                $content[] = "\n" . $indent . "\\" . implode(",\n    \\", $interfaces);
            } else {
                $content[] = '\\' . implode(', \\', $interfaces);
            }
        }

        return implode(' ', $content);
    }

    /**
     * Gets an array of fully qualified interface names implemented by the
     * class of the class reflector.
     *
     * @param ReflectionClass $class A class reflector.
     *
     * @return array<string> An array of fully qualified interface names.
     */
    protected function getClassInterfaces(
        ReflectionClass $class
    ): array
    {
        $interfaces = $class->getInterfaceNames();

        foreach ($interfaces as $key => $value) {
            if ($class instanceof ReflectionEnum) {
                if ($value === 'UnitEnum' ||
                    $value === 'BackedEnum'
                ) {
                    unset($interfaces[$key]);
                    continue;
                }
            }

            $interfaces[$key] = '\\' . $value;
        }

        return array_values($interfaces);
    }

    /**
     * Gets an array of class constant reflectors that exist in the specified
     * class reflector.
     *
     * @param ReflectionClass $class A class reflector.
     * @param int $filter An optional filter for filtering based on access
     *  modifiers.
     *
     * @return array<ReflectionClassConstant> An array of class constant
     *  reflectors.
     */
    protected function getClassReflectionConstants(
        ReflectionClass $class,
        int $filter = 0
    ): array
    {
        $constants = $class->getReflectionConstants($filter);

        if ($class instanceof ReflectionEnum) {
            foreach ($constants as $key => $value) {
                if ($class->hasCase($value->getName())) {
                    unset($constants[$key]);
                }
            }

            $constants = array_values($constants);
        }

        $constants = $this->removeIgnoreableReflectors($constants);

        return $constants;
    }

    /**
     * Gets an array of property reflectors that exist in the specified
     * class reflector.
     *
     * @param ReflectionClass $class A class reflector.
     * @param int $filter An optional filter for filtering based on access
     *  modifiers.
     *
     * @return array<ReflectionProperty> An array of property reflectors.
     */
    protected function getClassReflectionProperties(
        ReflectionClass $class,
        int $filter = 0
    ): array
    {
        $properties = $class->getProperties($filter);

        if ($class instanceof ReflectionEnum) {
            foreach ($properties as $key => $value) {
                if ($value->getName() === 'name' ||
                    $value->getName() === 'value'
                ) {
                    unset($properties[$key]);
                }
            }

            $properties = array_values($properties);
        }

        $properties = $this->removeIgnoreableReflectors($properties);

        return $properties;
    }

    /**
     * Gets an array of method reflectors that exist in the specified
     * class reflector.
     *
     * @param ReflectionClass $class A class reflector.
     * @param int $filter An optional filter for filtering based on access
     *  modifiers.
     *
     * @return array<ReflectionMethod> An array of method reflectors.
     */
    protected function getClassReflectionMethods(
        ReflectionClass $class,
        int $filter = 0
    ): array
    {
        $methods = $class->getMethods($filter);

        if ($class instanceof ReflectionEnum) {
            foreach ($methods as $key => $value) {
                if ($value->getName() === 'cases' ||
                    $value->getName() === 'from' ||
                    $value->getName() === 'tryFrom'
                ) {
                    unset($methods[$key]);
                }
            }

            $methods = array_values($methods);
        }

        $methods = $this->removeIgnoreableReflectors($methods);

        return $methods;
    }

    /**
     * Removes any reflectors from the specified array that the current
     * configuration indicates should be removed.
     *
     * @template T of ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionEnumUnitCase
     * @param array<T> $reflectors An array of reflectors.
     *
     * @return array<T> An array of reflectors.
     */
    protected function removeIgnoreableReflectors(array $reflectors): array
    {
        if ($this->config->showDeprecated &&
            $this->config->showInternal &&
            $this->config->showGenerated
        ) {
            return $reflectors;
        }

        foreach ($reflectors as $key => $value) {
            if ($value->getDocComment() !== false) {
                $docComment = new DocComment($value->getDocComment());

                if (!$this->config->showDeprecated &&
                    $docComment->isDeprecated()
                ) {
                    unset($reflectors[$key]);
                    continue;
                }

                if (!$this->config->showInternal &&
                    $docComment->isInternal()
                ) {
                    unset($reflectors[$key]);
                }

                if (!$this->config->showGenerated &&
                    $docComment->isGenerated()
                ) {
                    unset($reflectors[$key]);
                }
            }
        }

        return $reflectors;
    }

    /**
     * Gets a PHP code definition for the specified enumeration case reflector.
     *
     * @param ReflectionEnumUnitCase $case An enumeration case reflector.
     *
     * @return string A PHP code definition.
     */
    protected function getClassCaseDefinition(
        ReflectionEnumUnitCase $case,
    ): string
    {
        $content = [];

        $content[] = 'case';

        $content[] = $case->getName();

        if ($case instanceof ReflectionEnumBackedCase) {
            $value = var_export($case->getBackingValue(), true);
            $value = xlient_clean_var_export($value);
            $content[] = '= ' . $value;
        }

        return implode(' ', $content) . ';';
    }

    /**
     * Gets a PHP code definition for the specified class constnat reflector.
     *
     * @param ReflectionClassConstant $constant A enum case reflector.
     *
     * @return string A PHP code definition.
     */
    protected function getClassConstantDefinition(
        ReflectionClassConstant $constant,
    ): string
    {
        $content = [];

        if ($constant->isFinal()) {
            $content[] = 'final';
        }

        if ($constant->isPublic()) {
            $content[] = 'public';
        } elseif ($constant->isProtected()) {
            $content[] = 'protected';
        } elseif ($constant->isPrivate()) {
            $content[] = 'private';
        }

        $content[] = 'const';

        $content[] = $constant->getName();

        // TODO: Parse it out of the file like we do with functions
        // or perhaps have to go the token method since no easy way of
        // getting position in file.
        // Also need to handle promoted properties
        $value = var_export($constant->getValue(), true);
        $value = xlient_clean_var_export($value);
        $content[] = '= ' . $value;

        return implode(' ', $content) . ';';
    }

    /**
     * Gets a PHP code definition for the specified property reflector.
     *
     * @param ReflectionProperty $property A property reflector.
     *
     * @return string A PHP code definition.
     */
    protected function getPropertyDefinition(
        ReflectionProperty $property,
    ): string
    {
        $content = [];

        if ($property->isPublic()) {
            $content[] = 'public';
        } elseif ($property->isProtected()) {
            $content[] = 'protected';
        } elseif ($property->isPrivate()) {
            $content[] = 'private';
        }

        if ($property->isStatic()) {
            $content[] = 'static';
        }

        if ($property->isReadOnly()) {
            $content[] = 'readonly';
        }

        $type = $property->getType();
        if ($type) {
            $content[] = $this->getTypeDefinition($type);
        }

        $content[] = '$' . $property->getName();

        // TODO: Parse it out of the file like we do with functions
        // or perhaps have to go the token method since no easy way of
        // getting position in file.
        // Also need to handle promoted properties
        if ($property->hasDefaultValue()) {
            $value = var_export($property->getDefaultValue(), true);
            $value = xlient_clean_var_export($value);
            $content[] = '= ' . $value . ';';
        }

        return implode(' ', $content);
    }

    /**
     * Gets a PHP code definition for the specified function or method
     * reflector.
     *
     * @param ReflectionFunction|ReflectionMethod $function A function or
     * method reflector.
     *
     * @return string A PHP code definition.
     */
    protected function getFunctionDefinition(
        ReflectionFunction|ReflectionMethod $function
    ): string
    {
        // Start
        $start = '';

        if ($function instanceof ReflectionMethod) {
            if ($function->isFinal()) {
                $start .= 'final ';
            } elseif ($function->isAbstract()) {
                $start .= 'abstract ';
            }

            if ($function->isPublic()) {
                $start .= 'public ';
            } elseif ($function->isProtected()) {
                $start .= 'protected ';
            } elseif ($function->isPrivate()) {
                $start .= 'private ';
            }

            if ($function->isStatic()) {
                $start .= 'static ';
            }
        }

        $start .= 'function ' . $function->getShortName() . '(';

        // End
        $end = ')';

        $type = $function->getReturnType();
        if ($type) {
            $end .= ': ' . $this->getTypeDefinition($type);
        }

        // Keep track of definition length to determine if parameters shoud
        // be displayed one per line
        $len = strlen($start . $end);

        // Parameters
        $defaultParameterValues = $this->getDefaultParameterValues($function);

        $parameters = [];
        foreach ($function->getParameters() as $parameter) {
            $definition = '';

            $type = $parameter->getType();
            if ($type) {
                $definition .= $this->getTypeDefinition($type) . ' ';
            }

            if ($parameter->isPassedByReference()) {
                $definition .= '&';
            }

            if ($parameter->isVariadic()) {
                $definition .= '...';
            }

            $name = $parameter->getName();
            $definition .= '$' . $name;

            if ($parameter->isDefaultValueAvailable()) {
                if ($function->isUserDefined() &&
                    array_key_exists($name, $defaultParameterValues) &&
                    $defaultParameterValues[$name] !== null
                ) {
                    $definition .= ' = ' . $defaultParameterValues[$name];
                } elseif ($parameter->isDefaultValueConstant()) {
                    $definition .= ' = ' . $parameter->getDefaultValueConstantName();
                } else {
                    $value = var_export($parameter->getDefaultValue(), true);
                    $value = xlient_clean_var_export($value);
                    $definition .= ' = ' . $value;
                }
            }

            $len += strlen($definition);

            $parameters[] = $definition;
        }

        $len += (count($parameters) - 1) * 2; // Parameter separator ', '

        $hasNewlines = false;
        // TODO: Make this a config option.
        if ($len > 80) {
            $hasNewlines = true;
        } else {
            foreach ($parameters as $key => $value) {
                if (str_contains($value, "\n")) {
                    $hasNewlines = true;
                    break;
                }
            }
        }

        if ($hasNewlines) {
            $indent = str_pad(' ', $this->config->indentLength, ' ');

            $parameters = "\n" . $indent .
                implode(",\n" . $indent, $parameters) .
                ",\n";
        } else {
            $parameters = implode(', ', $parameters);
        }

        return $start . $parameters . $end;
    }

    /**
     * Gets a PHP code definition for the specified type.
     *
     * @param ReflectionType $type A type.
     *
     * @return string A PHP code definition.
     */
    protected function getTypeDefinition(ReflectionType $type): string
    {
        if ($type instanceof ReflectionIntersectionType) {
            $parts = [];

            foreach ($type->getTypes() as $value) {
                $parts[] = $this->getNameFromType($value);
            }

            $parts = $this->sortTypes($parts);

            return implode('&', $parts);
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];

            foreach ($type->getTypes() as $value) {
                $name = $this->getNameFromType($value);

                $parts[] = $name;
            }

            $parts = $this->sortTypes($parts);

            return implode('|', $parts);
        }

        if (!$type instanceof ReflectionNamedType) {
            return '';
        }

        $parts = [];
        $hasNullable = false;

        $typeName = $this->getNameFromType($type);

        if ($type->allowsNull() && $typeName !== 'mixed') {
            if ($this->config->useNullableSyntax) {
                $hasNullable = true;
            } else {
                $parts[] = 'null';
            }
        }

        $parts[] = $typeName;

        $parts = $this->sortTypes($parts);

        $parts = implode('|', $parts);

        if ($hasNullable) {
            $parts = '?' . $parts;
        }

        return $parts;
    }

    /**
     * Gets a name from the specified named type.
     *
     * @param ReflectionNamedType $type A named type.
     *
     * @return string A name.
     */
    private function getNameFromType(ReflectionNamedType $type): string
    {
        if ($type->isBuiltin()) {
            return $type->getName();
        }

        $name = $type->getName();

        if (in_array($name, ['static', 'self'])) {
            return $name;
        }

        return '\\' . $name;
    }

    /**
     * Sorts types by the configuration's type order.
     *
     * @param array<string> $types An array of types.
     *
     * @return array<string> A sorted array of types.
     */
    private function sortTypes(array $types): array
    {
        $classNames = [];
        $orderedTypes = [];

        foreach ($types as $type) {
            if (str_starts_with($type, '\\')) {
                $classNames[] = $type;
            }
        }

        foreach ($this->config->typeOrder as $value) {
            if ($value === PhpType::CLASS_NAME) {
                $orderedTypes = array_merge($orderedTypes, $classNames);
                continue;
            }

            if (in_array($value->value, $types)) {
                $orderedTypes[] = $value->value;
            }
        }

        foreach ($types as $type) {
            if (!in_array($type, $orderedTypes)) {
                $orderedTypes[] = $type;
            }
        }

        return $orderedTypes;
    }

    /**
     * Gets a type definition of the specified value.
     *
     * @param mixed $value The value to get a type from.
     *
     * @return string A type definition.
     */
    protected function getTypeDefinitionFromValue(mixed $value): string
    {
        $type = get_debug_type($value);

        if (in_array($type, [
            'null', 'bool', 'int', 'float', 'string', 'array'
        ])) {
            return $type;
        }

        if (str_starts_with($type, 'resource ')) {
            $type = 'resource';
            return $type;
        }

        // TODO: Not sure how this should be displayed
        // Maybe make it a param
        if ($type === 'class@anonymous') {
            return $type;
        }

        return '\\' . $type;
    }

    /**
     * Gets an array of default function or method parameter values.
     *
     * @param ReflectionFunction|ReflectionMethod $function A function
     *  reflector to get default parameter values from.
     *
     * @return array<string, string|null> An array of default parameter values.
     *
     * @todo Replace with values gotten during PhpFileParser tokenization.
     */
    protected function getDefaultParameterValues(
        ReflectionFunction|ReflectionMethod $function
    ): array
    {
        if (!$function->isUserDefined()) {
            return [];
        }

        $filename = $function->getFileName();
        $startLine = $function->getStartLine();
        $endLine = $function->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            return [];
        }

        --$startLine;

        $length = $endLine - $startLine;

        $source = file($filename);

        if ($source === false) {
            throw new RuntimeException('Function file could not be read.');
        }

        $values = implode('', array_slice($source, $startLine, $length));

        $indent = str_pad(' ', $this->config->indentLength, ' ');

        // Replace tabs with 4 spaces
        $values = str_replace("\t", $indent, $values);

        // Determine base indent of function in file
        $baseIndent = strlen($values) - strlen(ltrim($values));
        $baseIndent = str_pad(' ', $baseIndent, ' ');

        $pos = strpos($values, '(');
        if ($pos !== false) {
            $values = substr($values, $pos + 1);
        }

        $pos = strpos($values, '{');
        if ($pos !== false) {
            $values = substr($values, 0, $pos);
            $values = rtrim($values);
        }

        $pos = strrpos($values, ')');
        if ($pos !== false) {
            $values = substr($values, 0, $pos);
        }

        $values = str_replace("\r", '', $values);

        $values = trim($values);

        if ($values === '') {
            return [];
        }

        $hasNewlines = false;
        if (str_contains($values, "\n")) {
            $hasNewlines = true;
        }

        $index = -1;
        $depth = 0;
        $posStart = 0;
        $name = null;
        $value = null;
        $inName = false;
        $inValue = false;

        $defaultParameters = [];

        while (true) {
            ++$index;
            $char = substr($values, $index, 1);

            if ($char === '(' ||
                $char === '['
            ) {
                ++$depth;
            }

            if ($char === ')' ||
                $char === ']'
            ) {
                --$depth;
            }

            if ($depth !== 0) {
                if ($char === '') {
                    throw new RuntimeException('Parameters terminated early.');
                }

                continue;
            }

            if ($char === ',' || $char === '') {
                if ($inName) {
                    $name = substr($values, $posStart, $index - $posStart);
                    $name = trim($name);
                } else {
                    $value = substr($values, $posStart, $index - $posStart);
                    $value = trim($value);
                }

                $defaultParameters[$name] = $value;

                if ($char === '') {
                    break;
                }

                $inName = false;
                $inValue = false;
                $name = null;
                $value = null;
            }

            if ($char === '$') {
                $inName = true;
                $posStart = $index + 1;
                continue;
            }

            if ($char === '=') {
                $name = substr($values, $posStart, $index - $posStart);
                $name = trim($name);

                $inName = false;
                $inValue = true;
                $posStart = $index + 1;
            }
        }

        if (!$hasNewlines) {
            return $defaultParameters;
        }

        // TODO: Indent function that takes into consideration the context
        // instead of assuming its already nicely indented
        foreach ($defaultParameters as $key => $value) {
            // Re-add trimmed base indent
            $value = $baseIndent . $value;

            $value = explode("\n", $value);

            // Remove base indent from each line
            foreach ($value as $key2 => $value2) {
                $value[$key2] = substr($value2, strlen($baseIndent));
            }

            $defaultParameters[$key] = implode("\n", $value);
        }

        return $defaultParameters;
    }

    /**
     * Handles inheriting PHPDoc comments when @inheritDoc is present.
     *
     * @param Reflector $reflector The PHPDoc comment's owner.
     * @param string|null $docComment A PHPDoc comment.
     *
     * @return string A PHPDoc comment.
     */
    protected function inheritDocComment(
        Reflector $reflector,
        ?string $docComment = null
    ): string
    {
        if (!$this->config->inheritDocComment) {
            return $docComment ?? '/** */';
        }

        $inheritDoc = new InheritDoc(
            $reflector,
            $docComment
        );

        return $inheritDoc->getDocComment();
    }

    /**
     * Generates markdown for labels when certain PHPDoc tags are present in a
     * PHPDoc comment.
     *
     * @param DocComment $docComment A PHPDoc comment.
     *
     * @return string|null A markdown string or null if no mark tags present.
     */
    protected function makeMarkLabels(DocComment $docComment): ?string
    {
        $marks = [];

        if ($this->config->showDeprecatedLabel &&
            $docComment->isDeprecated()
        ) {
            $marks[] = '_' . $this->config->labels['deprecated'] . '_';
        }

        if ($this->config->showInternalLabel &&
            $docComment->isInternal()
        ) {
            $marks[] = '_' . $this->config->labels['internal'] . '_';
        }

        if ($this->config->showGeneratedLabel &&
            $docComment->isGenerated()
        ) {
            $marks[] = '_' . $this->config->labels['generated'] . '_';
        }

        if (!$marks) {
            return null;
        }

        $marks = implode(', ', $marks);

        return $marks;
    }
}

// ✝
