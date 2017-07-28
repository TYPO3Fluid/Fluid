<?php
namespace TYPO3Fluid\Fluid\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * This view helper is an abstract ViewHelper which implements an if/else condition.
 *
 * = Usage =
 *
 * To create a custom Condition ViewHelper, you need to subclass this class, and
 * implement your own render() method. Inside there, you should call $this->renderThenChild()
 * if the condition evaluated to TRUE, and $this->renderElseChild() if the condition evaluated
 * to FALSE.
 *
 * Every Condition ViewHelper has a "then" and "else" argument, so it can be used like:
 * <[aConditionViewHelperName] .... then="condition true" else="condition false" />,
 * or as well use the "then" and "else" child nodes.
 *
 * @see TYPO3Fluid\Fluid\ViewHelpers\IfViewHelper for a more detailed explanation and a simple usage example.
 * Make sure to NOT OVERRIDE the constructor.
 *
 * @api
 */
abstract class AbstractConditionViewHelper extends AbstractViewHelper
{

    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * Initializes the "then" and "else" arguments
     */
    public function initializeArguments()
    {
        $this->createArgument('then', 'mixed')
            ->setDescription('Value to be returned if the condition if met.')
            ->setRequired(false)
            ->setEscaping(true);
        $this->createArgument('else', 'mixed')
            ->setDescription('Value to be returned if the condition if not met.')
            ->setRequired(false)
            ->setEscaping(true);
        $this->registerArgument('condition', 'boolean', 'Condition expression conforming to Fluid boolean rules', false, false);
    }

    /**
     * Static method which can be overridden by subclasses. If a subclass
     * requires a different (or faster) decision then this method is the one
     * to override and implement.
     *
     * Note: method signature does not type-hint that an array is desired,
     * and as such, *appears* to accept any input type. There is no type hint
     * here for legacy reasons - the signature is kept compatible with third
     * party packages which depending on PHP version would error out if this
     * signature was not compatible with that of existing and in-production
     * subclasses that will be using this base class in the future. Let this
     * be a warning if someone considers changing this method signature!
     *
     * @param array|NULL $arguments
     * @return boolean
     * @api
     */
    protected static function evaluateCondition($arguments = null)
    {
        return (boolean) $arguments['condition'];
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return mixed
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        if (static::evaluateCondition($arguments)) {
            if (isset($arguments['then'])) {
                return $arguments['then'];
            }
            if (isset($arguments['__thenClosure'])) {
                return $arguments['__thenClosure']();
            }
        } elseif (!empty($arguments['__elseClosures'])) {
            $elseIfClosures = isset($arguments['__elseifClosures']) ? $arguments['__elseifClosures'] : [];
            return static::evaluateElseClosures($arguments['__elseClosures'], $elseIfClosures, $renderingContext);
        } elseif (array_key_exists('else', $arguments)) {
            return $arguments['else'];
        }
        return '';
    }

    /**
     * @param array $closures
     * @param array $conditionClosures
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    private static function evaluateElseClosures(array $closures, array $conditionClosures, RenderingContextInterface $renderingContext)
    {
        foreach ($closures as $elseNodeIndex => $elseNodeClosure) {
            if (!isset($conditionClosures[$elseNodeIndex])) {
                return $elseNodeClosure();
            } else {
                if ($conditionClosures[$elseNodeIndex]()) {
                    return $elseNodeClosure();
                }
            }
        }
        return '';
    }

    /**
     * Returns value of "then" attribute.
     * If then attribute is not set, iterates through child nodes and renders ThenViewHelper.
     * If then attribute is not set and no ThenViewHelper and no ElseViewHelper is found, all child nodes are rendered
     *
     * @return mixed rendered ThenViewHelper or contents of <f:if> if no ThenViewHelper was found
     * @api
     */
    protected function renderThenChild()
    {
        if ($this->hasArgument('then')) {
            return $this->arguments['then'];
        }

        $elseViewHelperEncountered = false;
        foreach ($this->childNodes as $childNode) {
            if ($childNode instanceof ViewHelperNode
                && substr($childNode->getViewHelperClassName(), -14) === 'ThenViewHelper') {
                $data = $childNode->evaluate($this->renderingContext);
                return $data;
            }
            if ($childNode instanceof ViewHelperNode
                && substr($childNode->getViewHelperClassName(), -14) === 'ElseViewHelper') {
                $elseViewHelperEncountered = true;
            }
        }

        if ($elseViewHelperEncountered) {
            return '';
        } else {
            return $this->renderChildren();
        }
    }

