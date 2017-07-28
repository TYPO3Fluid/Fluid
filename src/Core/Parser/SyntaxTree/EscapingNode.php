<?php
namespace TYPO3Fluid\Fluid\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;

/**
 * Escaping Node - wraps all content that must be escaped before output.
 */
class EscapingNode extends AbstractNode
{

    /**
     * Node to be escaped
     *
     * @var NodeInterface
     */
    protected $node;

    /**
     * Constructor.
     *
     * @param NodeInterface $node
     */
    public function __construct(NodeInterface $node)
    {
        $this->node = $node;
    }

    /**
     * Return the value associated to the syntax tree.
     *
     * @param RenderingContextInterface $renderingContext
     * @return number the value stored in this node/subtree.
     */
    public function evaluate(RenderingContextInterface $renderingContext)
    {
        $nodeReturnValue = $this->node->evaluate($renderingContext);

        if (is_array($nodeReturnValue) || (is_object($nodeReturnValue) && !method_exists($nodeReturnValue, '__toString'))) {
            return $nodeReturnValue;
        }

        return htmlspecialchars(strval($nodeReturnValue), ENT_QUOTES);
    }

    /**
     * @return NodeInterface
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * NumericNode does not allow adding child nodes, so this will always throw an exception.
     *
     * @param NodeInterface $childNode The sub node to add
     * @throws Parser\Exception
     * @return void
     */
    public function addChildNode(NodeInterface $childNode)
    {
        $this->node = $childNode;
    }
}
