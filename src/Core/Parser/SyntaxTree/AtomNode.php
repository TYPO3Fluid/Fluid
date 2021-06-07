<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Component\AbstractComponent;
use TYPO3Fluid\Fluid\Component\ComponentInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Atom Node
 *
 * Represents an Atom used in a template.
 */
class AtomNode extends AbstractComponent
{
    protected $escapeOutput = false;

    protected $file;

    public function setName(?string $name): ComponentInterface
    {
        $this->name = $name;
        return $this;
    }

    public function setFile(string $file): ComponentInterface
    {
        $this->file = $file;
        return $this;
    }

    public function evaluate(RenderingContextInterface $renderingContext)
    {
        $atom = clone $renderingContext->getTemplateParser()->parseFile($this->file);
        $arguments = clone $atom->getArguments();
        $evaluatedArguments = [];
        foreach ((array) $this->getArguments()->getAllRaw() as $key => $argument) {
            if ($argument instanceof ComponentInterface) {
                $argument = $argument->evaluate($renderingContext);
            }
            $evaluatedArguments[$key] = $argument;
        }
        $arguments->assignAll($evaluatedArguments + $renderingContext->getVariableProvider()->getAll())->setRenderingContext($renderingContext);
        foreach ($this->getChildren() as $child) {
            $atom->addChild($child);
        }
        return $renderingContext->getRenderer()->renderComponent($atom->setArguments($arguments));
    }

    public function onOpen(RenderingContextInterface $renderingContext): ComponentInterface
    {
        $this->getArguments()->setRenderingContext($renderingContext);
        return parent::onOpen($renderingContext);
    }
}
