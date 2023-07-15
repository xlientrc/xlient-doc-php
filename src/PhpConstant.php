<?php
namespace Xlient\Doc\Php;

/**
 * A basic representation of a PHP constant and its corresponding PHPDoc
 * comment.
 */
class PhpConstant
{
    /**
     * @var string The constants value.
     */
    protected string $value = 'null';

    /**
     * @var bool Is the constant defined.
     */
    protected bool $defined = false;

    /**
     * @var string|null The name of the constant if defined.
     */
    protected ?string $definedName = null;

    /**
     * @var string|null A PHPDoc comment.
     */
    protected ?string $docComment = null;

    /**
     * @param string $name A fully qualified name of a constant.
     */
    public function __construct(
        protected string $name,
    ) {}

    /**
     * Gets the fully qualified name of this constant.
     *
     * @return string The fully qualified name of this constant.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the short name of this constant.
     *
     * @return string The short name of this constant.
     */
    public function getShortName(): string
    {
        $name = explode('\\', $this->getName());
        return $name[count($name) - 1];
    }

    /**
     * Gets the value of this constant.
     *
     * @return string The value of this constant.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Sets the value of this constant.
     *
     * @param string $value A value.
     *
     * @return static
     */
    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Gets whether or not this constant was defined.
     *
     * @return bool True of this constant was defined, false otherwise.
     */
    public function getDefined(): bool
    {
        return $this->defined;
    }

    /**
     * Sets whether or not this constant was defined.
     *
     * @param bool $value A value indicating whether or not this constant was
     *  defined.
     *
     * @return static
     */
    public function setDefined(bool $value): static
    {
        $this->defined = $value;
        return $this;
    }

    /**
     * Gets the defined name value of a defined constant.
     *
     * @return string|null An originally defined name or null if not a defined
     *  constant.
     */
    public function getDefinedName(): ?string
    {
        return $this->definedName;
    }

    /**
     * Sets the defined name value of a defiend constant.
     *
     * @param string|null $value The defined name value.
     *
     * @return static
     */
    public function setDefinedName(?string $value): static
    {
        $this->definedName = $value;
        return $this;
    }

    /**
     * Gets the PHPDoc comment of this constant.
     *
     * @return string A PHPDoc comment.
     */
    public function getDocComment(): ?string
    {
        return $this->docComment;
    }

    /**
     * Sets the PHPDoc comment of this constant.
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
