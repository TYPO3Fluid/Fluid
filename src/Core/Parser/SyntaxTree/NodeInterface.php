<?php
namespace TYPO3Fluid\Fluid\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Node in the syntax tree.
 */
interface NodeInterface
{

    /**
     * Evaluate all child nodes and return the evaluated results.
     *
     * @param RenderingContextInterface $renderingContext
     * @return mixed Normally, an object is returned - in case it is concatenated with a string, a string is returned.
     */
    public function evaluateChildNodes(RenderingContextInterface $renderingContext);

    /**
     * Returns all child nodes for a given node.
     *
     * @return array<\TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface> A list of nodes
     */
    public function getChildNodes();

    /**
     * Appends a sub node to this node. Is used inside the parser to append children
     *
     * @param NodeInterface $childNode The sub node to add
     * @return void
     */
    public function addChildNode(NodeInterface $childNode);

    /**
     * Evaluates the node - can return not only strings, but arbitary objects.
     *
     * @param RenderingContextInterface $renderingContext
     * @return mixed Evaluated node
     */
    public function evaluate(RenderingContextInterface $renderingContext);

    /**
     * Specifies, if this node's output should be escaped
     *
     * @return bool
     */
    public function isEscapeOutputEnabled();

    /**
     * Enable or disable output escaping
     *
     * @param bool $escapeOutput
     */
    public function setEscapeOutput($escapeOutput);

}