    /**
     * Returns value of "else" attribute.
     * If else attribute is not set, iterates through child nodes and renders ElseViewHelper.
     * If else attribute is not set and no ElseViewHelper is found, an empty string will be returned.
     *
     * @return string rendered ElseViewHelper or an empty string if no ThenViewHelper was found
     * @api
     */
    protected function renderElseChild()
    {

        if ($this->hasArgument('else')) {
            return $this->arguments['else'];
        }

        /** @var ViewHelperNode|NULL $elseNode */
        $elseNode = null;
        foreach ($this->childNodes as $childNode) {
            if ($childNode instanceof ViewHelperNode
                && substr($childNode->getViewHelperClassName(), -14) === 'ElseViewHelper') {
                $arguments = $childNode->getArguments();
                if (isset($arguments['if'])) {
                    if ($arguments['if']->evaluate($this->renderingContext)) {
                        return $childNode->evaluate($this->renderingContext);
                    }
                } else {
                    $elseNode = $childNode;
                }
            }
        }

        return $elseNode instanceof ViewHelperNode ? $elseNode->evaluate($this->renderingContext) : '';
    }

    /**
     * The compiled ViewHelper adds two new ViewHelper arguments: __thenClosure and __elseClosure.
     * These contain closures which are be executed to render the then(), respectively else() case.
     *
     * @param string $argumentsName
     * @param string $closureName
     * @param string $initializationPhpCode
     * @param ViewHelperNode $node
     * @param TemplateCompiler $compiler
     * @return string
     */
    public function compile($argumentsName, $closureName, &$initializationPhpCode, ViewHelperNode $node, TemplateCompiler $compiler)
    {
        $thenViewHelperEncountered = $elseViewHelperEncountered = false;
        foreach ($node->getChildNodes() as $childNode) {
            if ($childNode instanceof ViewHelperNode) {
                $viewHelperClassName = $childNode->getViewHelperClassName();
                if (substr($viewHelperClassName, -14) === 'ThenViewHelper') {
                    $thenViewHelperEncountered = true;
                    $childNodesAsClosure = $compiler->wrapChildNodesInClosure($childNode);
                    $initializationPhpCode .= sprintf('%s[\'__thenClosure\'] = %s;', $argumentsName, $childNodesAsClosure) . chr(10);
                } elseif (substr($viewHelperClassName, -14) === 'ElseViewHelper') {
                    $elseViewHelperEncountered = true;
                    $childNodesAsClosure = $compiler->wrapChildNodesInClosure($childNode);
                    $initializationPhpCode .= sprintf('%s[\'__elseClosures\'][] = %s;', $argumentsName, $childNodesAsClosure) . chr(10);
                    $arguments = $childNode->getArguments();
                    if (isset($arguments['if'])) {
                        // The "else" has an argument, indicating it has a secondary (elseif) condition.
                        // Compile a closure which will evaluate the condition.
                        $elseIfConditionAsClosure = $compiler->wrapViewHelperNodeArgumentEvaluationInClosure($childNode, 'if');
                        $initializationPhpCode .= sprintf('%s[\'__elseifClosures\'][] = %s;', $argumentsName, $elseIfConditionAsClosure) . chr(10);
                    }
                }
            }
        }
        if (!$thenViewHelperEncountered && !$elseViewHelperEncountered && !isset($node->getArguments()['then'])) {
            $initializationPhpCode .= sprintf('%s[\'__thenClosure\'] = %s;', $argumentsName, $closureName) . chr(10);
        }
        return parent::compile($argumentsName, $closureName, $initializationPhpCode, $node, $compiler);
    }
}
