<?php
namespace Xlient\Doc\Php;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionClassConstant;
use ReflectionProperty;
use Reflector;

/**
 * Handles \@inheritDoc in PHPDoc comments.
 */
class InheritDoc
{
    /**
     * @param Reflector $reflector A reflector.
     * @param string|null $docComment A PHPDoc comment.
     */
    public function __construct(
        private Reflector $reflector,
        private ?string $docComment
    ) {}

    /**
     * Gets a PHPDoc comment with \@inheritDoc replaced accordingly.
     *
     * @return string A PHPDoc comment.
     */
    public function getDocComment(): string
    {
        if (!method_exists($this->reflector, 'getDocComment')) {
            return $this->docComment ?? '/** */';
        }

        $docComment = $this->docComment ?? $this->reflector->getDocComment();
        $docComment = $docComment ?: '/** */';

        if ($this->reflector instanceof ReflectionClass) {
            return $this->getClassDocComment($this->reflector, $docComment);
        }

        if ($this->reflector instanceof ReflectionClassConstant) {
            return $this->getClassConstantDocComment($this->reflector, $docComment);
        }

        if ($this->reflector instanceof ReflectionProperty) {
            return $this->getPropertyDocComment($this->reflector, $docComment);
        }

        if ($this->reflector instanceof ReflectionMethod) {
            return $this->getMethodDocComment($this->reflector, $docComment);
        }

        return $docComment;
    }

    /**
     * Gets a class PHPDoc comment with \@inheritDoc replaced accordingly.
     *
     * @param ReflectionClass $reflector A class reflector.
     * @param string $docComment A PHPDoc comment.
     *
     * @return string A PHPDoc comment.
     */
    protected function getClassDocComment(
        ReflectionClass $reflector,
        string $docComment
    ): string
    {
        if ($this->isInheritOnly($docComment)) {
            $parent = $this->getParentClass($reflector);
            if ($parent === null || $parent->getDocComment() === false) {
                return '/** */';
            }

            return $this->getClassDocComment(
                $parent,
                $parent->getDocComment()
            );
        }

        $docComment = str_replace('@inheritdoc', '@inheritDoc', $docComment);

        if (str_contains($docComment, '{@inheritDoc}')) {
            $parent = $this->getParentClass($reflector);

            $parentDocComment = '';

            if ($parent !== null && $parent->getDocComment() !== false) {
                $parentDocComment = $this->getClassDocComment(
                    $parent,
                    $parent->getDocComment()
                );

                $parentDocComment = new DocComment($parentDocComment);
                $parentDocComment = $parentDocComment->getDescription() ?? '';
            }

            $docComment = str_replace(
                '{@inheritDoc}',
                $parentDocComment,
                $docComment,
            );
        }

        // Regular inheritance
        $parent = $this->getParentClass($reflector);

        if ($parent !== null) {
            $parentDocComment = $this->getClassDocComment(
                $parent,
                $parent->getDocComment() ?: '/** */'
            );

            $docComment = $this->mergeDocComments(
                $parentDocComment,
                $docComment
            );
        }

        return $docComment;
    }

    /**
     * Gets the parent class reflector of the specified class reflector.
     *
     * @param ReflectionClass $reflector A class reflector.
     *
     * @return ReflectionClass|null A class reflector or null if no
     *  parents.
     */
    protected function getParentClass(
        ReflectionClass $reflector
    ): ?ReflectionClass
    {
        $parentClass = null;

        $parentReflection = $reflector;

        while (true) {
            $parentReflection = $parentReflection->getParentClass();
            if ($parentReflection === false) {
                break;
            }

            // Keep going up until a comment is reached
            if ($parentReflection->getDocComment() !== false) {
                $parentClass = $parentReflection;
                break;
            }
        }

        return $parentClass;
    }

