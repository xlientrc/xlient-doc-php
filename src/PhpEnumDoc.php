<?php
namespace Xlient\Doc\Php;

use ReflectionClass;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionEnum;
use Xlient\Doc\Php\AbstractPhpDoc;

use function Xlient\Doc\Php\indent as xlient_indent;
use function Xlient\Doc\Php\markdown_escape as xlient_markdown_escape;

/**
 * Generates documentation for PHP enumerations.
 *
 * @property ReflectionEnum $reflector An enum reflector.
 */
class PhpEnumDoc extends PhpClassDoc
{
    /**
     * @inheritDoc
     */
    protected function initializeReflector(): void
    {
        $this->reflector = new ReflectionEnum($this->getName());
    }

    /**
     * @inheritDoc
     */
    protected function makeName(): array
    {
        return [
            '# Enum ' . rtrim($this->getName(), '\\')
        ];
    }

    /**
     * @inheritDoc
     */
    protected function makeConstantSynopsis(): array
    {
        $content = [];

        $cases = $this->makeCaseSynopsis();
        $constants = parent::makeConstantSynopsis();

        if ($cases && $constants) {
            $content = [
                ...$cases,
                '',
                ...$constants,
            ];
        } else {
            $content = [
                ...$cases,
                ...$constants,
            ];
        }

        return $content;
    }

