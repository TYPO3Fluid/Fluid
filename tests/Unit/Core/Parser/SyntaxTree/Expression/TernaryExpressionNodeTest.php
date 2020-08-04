<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser\SyntaxTree;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\TernaryExpressionNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Class TernaryExpressionNodeTest
 */
class TernaryExpressionNodeTest extends UnitTestCase
{

    /**
     * @dataProvider getTernaryExpressionDetection
     * @param string $expression
     * @param mixed $expected
     */
    public function testTernaryExpressionDetection($expression, $expected)
    {
        $result = preg_match_all(TernaryExpressionNode::$detectionExpression, $expression, $matches, PREG_SET_ORDER);
        $this->assertEquals($expected, count($matches) > 0);
    }

    /**
     * @return array
     */
    public function getTernaryExpressionDetection()
    {
        return [
            ['{true ? foo : bar}', true],
            ['{true ? 1 : 0}', true],
            ['{true ? foo : \'no\'}', true],
            ['{(true) ? \'yes\' : \'no\'}', true],
            ['{!(true) ? \'yes\' : \'no\'}', true],
            ['{(true || false) ? \'yes\' : \'no\'}', true],
            ['{(true && 1) ? \'yes\' : \'no\'}', true],
            ['{(\'foo\' == \'foo\') ? \'yes\' : \'no\'}', true],
            ['{(1 > 0) ? \'yes\' : \'no\'}', true],
            ['{(1 < 0) ? \'yes\' : \'no\'}', true],
            ['{(1 >= 0) ? \'yes\' : \'no\'}', true],
            ['{(1 <= 0) ? \'yes\' : \'no\'}', true],
            ['{(1 % 0) ? \'yes\' : \'no\'}', true],
            ['{(true || (\'foo\' == \'bar\')) ? \'yes\' : \'no\'}', true],
            ['{(foo || 1 && 1 && !(false) || (1 % 2) || (1 > 0) || (\'foo\' == \'bar\')) ? \'yes\' : \'no\'}', true],
            ['{{f:if(condition: 1, then: 1, else: 0)} ? \'yes\' : \'no\'}', true],
            ['{zeroString ? zeroString : 123}', true],
        ];
    }

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
        $result = TernaryExpressionNode::evaluateExpression($renderingContext, $expression, []);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getEvaluateExpressionTestValues()
    {
        return [
            ['1 ? 2 : 3', [], 2],
            ['0 ? 2 : 3', [], 3],
            ['zeroString ? zeroString : 123', ['zeroString' => '0'], 123],
            ['nested.zeroString ? nested.zeroString : 123', ['nested' => ['zeroString' => '0']], 123],
        ];
    }
}