    /**
     * Gets a class constant PHPDoc comment with \@inheritDoc replaced
     * accordingly.
     *
     * @param ReflectionClassConstant $reflector A class constant reflector.
     * @param string $docComment A PHPDoc comment.
     *
     * @return string A PHPDoc comment.
     */
    protected function getClassConstantDocComment(
        ReflectionClassConstant $reflector,
        string $docComment
    ): string
    {
        if ($this->isInheritOnly($docComment)) {
            $parent = $this->getParentClassConstant($reflector);

            if ($parent === null || $parent->getDocComment() === false) {
                return '/** */';
            }

            return $this->getClassConstantDocComment(
                $parent,
                $parent->getDocComment()
            );
        }

        $docComment = str_replace('@inheritdoc', '@inheritDoc', $docComment);

        if (str_contains($docComment, '{@inheritDoc}')) {
            $parent = $this->getParentClassConstant($reflector);

            $parentDocComment = '';

            if ($parent !== null && $parent->getDocComment() !== false) {
                $parentDocComment = $this->getClassConstantDocComment(
                    $parent,
                    $parent->getDocComment()
                );

                $parentDocComment = new DocComment($parentDocComment);
                $parentDocComment = $parentDocComment->getDescription() ?? '';
            }

            $docComment = str_replace(
                '{@inheritDoc}',
                $parentDocComment,
                $docComment,
            );
        }

        // Regular inheritance
        $parent = $this->getParentClassConstant($reflector);

        if ($parent !== null) {
            $parentDocComment = $this->getClassConstantDocComment(
                $parent,
                $parent->getDocComment() ?: '/** */'
            );

            $docComment = $this->mergeDocComments(
                $parentDocComment,
                $docComment
            );
        }

        return $docComment;
    }

    /**
     * Gets the parent class constant reflector of the specified class constant
     * reflector.
     *
     * @param ReflectionClassConstant $reflector A class constant reflector.
     *
     * @return ReflectionClassConstant|null A class constant reflector
     * or null if no parents.
     */
    protected function getParentClassConstant(
        ReflectionClassConstant $reflector
    ): ?ReflectionClassConstant
    {
        $parentClassConstant = null;

        $parentReflection = $reflector->getDeclaringClass();

        while (true) {
            $parentReflection = $parentReflection->getParentClass();
            if ($parentReflection === false) {
                break;
            }

            if (!$parentReflection->hasConstant($reflector->getName())) {
                continue;
            }

            $parent = $parentReflection->getReflectionConstant($reflector->getName());
            if ($parent !== false && $parent->getDocComment() !== false) {
                $parentClassConstant = $parent;
                break;
            }
        }

        if ($parentClassConstant !== null) {
            return $parentClassConstant;
        }

        $interfaces = $reflector->getDeclaringClass()->getInterfaces();
        foreach ($interfaces as $interface) {
            if (!$interface->hasConstant($reflector->getName())) {
                continue;
            }

            $parent = $interface->getReflectionConstant($reflector->getName());
            if ($parent !== false) {
                if ($parent->getDocComment() === false) {
                    return $this->getParentClassConstant($parent);
                }

                $parentClassConstant = $parent;
                break;
            }
        }

        return $parentClassConstant;
    }

    /**
     * Gets a property PHPDoc comment with \@inheritDoc replaced accordingly.
     *
     * @param ReflectionProperty $reflector A property reflector.
     * @param string $docComment A PHPDoc comment.
     *
     * @return string A PHPDoc comment.
     */
    protected function getPropertyDocComment(
        ReflectionProperty $reflector,
        string $docComment
    ): string
    {
        if ($this->isInheritOnly($docComment)) {
            $parent = $this->getParentProperty($reflector);

            if ($parent === null || $parent->getDocComment() === false) {
                return '/** */';
            }

            return $this->getPropertyDocComment(
                $parent,
                $parent->getDocComment()
            );
        }

        $docComment = str_replace('@inheritdoc', '@inheritDoc', $docComment);

        if (str_contains($docComment, '{@inheritDoc}')) {
            $parent = $this->getParentProperty($reflector);

            $parentDocComment = '';

            if ($parent !== null && $parent->getDocComment() !== false) {
                $parentDocComment = $this->getPropertyDocComment(
                    $parent,
                    $parent->getDocComment()
                );

                $parentDocComment = new DocComment($parentDocComment);
                $parentDocComment = $parentDocComment->getDescription() ?? '';
            }

            $docComment = str_replace(
                '{@inheritDoc}',
                $parentDocComment,
                $docComment,
            );
        }

        // Regular inheritance
        $parent = $this->getParentProperty($reflector);

        if ($parent !== null) {
            $parentDocComment = $this->getPropertyDocComment(
                $parent,
                $parent->getDocComment() ?: '/** */'
            );

            $docComment = $this->mergeDocComments(
                $parentDocComment,
                $docComment
            );
        }

        return $docComment;
    }

