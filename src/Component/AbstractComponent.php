<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Component;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Component\Argument\ArgumentCollection;
use TYPO3Fluid\Fluid\Component\Error\ChildNotFoundException;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\ViewHelpers\ArgumentViewHelper;

/**
 * Base Component Class
 *
 * Contains standard implementations for some of the more
 * universal methods a Component supports, e.g. handling
 * of child components and resolving of named children.
 */
abstract class AbstractComponent implements ComponentInterface
{
    /**
     * Unnamed children indexed by numeric position in array
     *
     * @var ComponentInterface[]
     */
    protected $children = [];

    /**
     * @var string|null
     */
    protected $name;

    /**
     * Specifies whether the escaping interceptors should be disabled or enabled for the result of renderChildren() calls within this ViewHelper
     * @see isChildrenEscapingEnabled()
     *
     * Note: If this is NULL the value of $this->escapingInterceptorEnabled is considered for backwards compatibility
     *
     * @var boolean|null
     */
    protected $escapeChildren = null;

    /**
     * Specifies whether the escaping interceptors should be disabled or enabled for the render-result of this ViewHelper
     * @see isOutputEscapingEnabled()
     *
     * @var boolean|null
     */
    protected $escapeOutput = null;

    /**
     * @var ArgumentCollection|null
     */
    protected $arguments = null;

    private $_lastAddedWasTextNode = false;

    public function onOpen(RenderingContextInterface $renderingContext): ComponentInterface
    {
        return $this;
    }

    public function onClose(RenderingContextInterface $renderingContext): ComponentInterface
    {
        return $this;
    }

    public function getComponentName(): ?string
    {
        return $this->name;
    }

    public function setArguments(ArgumentCollection $arguments): ComponentInterface
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getArguments(): ArgumentCollection
    {
        return $this->arguments ?? ($this->arguments = new ArgumentCollection());
    }

    public function addChild(ComponentInterface $component): ComponentInterface
    {
        if ($component instanceof RootNode) {
            // Assimilate child nodes instead of allowing a root node inside a root node.
            foreach ($component->getChildren() as $node) {
                $this->addChild($node);
            }
        } elseif ($component instanceof TextNode && $this->_lastAddedWasTextNode) {
            /** @var TextNode|false $lastNode */
            $lastNode = end($this->children);
            if ($lastNode) {
                $lastNode->appendText($component->getText());
            }
        } elseif ($component instanceof ArgumentViewHelper) {
            $this->getArguments()[$component->getArguments()['name']] = $component;
        } else {
            $this->children[] = $component;
            $this->_lastAddedWasTextNode = $component instanceof TextNode;
        }
        return $this;
    }

    public function getNamedChild(string $name): ComponentInterface
    {
        $parts = explode('.', $name, 2);
        foreach (array_reverse($this->children) as $child) {
            if ($child->getComponentName() === $parts[0]) {
                if (isset($parts[1])) {
                    return $child->getNamedChild($parts[1]);
                }
                return $child;
            }
            if ($child instanceof TransparentComponentInterface) {
                try {
                    return $child->getNamedChild($name);
                } catch (ChildNotFoundException $exception) {

                }
            }
        }
        throw new ChildNotFoundException(sprintf('Child with name "%s" not found', $name), 1562757835);
    }

