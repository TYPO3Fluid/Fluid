<?php
namespace TYPO3Fluid\Fluid\ViewHelpers;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

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
 *     <f:render partial="Foo">
 *         <f:argument name="arg1">Value1</f:argument>
 *         <f:argument name="arg2">Value2</f:argument>
 *     </f:render>
 *
 * Which is the equivalent of:
 *
 *     <f:render partial="Foo" arguments="{arg1: 'Value1', arg2: 'Value2'}'" />
 *
 * But has the benefit that writing ViewHelper expressions or
 * other more complex syntax becomes much easier because you
 * can use tag syntax (tag content becomes argument value).
 *
 * @api
 */
class ArgumentViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    /**
     * @return void
     */
    public function initializeArguments()
    {
        $this->registerArgument('value', 'mixed', 'Value to assign. If not in arguments then taken from tag content');
        $this->registerArgument('name', 'string', 'Name of variable to create', true);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return null
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $delegateVariableProvider = $renderingContext->getViewHelperVariableContainer()->getTopmostDelegateVariableProvider();
        if ($delegateVariableProvider) {
            $delegateVariableProvider->add($arguments['name'], $renderChildrenClosure());
        }
    }

}
