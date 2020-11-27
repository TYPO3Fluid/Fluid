<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\MathExpressionNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Class MathExpressionNodeTest
 */
class MathExpressionNodeTest extends UnitTestCase
{

    /**
     * @dataProvider getEvaluateExpressionTestValues
     * @param string $expression
     * @param array $variables
     * @param mixed $expected
     */
    public function testEvaluateExpression($expression, array $variables, $expected)
    {
        $view = new TemplateView();
        $renderingContext = new RenderingContext($view);
        $renderingContext->setVariableProvider(new StandardVariableProvider($variables));
        $result = MathExpressionNode::evaluateExpression($renderingContext, $expression, []);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getEvaluateExpressionTestValues()
    {
        return [
            'Invalid operator returns zero' => ['1 gabbagabbahey 1', [], 0],
            'Simple addition' => ['1 + 1', [], 2],
            'Chained addition' => ['1 + 1 + 1', [], 3],
            'Multiplication before addition' => ['1 + 1 * 2', [], 4],
            'Subtraction' => ['2 - 1', [], 1],
            'Modulo' => ['2 % 4', [], 2],
            'Multiplication' => ['2 * 4', [], 8],
            'Division' => ['4 / 2', [], 2],
            'Exponentiation' => ['4 ^ 2', [], 16],
            'Simple variable (first) addition' => ['a + 1', ['a' => 1], 2],
            'Simple variable (last) addition' => ['1 + b', ['b' => 1], 2],
            'Multiple variable addition' => ['a + a + 1', ['a' => 1], 3],
            'Variable only addition' => ['a + b', ['a' => 1, 'b' => 1], 2],
            'Addition with boolean "true"' => ['a + 1', ['a' => true], 2],
            'Addition with boolean "false"' => ['a + 1', ['a' => false], 1],
        ];
    }
}
