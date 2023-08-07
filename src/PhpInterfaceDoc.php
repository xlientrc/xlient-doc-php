<?php
namespace Xlient\Doc\Php;

use Xlient\Doc\Php\PhpClassDoc;

/**
 * Generates documentation for PHP interfaces.
 */
class PhpInterfaceDoc extends PhpClassDoc
{
    /**
     * @inheritDoc
     */
    protected function makeName(): array
    {
        return [
            '# Interface ' . $this->getName()
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getClassFile(string $name): string
    {
        return $this->getFile(
            $name,
            $this->config->interfaceFilenamePrefix,
            $this->config->interfaceFilenameSuffix,
        );
    }

    /**
     * @inheritDoc
     */
    protected function getClassUrl(string $name): ?string
    {
        return $this->getUrl(
            $name,
            $this->config->interfaceFilenamePrefix,
            $this->config->interfaceFilenameSuffix,
        );
    }
}

// ✝