    /**
     * Gets the parent property reflector of the specified property reflector.
     *
     * @param ReflectionProperty $reflector A property reflector.
     *
     * @return ReflectionProperty|null A property reflector or null if
     *  no parents.
     */
    protected function getParentProperty(
        ReflectionProperty $reflector
    ): ?ReflectionProperty
    {
        $parentProperty = null;

        $parentReflection = $reflector->getDeclaringClass();

        while (true) {
            $parentReflection = $parentReflection->getParentClass();
            if ($parentReflection === false) {
                break;
            }

            if (!$parentReflection->hasProperty($reflector->getName())) {
                continue;
            }

            $parent = $parentReflection->getProperty($reflector->getName());
            if ($parent !== false && $parent->getDocComment() !== false) {
                $parentProperty = $parent;
                break;
            }
        }

        if ($parentProperty !== null) {
            return $parentProperty;
        }

        $interfaces = $reflector->getDeclaringClass()->getInterfaces();
        foreach ($interfaces as $interface) {
            if (!$interface->hasProperty($reflector->getName())) {
                continue;
            }

            $parent = $interface->getProperty($reflector->getName());
            if ($parent !== false) {
                if ($parent->getDocComment() === false) {
                    return $this->getParentProperty($parent);
                }

                $parentProperty = $parent;
                break;
            }
        }

        return $parentProperty;
    }

    /**
     * Gets a method PHPDoc comment with \@inheritDoc replaced accordingly.
     *
     * @param ReflectionMethod $reflector A method reflector.
     * @param string $docComment A PHPDoc comment.
     *
     * @return string A PHPDoc comment.
     */
    protected function getMethodDocComment(
        ReflectionMethod $reflector,
        string $docComment
    ): string
    {
        if ($this->isInheritOnly($docComment)) {
            $parent = $this->getParentMethod($reflector);

            if ($parent === null || $parent->getDocComment() === false) {
                return '/** */';
            }

            return $this->getMethodDocComment(
                $parent,
                $parent->getDocComment()
            );
        }

        $docComment = str_replace('@inheritdoc', '@inheritDoc', $docComment);

        if (str_contains($docComment, '{@inheritDoc}')) {
            $parent = $this->getParentMethod($reflector);

            $parentDocComment = '';

            if ($parent !== null && $parent->getDocComment() !== false) {
                $parentDocComment = $this->getMethodDocComment(
                    $parent,
                    $parent->getDocComment()
                );

                $parentDocComment = new DocComment($parentDocComment);
                $parentDocComment = $parentDocComment->getDescription() ?? '';
            }

            $docComment = str_replace(
                '{@inheritDoc}',
                $parentDocComment,
                $docComment,
            );
        }

        // Regular inheritance
        $parent = $this->getParentMethod($reflector);

        if ($parent !== null) {
            $parentDocComment = $this->getMethodDocComment(
                $parent,
                $parent->getDocComment() ?: '/** */'
            );

            $docComment = $this->mergeDocComments(
                $parentDocComment,
                $docComment
            );
        }

        return $docComment;
    }

