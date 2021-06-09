<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\MathExpressionNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers\Fixtures\UserWithToString;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;

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
        $renderingContext = new RenderingContext();
        $renderingContext->setVariableProvider(new StandardVariableProvider($variables));
        $result = MathExpressionNode::evaluateExpression($renderingContext, $expression, []);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getEvaluateExpressionTestValues()
    {
        $objectWithToStringReturningNumericValue = new UserWithToString('123');
        $objectWithToStringReturningNonNumericValue = new UserWithToString('text');
        return [
            'invalid operator returns zero' => ['1 gabbagabbahey 1', [], 0],
            '1 + 1 = 2' => ['1 + 1', [], 2],
            '2 - 1 = 1' => ['2 - 1', [], 1],
            '2 % 4 = 2' => ['2 % 4', [], 2],
            '2 * 4 = 8' => ['2 * 4', [], 8],
            '4 / 2 = 2' => ['4 / 2', [], 2],
            '4 ^ 2 = 16' => ['4 ^ 2', [], 16],
            '$a(1) + 1 = 2' => ['a + 1', ['a' => 1], 2],
            '1 + $b(1) = 2' => ['1 + b', ['b' => 1], 2],
            '$a(1) + $b(1) = 2' => ['a + b', ['a' => 1, 'b' => 1], 2],
            '$a(array) + $b(array) = $union' => ['a + b', ['a' => ['foo' => 'foo'], 'b' => ['bar' => 'bar']], ['foo' => 'foo', 'bar' => 'bar']],
            '$a(object-numeric) + 2 = 125' => ['a + 2', ['a' => $objectWithToStringReturningNumericValue], 125],
            '$a(object-not-numeric) + 2 = 2' => ['a + 2', ['a' => $objectWithToStringReturningNonNumericValue], 2],
            '$notdefined(null) + 1 = 1' => ['notdefined + 1', [], 1],
            '$a(true) + 1 = 2' => ['a + 1', ['a' => true], 2],
            '$a(false) + 1 = 1' => ['a + 1', ['a' => false], 1],
        ];
    }
}
