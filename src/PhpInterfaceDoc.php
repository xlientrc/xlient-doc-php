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
    protected function getFilename(?string $name = null): string
    {
        $name ??= $this->getName();

        return $this->getClassFilename(
            $name,
            $this->config->interfaceFilenamePrefix,
            $this->config->interfaceFilenameSuffix,
        );
    }
}

// ✝
