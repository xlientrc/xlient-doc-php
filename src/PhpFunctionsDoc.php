<?php
namespace Xlient\Doc\Php;

use Xlient\Doc\Php\DocComment;
use Xlient\Doc\Php\AbstractPhpDoc;
use Xlient\Doc\Php\PhpFunction;

use function Xlient\Doc\Php\make_dir as xlient_make_dir;
use function Xlient\Doc\Php\markdown_escape as xlient_markdown_escape;
use function Xlient\Doc\Php\markdown_unescape as xlient_markdown_unescape;

use const DIRECTORY_SEPARATOR as DS;

/**
 * Generates documentation for PHP functions.
 */
class PhpFunctionsDoc extends AbstractPhpDoc
{
    /**
     * @var array<PhpFunction> An array of functions to put in this
     *  documentation.
     */
    protected array $functions = [];

    /**
     * @inheritDoc
     */
    public function make(): array
    {
        if ($this->config->sortByName) {
            usort($this->functions, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [
            ...$this->makeFunctionsDescription(),
            ...$this->makeSynopsis(),
            ...$this->makeFunctions(),
            ...$this->makeFunctionDetails(),
        ];

        if (!$content) {
            return [];
        }

        $content = [
            ...$this->makeName(),
            ...$content,
        ];

        $content = implode("\n\n", $content);

        $file = $this->getFile($this->getName());

        file_put_contents($file, $content);

        return [
            $file,
            ...$this->makeFunctionFiles(),
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
        $name = $this->getName();

        $name = substr($name, 0, -strlen('\\' . $this->config->functionsFilename));

        if ($name !== '') {
            $name = ' ' . $name;
        }

        $name = xlient_markdown_escape($name);

        return [
            '# ' . $this->config->labels['functions'] . $name
        ];
    }

    /**
     * Generates markdown for the description of this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeFunctionsDescription(): array
    {
        if (!$this->config->makeFunctionsDescription) {
            return [];
        }

        $docComment = $this->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $docComment = new DocComment($docComment);

        $description = $this->makeDescription(
            $docComment->getSummary(),
            $docComment->getDescription()
        );

        if ($description === null) {
            return [];
        }

        return [$description];
    }

    /**
     * Generates markdown for a code snyopsis of this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeSynopsis(): array
    {
        if (!$this->config->makeFunctionSynopsis) {
            return [];
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['function_synopsis'];

        $content[] = '```php';

        foreach ($this->functions as $value) {
            $content[] = $this->getFunctionDefinition(
                $value->getReflection()
            );
        }

        $content[] = '```';

        return [implode("\n", $content)];
    }

    /**
     * Generates meta JSON files to allow for linking in code sysnopsis after
     * syntax highlighting.
     *
     * @return array<string> An array of meta JSON files.
     */
    public function makeSynopsisMeta(): array
    {
        if (!$this->config->makeFunctionSynopsis ||
            !$this->config->makeSynopsisMeta
        ) {
            return [];
        }

        // If no function files or function details than there is no reason to
        // make synopsis meta
        if (!$this->config->functionFiles &&
            !$this->config->makeFunctionDetails
        ) {
            return [];
        }

        $functions = $this->functions;

        // Reverse sort to ensure longer names first
        usort($functions, function($a, $b) {
            return strcasecmp($b->getShortName(), $a->getShortName());
        });

        $urls = [];

        foreach ($functions as $value) {
            $value = $value->getReflection();

            $name = $value->getShortName();

            if ($this->config->functionFiles) {
                $url = $this->getFunctionUrl($value->getShortName());
                if ($url === null) {
                    continue;
                }
            } else {
                $url = '#' . $this->getAnchor($value->getShortName());
            }

            $urls[] = [$name, $url];
        }

        if (!$urls) {
            return [];
        }

        $content = [
            'urls' => $urls
        ];

        $content = json_encode($content);

        $file = $this->getFile($this->getName());

        $file = substr($file, 0, -3) . '.json';

        file_put_contents($file, $content);

        return [$file];
    }

    /**
     * Generates markdown for a list of functions contained in this
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeFunctions(): array
    {
        if ($this->config->enableTables) {
            return $this->makeFunctionsTable();
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['functions'];

        foreach ($this->functions as $value) {
            $data = $this->getFunctionData($value);

            if ($data->url !== null) {
                $content[] = '#### [' . $data->name . '()](' . $data->url . ')';
            } else {
                $content[] = '#### ' . $data->name . '()';
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
     * Generates markdown for a list of functions contained in this
     * documentation file in a table format.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeFunctionsTable(): array
    {
        $content = [];

        $content[] = '## ' . $this->config->labels['functions'];

        $table = [];
        $table[] = '| ' . $this->config->labels['name'] . ' | ' . $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- |';

        foreach ($this->functions as $value) {
            $data = $this->getFunctionData($value);

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
     * specified function.
     *
     * @param PhpFunction $function A Xlient php function object.
     *
     * @return object{
     *     name: string,
     *     description: string|null,
     *     url: string|null,
     *     marks: string|null,
     * } An object of function data.
     */
    protected function getFunctionData(PhpFunction $function): object
    {
        $reflector = $function->getReflection();

        $name = xlient_markdown_escape($reflector->getShortName());

        $docComment = new DocComment(
            $function->getDocComment() ?: '/** */'
        );

        $description = $this->makeDescription(
            $docComment->getSummary(),
            null
        );

        if ($this->config->functionFiles) {
            $url = $this->getFunctionUrl($reflector->getShortName());
            if ($url !== null) {
                $url = xlient_markdown_escape($url);
            }
        } elseif ($this->config->makeFunctionDetails) {
            $url = '#' . $this->getAnchor($reflector->getShortName());
            $url = xlient_markdown_escape($url);
        } else {
            $url = null;
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
     * Generates markdown for more detailed information about the functions in
     * this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeFunctionDetails(): array
    {
        if (!$this->config->makeFunctionDetails) {
            return [];
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['function_details'];

        foreach ($this->functions as $value) {
            $value = $value->getReflection();

            $anchor = $this->getAnchor($value->getShortName());

            $content = [
                ...$content,
                ...$this->makeFunction(
                    function: $value,
                    headingDepth: 2,
                    anchor: $anchor,
                )
            ];
        }

        return $content;
    }

    /**
     * Generates separate files for each function.
     *
     * @return array<string> An array of files.
     */
    public function makeFunctionFiles(): array
    {
        if (!$this->config->functionFiles) {
            return [];
        }

        $files = [];

        $namePrefix = xlient_markdown_unescape(
            $this->config->labels['function']
        );

        foreach ($this->functions as $value) {
            $value = $value->getReflection();

            $content = $this->makeFunction(
                function: $value,
                name: $namePrefix . ' \\' . $value->getName(),
            );

            $content = implode("\n\n", $content);

            $file = $this->getFunctionFile($value->getShortName());

            file_put_contents($file, $content);

            $files[] = $file;
        }

        return $files;
    }

    /**
     * Adds a function to this documentation file.
     *
     * @param PhpFunction $function A function to add.
     *
     * @return static
     */
    public function add(PhpFunction $function): static
    {
        if ($function->getDocComment() !== null &&
            (
                !$this->config->showDeprecated ||
                !$this->config->showInternal ||
                !$this->config->showGenerated
            )
        ) {
            $docComment = new DocComment($function->getDocComment());

            if (!$this->config->showDeprecated &&
                $docComment->isDeprecated()
            ) {
                return $this;
            }

            if (!$this->config->showInternal &&
                $docComment->isInternal()
            ) {
                return $this;
            }

            if (!$this->config->showGenerated &&
                $docComment->isGenerated()
            ) {
                return $this;
            }
        }

        $this->functions[] = $function;

        return $this;
    }

    /**
     * Gets a filename for the specified function short name.
     *
     * @param string $function A short name of a function.
     *
     * @return string A file.
     */
    protected function getFunctionFile(string $function): string
    {
        if ($this->config->functionDir) {
            $dir = $this->destDir . DS . $this->getDirPath($this->getName()) .
                DS . $this->config->functionsFilename;
        } else {
            $dir = $this->destDir . DS . $this->getDirPath($this->getName());
        }

        if (!file_exists($dir)) {
            xlient_make_dir($dir);
        }

        return $dir . DS . $this->getFilename(
            $function,
            $this->config->functionFilenamePrefix,
            $this->config->functionFilenameSuffix,
        );
    }

    /**
     * Gets a URL for the specified function short name.
     *
     * @param string $function A short name of a function.
     *
     * @return string|null A URL.
     */
    protected function getFunctionUrl(string $function): ?string
    {
        $name = $this->getName();

        $matchingNamespace = null;
        foreach ($this->config->baseNamespaces as $namespace) {
            if (str_starts_with($name, $namespace)) {
                $matchingNamespace = rtrim($namespace, '\\');
                break;
            }
        }

        if ($matchingNamespace === null) {
            return null;
        }

        $baseUrl = $this->config->baseUrls[$matchingNamespace] ?? null;
        if ($baseUrl === null) {
            return null;
        }

        if ($this->config->functionDir) {
            $name .= '\\' . $this->config->functionsFilename;
        }

        $url = $baseUrl . $this->getUrlPath($name);

        return $url . '/' . $this->getFilename(
            $function,
            $this->config->functionFilenamePrefix,
            $this->config->functionFilenameSuffix,
        );
    }
}

// ✝