    /**
     * Gets the parent method reflector of the specified method reflector.
     *
     * @param ReflectionMethod $reflector A method reflector.
     *
     * @return ReflectionMethod|null A method reflector or null if
     *  no parents.
     */
    protected function getParentMethod(
        ReflectionMethod $reflector
    ): ?ReflectionMethod
    {
        $parentMethod = null;

        $parentReflection = $reflector->getDeclaringClass();

        while (true) {
            $parentReflection = $parentReflection->getParentClass();
            if ($parentReflection === false) {
                break;
            }

            if (!$parentReflection->hasMethod($reflector->getName())) {
                continue;
            }

            $parent = $parentReflection->getMethod($reflector->getName());
            if ($parent !== false && $parent->getDocComment() !== false) {
                $parentMethod = $parent;
                break;
            }
        }

        if ($parentMethod !== null) {
            return $parentMethod;
        }

        $interfaces = $reflector->getDeclaringClass()->getInterfaces();
        foreach ($interfaces as $interface) {
            if (!$interface->hasMethod($reflector->getName())) {
                continue;
            }

            $parent = $interface->getMethod($reflector->getName());
            if ($parent !== false) {
                if ($parent->getDocComment() === false) {
                    return $this->getParentMethod($parent);
                }

                $parentMethod = $parent;
                break;
            }
        }

        return $parentMethod;
    }

    /**
     * Gets whether or not the specified PHPDoc comment is made up of a single
     * \@inheritDoc tag.
     *
     * @param string $docComment A PHPDoc comment.
     *
     * @return bool True if the PHPDoc comment is made up of a single
     * \@inheritDoc tag, false otherwise.
     */
    protected function isInheritOnly(string $docComment): bool
    {
        // Remove surrounding /** */
        $docComment = trim(substr($docComment, 3, -2));

        // Remove empty lines and additional comment characters
        $docComment = ltrim($docComment, "* \n");

        if ($docComment === '@inheritdoc' ||
            $docComment === '@inheritDoc' ||
            $docComment === '{@inheritdoc}' ||
            $docComment === '{@inheritDoc}' ||
            $docComment === ''
        ) {
            return true;
        }

        return false;
    }

    /**
     * Merges two PHPDoc comments together.
     *
     * Any information in the parent comment not overriden in the child will be
     * inherited.
     *
     * @param string $parentDocComment A PHPDoc comment of the parent.
     * @param string $docComment A PHPDoc comment of the child.
     *
     * @return string A merged PHPDoc comment.
     */
    private function mergeDocComments(
        string $parentDocComment,
        string $docComment
    ): string
    {
        $lines = [];
        $lines[] = '/**';

        $parentDocComment = new DocComment($parentDocComment);
        $docComment = new DocComment($docComment);

        $summary = $docComment->getSummary() ??
            $parentDocComment->getSummary();

        if ($summary !== null) {
            $lines[] = '* ' . $summary;
            $lines[] = '* ';
        }

        $description = $docComment->getDescription() ??
            $parentDocComment->getDescription();

        if ($description !== null) {
            $description = explode("\n", trim($description));
            $description = implode("\n" . '* ', $description);
            $lines[] = '* ' . $description;
            $lines[] = '* ';
        }

        // @params
        $tags = $docComment->getParamTagValues();
        if (!$tags) {
            $tags = $parentDocComment->getParamTagValues();
        }

        if ($tags) {
            foreach ($tags as $tag) {
                $param = '* @param ' . $tag->type . ' ' . $tag->parameterName;
                $description = explode("\n", $tag->description);
                $description = implode("\n" . '*   ', $description);

                $lines[] = trim($param . ' ' . $description);
            }

            $lines[] = '* ';
        }

        // @return
        $tags = $docComment->getReturnTagValues();
        if (!$tags) {
            $tags = $parentDocComment->getReturnTagValues();
        }

        if ($tags) {
            $tag = $tags[0];

            $return = '* @return ' . $tag->type;
            $description = explode("\n", $tag->description);
            $description = implode("\n" . '*   ', $description);

            $lines[] = ($return . ' ' . $description);
            $lines[] = '* ';
        }

        // @throws
        $tags = $docComment->getThrowsTagValues();
        if (!$tags) {
            $tags = $parentDocComment->getThrowsTagValues();
        }

        if ($tags) {
            foreach ($tags as $tag) {
                $param = '* @throws ' . $tag->type;
                $description = explode("\n", $tag->description);
                $description = implode("\n" . '*   ', $description);

                $lines[] = trim($param . ' ' . $description);
            }
            $lines[] = '* ';
        }

        if (count($lines) > 1) {
            array_pop($lines);
        }

        $lines[] = '*/';

        return implode("\n", $lines);
    }
}

// ✝
