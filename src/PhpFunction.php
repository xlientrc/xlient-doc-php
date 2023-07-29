<?php
namespace Xlient\Doc\Php;

use ReflectionFunction;

/**
 * A basic representation of a PHP function and its corresponding PHPDoc
 * comment.
 */
class PhpFunction
{
    /**
     * @var string|null A PHPDoc comment.
     */
    private ?string $docComment = null;

    /**
     * @var ReflectionFunction A reflector instance of this function.
     */
    private ReflectionFunction $reflector;

    /**
     * @param string $name A fully qualified name of a function.
     */
    public function __construct(
        private string $name,
    ) {
        $this->reflector = new ReflectionFunction($name);
    }

    /**
     * Gets the fully qualified name of this function.
     *
     * @return string The fully qualified name of this function.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the short name of this function.
     *
     * @return string The short name of this function.
     */
    public function getShortName(): string
    {
        return $this->reflector->getShortName();
    }

    /**
     * Gets a reflector instance of this function.
     *
     * @return ReflectionFunction A reflector instance of this function.
     */
    public function getReflection(): ReflectionFunction
    {
        return $this->reflector;
    }

    /**
     * Gets the PHPDoc comment of this function.
     *
     * @return string A PHPDoc comment.
     */
    public function getDocComment(): ?string
    {
        return $this->docComment;
    }

    /**
     * Sets the PHPDoc comment of this function.
     *
     * @param ?string $value A PHPDoc comment.
     *
     * @return static
     */
    public function setDocComment(?string $value): static
    {
        if ($value === '') {
            $value = null;
        }

        $this->docComment = $value;
        return $this;
    }
}

// ✝