    /**
     * Generates markdown for a code snyopsis of all enumeration cases in this
     * documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCaseSynopsis(): array
    {
        $content = [];

        $cases = $this->getClassReflectionCases($this->reflector);

        if (!$cases) {
            return $content;
        }

        if ($this->config->sortByName) {
            usort($cases, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content[] = xlient_indent(
            '/* ' . $this->config->labels['cases'] . ' */',
            1,
            $this->config->indentLength
        );

        foreach ($cases as $value) {
            $name = $value->getName();
            if (strtoupper($name) === $name) {
                $name = strtolower($name);
            }
            $anchor = $this->getAnchor($name);

            $content[] = xlient_indent(
                $this->getClassCaseDefinition($value),
                1,
                $this->config->indentLength
            );
        }

        return $content;
    }

    /**
     * @inheritDoc
     */
    protected function makeConstants(): array
    {
        $content = [];

        $content = [
            ... $this->makeCases(),
            ...parent::makeConstants(),
        ];

        return $content;
    }

    /**
     * @inheritDoc
     */
    protected function makeConstantDetails(): array
    {
        $content = [];

        $content = [
            ... $this->makeCaseDetails(),
            ...parent::makeConstantDetails(),
        ];

        return $content;
    }

    /**
     * Generates markdown for a list of cases contained in this documentation
     * file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCases(): array
    {
        if (!$this->config->makeClassCases) {
            return [];
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['cases'];

        $cases = $this->getClassReflectionCases($this->reflector);

        if (!$cases) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($cases, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [
            ...$content,
            ...$this->makeCasesPartial($cases, 2)
        ];

        return $content;
    }

    /**
     * Generates markdown for a list of the specified cases.
     *
     * @param array<ReflectionEnumUnitCase> $cases An array of case reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCasesPartial(
        array $cases,
        int $headingDepth,
    ): array
    {
        if ($this->config->enableTables) {
            return $this->makeCasesPartialTable(
                $cases,
                $headingDepth,
            );
        }

        $content = [];

        $heading = str_repeat('#', $headingDepth);

        foreach ($cases as $value) {
            $data = $this->getCaseData($value);

            if ($data->url !== null) {
                $content[] = $heading . '# [' . $data->name . '()](' . $data->url . ')';
            } else {
                $content[] = $heading . '# ' . $data->name;
            }

            if ($data->marks !== null) {
                $content[] = $data->marks;
            }

            if ($data->description !== null) {
                $content[] = $data->description;
            }

            $definition = $this->getClassCaseDefinition($value);
            $content[] = '```php' . "\n" . $definition . "\n" . '```';
        }

        return $content;
    }

    /**
     * Generates markdown for a list of the specified cases in a table format.
     *
     * @param array<ReflectionEnumUnitCase> $cases An array of case reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCasesPartialTable(
        array $cases,
        int $headingDepth,
    ): array
    {
        $content = [];

        $heading = str_repeat('#', $headingDepth);

        if ($this->reflector->isBacked()) {
            $table[] = '| ' . $this->config->labels['name'] . ' | ' .
                $this->config->labels['value'] . ' |' .
                $this->config->labels['description'] . ' |';
            $table[] = '| :--- | :--- | :--- |';
        } else {
            $table[] = '| ' . $this->config->labels['name'] . ' | ' .
                $this->config->labels['description'] . ' |';
            $table[] = '| :--- | :--- |';
        }

        foreach ($cases as $value) {
            $data = $this->getCaseData($value);

            $marks = $data->marks;

            if ($marks !== null) {
                $marks = '<br>' . $marks;
            }

            $row = '| ';

            if ($data->url !== null) {
                $row .= '[' . $data->name . '](#' . $data->url. ')' . $marks . ' | ';
            } else {
                $row .= $data->name . $marks . ' | ';
            }

            if ($this->reflector->isBacked()) {
                $row .= $data->backingValue ?? '';
                $row .= ' | ';
            }

            $row .= $data->description ?? '';

            $row .= ' |';

            $table[] = $row;
        }

        $content[] = implode("\n", $table);

        return $content;
    }

    /**
     * Gets the markdown encoded name, backingValue, description, url, and
     * marks of the specified constant.
     *
     * @param ReflectionEnumUnitCase $case A enum case reflector.
     *
     * @return object{
     *     name: string,
     *     backingValue: string|int|null,
     *     description: string|null,
     *     url: string|null,
     *     marks: string|null,
     * } An object of class constant data.
     */
    protected function getCaseData(ReflectionEnumUnitCase $case): object
    {
        $name = xlient_markdown_escape($case->getName());

        if ($case instanceof ReflectionEnumBackedCase) {
            $backingValue = $case->getBackingValue();
        } else {
            $backingValue = null;
        }

        $docComment = $this->inheritDocComment($case);
        $docComment = new DocComment($docComment);

        $description = $this->makeDescription(
            $docComment->getSummary(),
            null
        );

        if ($this->config->makeClassCaseDetails) {
            $url = $this->getAnchor($case->getName());
            $url = xlient_markdown_escape($url);
        } else {
            $url = null;
        }

        $marks = $this->makeMarkLabels($docComment);

        return new class($name, $backingValue, $description, $url, $marks) {
            public function __construct(
                public readonly string $name,
                public readonly null|string|int $backingValue,
                public readonly ?string $description,
                public readonly ?string $url,
                public readonly ?string $marks,
            ) {}
        };
    }

    /**
     * Generates markdown for more detailed information about the cases in
     * this documentation file.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCaseDetails(): array
    {
        if (!$this->config->makeClassCaseDetails) {
            return [];
        }

        $cases = $this->getClassReflectionCases($this->reflector);

        if (!$cases) {
            return [];
        }

        if ($this->config->sortByName) {
            usort($cases, function($a, $b) {
                return strnatcmp($a->getName(), $b->getName());
            });
        }

        $content = [];

        $content[] = '## ' . $this->config->labels['case_details'];

        $content = [
            ...$content,
            ...$this->makeCaseDetailsPartial($cases, 2)
        ];

        return $content;
    }

    /**
     * Generates markdown for more detailed information about the specified
     * cases.
     *
     * @param array<ReflectionEnumUnitCase> $cases An array of case reflectors.
     * @param int $headingDepth The number of headings deep this markdown code
     *  will be contained in.
     *
     * @return array<string> An array of markdown lines.
     */
    protected function makeCaseDetailsPartial(
        array $cases,
        int $headingDepth,
    ): array
    {
        $content = [];

        foreach ($cases as $value) {
            $name = $value->getName();
            if (strtoupper($name) === $name) {
                $name = strtolower($name);
            }
            $anchor = $this->getAnchor($name);

            $content = [
                ...$content,
                ...$this->makeCase(
                    case: $value,
                    headingDepth: $headingDepth,
                    anchor: $anchor
                ),
            ];
        }

        return $content;
    }

    /**
     * Gets an array of case reflectors that exist in the specified
     * class reflector.
     *
     * @param ReflectionEnum $class A class reflector.
     *
     * @return array<ReflectionEnumUnitCase> An array of enum case reflectors.
     */
    protected function getClassReflectionCases(
        ReflectionEnum $class
    ): array
    {
        $cases = $class->getCases();

        $cases = $this->removeIgnoreableReflectors($cases);

        return $cases;
    }

    /**
     * @inheritDoc
     */
    protected function getFilename(?string $name = null): string
    {
        $name ??= $this->getName();

        return $this->getClassFilename(
            $name,
            $this->config->enumFilenamePrefix,
            $this->config->enumFilenameSuffix,
        );
    }
}

// ✝