    /**
     * Gets a new RootNode with children copied from this current
     * Component. Scans for children of a specific type (a Component
     * class name like a ViewHelper class name) and an optional name
     * which if not-null must also be matched (much like getNamedChild,
     * except does not error when no children match and is capable of
     * returning multiple children if they have the same name).
     *
     * @param string $typeClassName
     * @param string|null $name
     * @return ComponentInterface
     */
    public function getTypedChildren(string $typeClassName, ?string $name = null): ComponentInterface
    {
        $root = new RootNode();
        foreach ($this->children as $child) {
            if ($child instanceof $typeClassName) {
                if ($name === null || ($parts = explode('.', $name, 2)) && $parts[0] === $child->getComponentName()) {
                    // Child will be a Component of the right class; matching name if name is provided. Otherwise ignored.
                    if (isset($parts[1])) {
                        // If $name is null then $parts won't be set and this condition is not entered. If $parts[1] is set
                        // this means the $name had a dot and we must recurse.
                        $root->addChild($child->getTypedChildren($typeClassName, $parts[1]));
                        continue;
                    } else {
                        // Otherwise we indiscriminately add the resolved child to our collection, but only if $parts[1]
                        // was not set (no more recursion), if $name was null, or if $name matched completely.
                        $root->addChild($child);
                    }
                }
            }
            if ($child instanceof TransparentComponentInterface) {
                $root->addChild($child->getTypedChildren($typeClassName, $name));
            }
        }
        return $root;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getChildren(): iterable
    {
        return $this->children;
    }

    /**
     * Returns one of the following:
     *
     * - Itself, if there is more than one child node and one or more nodes are not TextNode or NumericNode
     * - A plain value if there is a single child node of type TextNode or NumericNode
     * - The one child node if there is only a single child node not of type TextNode or NumericNode
     * - Null if there are no child nodes at all.
     *
     * @param bool $extractNode If TRUE, will extract the value of a single node if the node type contains a scalar value
     * @return ComponentInterface|mixed
     */
    public function flatten(bool $extractNode = false)
    {
        if (empty($this->children) && $extractNode) {
            return null;
        }
        if (isset($this->children[0]) && !isset($this->children[1])) {
            if ($extractNode) {
                if ($this->children[0] instanceof TextNode) {
                    /** @var string|float|int $text */
                    $text = $this->children[0]->getText();
                    return is_numeric($text) ? $text + 0 : $text;
                }
            }
            return $this->children[0];
        }
        return $this;
    }

    /**
     * @param iterable|ComponentInterface[] $children
     * @return ComponentInterface
     */
    public function setChildren(iterable $children): ComponentInterface
    {
        $this->children = $children;
        $this->_lastAddedWasTextNode = end($children) instanceof TextNode;
        return $this;
    }

    /**
     * Returns whether the escaping interceptors should be disabled or enabled for the result of renderChildren() calls within this ViewHelper
     *
     * Note: This method is no public API, use $this->escapeChildren instead!
     *
     * @return boolean
     */
    public function isChildrenEscapingEnabled(): bool
    {
        if ($this->escapeChildren === null) {
            // Disable children escaping automatically, if output escaping is on anyway.
            return !$this->isOutputEscapingEnabled();
        }
        return $this->escapeChildren !== false;
    }

    /**
     * Returns whether the escaping interceptors should be disabled or enabled for the render-result of this ViewHelper
     *
     * Note: This method is no public API, use $this->escapeOutput instead!
     *
     * @return boolean
     */
    public function isOutputEscapingEnabled(): bool
    {
        return $this->escapeOutput !== false;
    }

    public function evaluate(RenderingContextInterface $renderingContext)
    {
        return $this->evaluateChildNodes($renderingContext);
    }

    public function allowUndeclaredArgument(string $argumentName): bool
    {
        return true;
    }

    /**
     * Evaluate all child nodes and return the evaluated results.
     *
     * @param RenderingContextInterface $renderingContext
     * @return mixed Normally, an object is returned - in case it is concatenated with a string, a string is returned.
     * @throws Exception
     */
    protected function evaluateChildNodes(RenderingContextInterface $renderingContext)
    {
        $evaluatedNodes = [];
        foreach ($this->getChildren() as $childNode) {
            if ($childNode instanceof EmbeddedComponentInterface) {
                continue;
            }
            $evaluatedNodes[] = $childNode->evaluate($renderingContext);
        }
        // Make decisions about what to actually return
        if (empty($evaluatedNodes)) {
            return null;
        }
        if (count($evaluatedNodes) === 1) {
            return $evaluatedNodes[0];
        }
        $string = '';
        foreach ($evaluatedNodes as $evaluatedNode) {
            $string .= $this->castToString($evaluatedNode);
        }
        return $string;
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function castToString($value): string
    {
        if (is_object($value) && !method_exists($value, '__toString')) {
            throw new Exception('Cannot cast object of type "' . get_class($value) . '" to string.', 1273753083);
        }
        return (string) $value;
    }
}
