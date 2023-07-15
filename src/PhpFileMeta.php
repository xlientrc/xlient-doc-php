<?php
namespace Xlient\Doc\Php;

/**
 * Stores meta information about a PHP file.
 */
class PhpFileMeta
{
    /**
     * @var array<string, string> A key value pair of hashes and names.
     */
    protected array $names = [];

    /**
     * @var array<string, string> An array of class use statements.
     */
    protected array $classUses = [];

    /**
     * @var array<string, string> An array of function use statements.
     */
    protected array $functionUses = [];

    /**
     * @var array<string, string> An array of constant use statements.
     */
    protected array $constantUses = [];

    /**
     * @param Configuration $config The configuration to use to generate the
     *  documentation.
     */
    public function __construct(
        protected Configuration $config
    ) {}

    /**
     * Adds a name used int he PHP file.
     *
     * @param string $name A name.
     *
     * @return string A hash of the name.
     */
    public function addName(string $name): string
    {
        $hash = md5($name);

        $this->names[$hash] = $name;

        return $hash;
    }

    /**
     * Adds a class use statement.
     *
     * @param string $name A name.
     * @param string|null $as An alias for the name.
     *
     * @return static
     */
    public function addClassUse(string $name, ?string $as = null): static
    {
        if ($as === null) {
            $as = explode('\\', $name);
            $as = array_pop($as);
        }

        $this->classUses[$name] = $as;

        return $this;
    }

    /**
     * Adds a function use statement.
     *
     * @param string $name A name.
     * @param string|null $as An alias for the name.
     *
     * @return static
     */
    public function addFunctionUse(string $name, ?string $as = null): static
    {
        if ($as === null) {
            $as = explode('\\', $name);
            $as = array_pop($as);
        }

        $this->functionUses[$name] = $as;

        return $this;
    }

    /**
     * Adds a constant use statement.
     *
     * @param string $name A name.
     * @param string|null $as An alias for the name.
     *
     * @return static
     */
    public function addConstantUse(string $name, ?string $as = null): static
    {
        if ($as === null) {
            $as = explode('\\', $name);
            $as = array_pop($as);
        }

        $this->constantUses[$name] = $as;

        return $this;
    }

    /**
     * Replaces hashed names with their corresponding fully qualified name or
     * it's original value if not found found.
     *
     * @param string $value The value to replace.
     *
     * @return string The value with all hashed names replaced.
     */
    public function replaceNames(string $value): string
    {
        foreach ($this->names as $hash => $name) {
            $search = array_search($name, $this->classUses);

            if ($search !== false) {
                $value = str_replace($hash, $search, $value);
                continue;
            }

            $search = array_search($name, $this->functionUses);

            if ($search !== false) {
                $value = str_replace($hash, $search, $value);
                continue;
            }

            $search = array_search($name, $this->constantUses);

            if ($search !== false) {
                $value = str_replace($hash, $search, $value);
                continue;
            }

            $value = str_replace($hash, $name, $value);
        }

        return $value;
    }
}

// ✝
