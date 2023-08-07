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
    protected function getClassFile(string $name): string
    {
        return $this->getFile(
            $name,
            $this->config->traitFilenamePrefix,
            $this->config->traitFilenameSuffix,
        );
    }

    /**
     * @inheritDoc
     */
    protected function getClassUrl(string $name): ?string
    {
        return $this->getUrl(
            $name,
            $this->config->traitFilenamePrefix,
            $this->config->traitFilenameSuffix,
        );
    }
}

// ✝
