<?php
namespace Xlient\Doc\Php;

use Xlient\Doc\Php\PhpClassDoc;

/**
 * Generates documentation for PHP traits.
 */
class PhpTraitDoc extends PhpClassDoc
{
    /**
     * @inheritDoc
     */
    protected function makeName(): array
    {
        return [
            '# Trait ' . rtrim($this->getName(), '\\')
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getFilename(?string $name = null): string
    {
        $name ??= $this->getName();

        return $this->getClassFilename(
            $name,
            $this->config->traitFilenamePrefix,
            $this->config->traitFilenameSuffix,
        );
    }
}

// ✝
