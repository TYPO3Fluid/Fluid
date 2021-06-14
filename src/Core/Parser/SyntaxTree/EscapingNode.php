<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Component\AbstractComponent;
use TYPO3Fluid\Fluid\Component\ComponentInterface;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Escaping Node - wraps all content that must be escaped before output.
 */
class EscapingNode extends AbstractComponent
{
    /**
     * Node to be escaped
     *
     * @var ComponentInterface
     */
    protected $node;

    public function __construct(ComponentInterface $node)
    {
        $this->node = $node;
    }

    public function getComponentName(): ?string
    {
        return $this->node->getComponentName();
    }

    public function addChild(ComponentInterface $component): ComponentInterface
    {
        $this->node = $component;
        return $this;
    }

    public function evaluate(RenderingContextInterface $renderingContext)
    {
        $evaluated = $this->node->evaluate($renderingContext);
        if (is_array($evaluated)) {
            throw new Exception('Array can not be converted to string: ' . var_export($evaluated, true), 1623650387);
        }
        if (is_string($evaluated) || (is_object($evaluated) && method_exists($evaluated, '__toString'))) {
            return htmlspecialchars((string) $evaluated, ENT_QUOTES);
        }
        return (string) $evaluated;
    }
}
