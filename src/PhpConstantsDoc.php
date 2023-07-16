<?php
namespace Xlient\Doc\Php;

use Xlient\Doc\Php\DocComment;
use Xlient\Doc\Php\AbstractPhpDoc;
use Xlient\Doc\Php\PhpConstant;

use function Xlient\Doc\Php\markdown_escape as xlient_markdown_escape;

/**
 * Generates documentation for PHP constants.
 */
class PhpConstantsDoc extends AbstractPhpDoc
{
    /**
     * @var array<PhpConstant> An array of constants to put in this
     *  documentation.
     */
    protected array $constants = [];

    /**
     * @inheritDoc
     */
    public function make(): array
    {
        if ($this->config->sortByName) {
            usort($this->constants, function($a, $b) {
                return strnatcmp($a->getShortName(), $b->getShortName());
            });
        }

        $content = [
            ...$this->makeConstantsDescription(),
            ...$this->makeSynopsis(),
            ...$this->makeConstants(),
            ...$this->makeConstantDetails(),
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

        $name = substr($name, 0, -strlen('\\' . $this->config->constantsFilename));

        if ($name !== '') {
            $name = ' ' . $name;
        }

        return [
            '# ' . $this->config->labels['constants'] . $name
        ];
    }

    /**
     * Generates markdown for the description of this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeConstantsDescription(): array
    {
        if (!$this->config->makeConstantsDescription) {
            return [];
        }

        $docComment = $this->getDocComment();
        if ($docComment === null) {
            return [];
        }

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
     * Generates markdown for a code snyopsis of this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeSynopsis(): array
    {
        if (!$this->config->makeConstantSynopsis) {
            return [];
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['constant_synopsis'];

        $content[] = '```php';

        foreach ($this->constants as $value) {
            $content[] = $this->getConstantDefinition($value);
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
        if (!$this->config->makeConstantSynopsis ||
            !$this->config->makeSynopsisMeta
        ) {
            return [];
        }

        // If no constant details than there is no reason to
        // make synopsis meta
        if (!$this->config->makeConstantDetails) {
            return [];
        }

        $urls = [];

        foreach ($this->constants as $value) {
            $url = '#' . $this->getAnchor($value->getShortName());

            $name = $value->getName();
            $shortName = $value->getShortName();

            // In order of best match
            if ($value->getDefined()) {
                $definedName = $value->getDefinedName();
                $urls[] = [$definedName, $url];
            }

            $urls[] = [$name, $url];
            $urls[] = [$shortName, $url];
        }

        if (!$urls) {
            return [];
        }

        $content = [
            'urls' => $urls
        ];

        $content = json_encode($content);

        $file = substr($this->getFile(), 0, -3) . '.json';

        file_put_contents($file, $content);

        return [$file];
    }

    /**
     * Generates markdown for a list of constants contained in this
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeConstants(): array
    {
        if ($this->config->enableTables) {
            return $this->makeConstantsTable();
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['constants'];

        foreach ($this->constants as $value) {
            $data = $this->getConstantData($value);

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
     * Generates markdown for a list of constants contained in this
     * documentation file in a table format.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeConstantsTable(): array
    {
        $content = [];

        $content[] = '## ' . $this->config->labels['constants'];

        $table = [];
        $table[] = '| ' . $this->config->labels['name'] . ' | ' . $this->config->labels['description'] . ' |';
        $table[] = '| :--- | :--- |';

        foreach ($this->constants as $value) {
            $data = $this->getConstantData($value);

            $marks = $data->marks;

            if ($marks !== null) {
                $marks = '<br>' . $marks;
            }

            $row = '| ';
            if ($data->url !== null) {
                $row .= '[' . $data->name . '](' . $data->url . ')' . $marks . ' | ';
            } else {
                $row .= $data->name . $marks . ' | ';
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
     * @param PhpConstant $constant A Xlient php constant object.
     *
     * @return object{
     *     name: string,
     *     description: string|null,
     *     url: string|null,
     *     marks: string|null,
     * } An object of constant data.
     */
    protected function getConstantData(PhpConstant $constant): object
    {
        $name = xlient_markdown_escape($constant->getShortName());

        $docComment = new DocComment(
            $constant->getDocComment() ?: '/** */'
        );

        $description = $this->makeDescription(
            $docComment->getSummary(),
            null
        );

        if ($this->config->makeConstantDetails) {
            $url = '#' . $this->getAnchor($constant->getShortName());
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
     * Generates markdown for more detailed information about the constants in
     * this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    public function makeConstantDetails(): array
    {
        if (!$this->config->makeConstantDetails) {
            return [];
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['constant_details'];

        foreach ($this->constants as $value) {
            $anchor = $this->getAnchor($value->getShortName());
            $content[] = '<a id="' . $anchor . '"></a>';

            $name = xlient_markdown_escape($value->getShortName());

            $content[] = '### ' . $name;

            $docComment = new DocComment(
                $value->getDocComment() ?: '/** */'
            );

            $marks = $this->makeMarkLabels($docComment);
            if ($marks !== null) {
                $content[] = $marks;
            }

            $description = $this->makeDescription(
                $docComment->getSummary(),
                $docComment->getDescription(),
            );

            if ($description !== null) {
                $content[] = $description;
            }

            $definition = $this->getConstantDefinition($value);
            $content[] = '```php' . "\n" . $definition . "\n" . '```';

        }

        return $content;
    }

    /**
     * Gets a PHP code definition of the specified constant.
     *
     * @param PhpConstant $constant A constant.
     *
     * @return string A PHP code definition.
     */
    protected function getConstantDefinition(PhpConstant $constant): string
    {
        $content = [];

        if ($constant->getDefined()) {
            $content[] = 'define(';

            $content[] = $constant->getDefinedName();

            $content[] = ', ';

            $content[] = $this->meta->replaceNames($constant->getValue());

            $content[] = ');';
        } else {
            $content[] = 'const ';

            $content[] = $constant->getShortName();

            $content[] = ' = ';

            $content[] = $this->meta->replaceNames($constant->getValue());

            $content[] = ';';
        }

        return implode('', $content);
    }

    /**
     * Adds a constant to this documentation file.
     *
     * @param PhpConstant $constant A constant to add.
     *
     * @return static
     */
    public function add(PhpConstant $constant): static
    {
        if ($constant->getDocComment() !== null &&
            (
                !$this->config->showDeprecated ||
                !$this->config->showInternal ||
                !$this->config->showGenerated
            )
        ) {
            $docComment = new DocComment($constant->getDocComment());

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

        $this->constants[] = $constant;

        return $this;
    }
}

// ✝
