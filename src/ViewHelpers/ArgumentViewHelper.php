<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\ViewHelpers;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Component\EmbeddedComponentInterface;
use TYPO3Fluid\Fluid\Component\SequencingComponentInterface;
use TYPO3Fluid\Fluid\Core\Parser\Sequencer;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Argument assigning ViewHelper
 *
 * Assigns an argument for a parent ViewHelper call when
 * the parent ViewHelper supports it.
 *
 * Alternative to declaring an array to pass as "arguments".
 *
 * Usages:
 *
 *     <my:atom arg0="Foo">
 *         <f:argument name="arg1">Value1</f:argument>
 *         <f:argument name="arg2">Value2</f:argument>
 *     </f:atom>
 *
 * Which is the equivalent of:
 *
 *     <my:atom arg0="Foo" arg1="Foo" arg2="Foo" />
 *
 * But has the benefit that writing ViewHelper expressions or
 * other more complex syntax becomes much easier because you
 * can use tag syntax (tag content becomes argument value).
 *
 */
class ArgumentViewHelper extends AbstractViewHelper implements EmbeddedComponentInterface
{
    protected $escapeOutput = false;

    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('name', 'string', 'Name of the parameter', true);
    }

    public function evaluate(RenderingContextInterface $renderingContext)
    {
        $arguments = $this->getArguments()->setRenderingContext($renderingContext)->getArrayCopy();
        return $arguments['value'] ?? $this->evaluateChildNodes($renderingContext);
    }
}
