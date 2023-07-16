<?php
namespace Xlient\Doc\Php;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

/**
 * Handles parsing a PHPDoc comment.
 */
class DocComment
{
    /**
     * A PHPStan PHPDoc node.
     */
    private PhpDocNode $node;

    /**
     * @param string $docComment A PHPDoc comment.
     */
    public function __construct(
        private string $docComment
    ) {
        $constExprParser = new ConstExprParser();

        $phpDocParser = new PhpDocParser(
            new TypeParser($constExprParser),
            $constExprParser
        );

        $lexer = new Lexer();
        $tokens = $lexer->tokenize($docComment);
        $tokens = new TokenIterator($tokens);

        $this->node = $phpDocParser->parse($tokens);
    }

    /**
     * Gets the original PHPDoc comment string.
     *
     * @return string A PHPDoc comment.
     */
    public function getDocComment(): string
    {
        return $this->docComment;
    }

    /**
     * Gets the summary text of the PHPDoc comment.
     *
     * @return string|null The summary text or null if not present.
     */
    public function getSummary(): ?string
    {
        $nodes = $this->getText();

        foreach ($nodes as $node) {
            $text = trim(strval($node));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * Gets the description text of the PHPDoc comment.
     *
     * @return string|null The description text or null if not present.
     */
    public function getDescription(): ?string
    {
        $nodes = $this->getText();

        $description = [];

        $summaryPassed = false;

        foreach ($nodes as $node) {
            $text = trim(strval($node));

            // Skip over empty nodes
            if ($text === '' && !$description) {
                continue;
            }

            if (!$summaryPassed) {
                $summaryPassed = true;
                continue;
            }

            $description[] = $text;
        }

        if ($description) {
            return implode("\n", $description);
        }

        return null;
    }

    /**
     * Gets all text non tagged text from the PHPDoc comment.
     *
     * @return array<PhpDocTextNode> An array of text from the PHPDoc comment.
     */
    private function getText(): array
    {
        return array_filter(
            $this->node->children,
            static function (PhpDocChildNode $child): bool {
			    return $child instanceof PhpDocTextNode;
            }
        );
    }

    /**
     * Gets all \@param tag values from the PHPDoc comment.
     *
     * @return array<ParamTagValueNode> An array of \@param tag values.
     */
    public function getParamTagValues(): array
    {
        return $this->node->getParamTagValues();
    }

    /**
     * Gets all \@return tag values from the PHPDoc comment.
     *
     * @return array<ReturnTagValueNode> An array of \@return tag values.
     */
    public function getReturnTagValues(): array
    {
        return $this->node->getReturnTagValues();
    }

    /**
     * Gets all \@throws tag values from the PHPDoc comment.
     *
     * @return array<ThrowsTagValueNode> An array of \@throws tag values.
     */
    public function getThrowsTagValues(): array
    {
        return $this->node->getThrowsTagValues();
    }

    /**
     * Gets all \@var tag values from the PHPDoc comment.
     *
     * @return array<VarTagValueNode> An array of \@var tag values.
     */
    public function getVarTagValues(): array
    {
        return $this->node->getVarTagValues();
    }

    /**
     * Gets whether or not an \@deprecated tag value is present in the PHPDoc
     * comment.
     *
     * @return bool True if present, false otherwise.
     */
    public function isDeprecated(): bool
    {
        return !!$this->node->getTagsByName('@deprecated');
    }

    /**
     * Gets whether or not an \@internal tag value is present in the PHPDoc
     * comment.
     *
     * @return bool True if present, false otherwise.
     */
    public function isInternal(): bool
    {
        return !!$this->node->getTagsByName('@internal');
    }

    /**
     * Gets whether or not an \@generated tag value is present in the PHPDoc
     * comment.
     *
     * @return bool True if present, false otherwise.
     */
    public function isGenerated(): bool
    {
        return !!$this->node->getTagsByName('@generated');
    }
}

// ✝
