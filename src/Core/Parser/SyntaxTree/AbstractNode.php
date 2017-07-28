<?php
namespace TYPO3Fluid\Fluid\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Abstract node in the syntax tree which has been built.
 */
abstract class AbstractNode implements NodeInterface
{

    /**
     * List of Child Nodes.
     *
     * @var NodeInterface[]
     */
    protected $childNodes = [];

    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * Evaluate all child nodes and return the evaluated results.
     *
     * @param RenderingContextInterface $renderingContext
     * @return mixed Normally, an object is returned - in case it is concatenated with a string, a string is returned.
     * @throws Parser\Exception
     */
    public function evaluateChildNodes(RenderingContextInterface $renderingContext)
    {
        $numberOfChildNodes = count($this->childNodes);
        if ($numberOfChildNodes === 0) {
            return null;
        }
        if ($numberOfChildNodes === 1) {
            return $this->evaluateChildNode($this->childNodes[0], $renderingContext, false);
        }
        $output = '';
        /** @var $subNode NodeInterface */
        foreach ($this->childNodes as $subNode) {
            $output .= $this->evaluateChildNode($subNode, $renderingContext, true);
        }
        return $output;
    }

    /**
     * @param NodeInterface $node
     * @param RenderingContextInterface $renderingContext
     * @param boolean $cast
     * @return mixed
     */
    protected function evaluateChildNode(NodeInterface $node, RenderingContextInterface $renderingContext, $cast)
    {
        $output = $node->evaluate($renderingContext);
        if ($cast && is_object($output)) {
            if (!method_exists($output, '__toString')) {
                throw new Parser\Exception('Cannot cast object of type "' . get_class($output) . '" to string.', 1273753083);
            }
            $output = (string) $output;
        }
        if ($cast && is_array($output)) {
            $output = 'Array';
        }
        return $output;
    }

    /**
     * Returns all child nodes for a given node.
     * This is especially needed to implement the boolean expression language.
     *
     * @return NodeInterface[] A list of nodes
     */
    public function getChildNodes()
    {
        return $this->childNodes;
    }

    /**
     * Appends a sub node to this node. Is used inside the parser to append children
     *
     * @param NodeInterface $childNode The sub node to add
     * @return void
     */
    public function addChildNode(NodeInterface $childNode)
    {
        $this->childNodes[] = $childNode;
    }

    /**
     * Specifies, if this node's output should be escaped
     *
     * @return bool
     */
    public function isEscapeOutputEnabled()
    {
        return $this->escapeOutput;
    }

    /**
     * @param bool $escapeOutput
     */
    public function setEscapeOutput($escapeOutput)
    {
        $this->escapeOutput = $escapeOutput;
    }

}
