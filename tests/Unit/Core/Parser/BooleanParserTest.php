<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\BooleanParser;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;

/**
 * Testcase for BooleanNode
 */
class BooleanParserTest extends UnitTestCase
{
    /**
     * @var RenderingContextInterface
     */
    protected $renderingContext;

    /**
     * Setup fixture
     */
    public function setUp(): void
    {
        $this->renderingContext = new RenderingContextFixture();
    }

    /**
     * @test
     * @dataProvider getSomeEvaluationTestValues
     * @param string $comparison
     * @param boolean $expected
     */
    public function testSomeEvaluations($comparison, $expected, $variables = [])
    {
        $parser = new BooleanParser();
        $this->assertEquals($expected, BooleanNode::convertToBoolean($parser->evaluate($comparison, $variables), $this->renderingContext), 'Expression: ' . $comparison);

        $compiledEvaluation = $parser->compile($comparison);
        $functionName = 'expression_' . md5($comparison . (function_exists('random_bytes') ? random_bytes(10) : microtime(true)));
        eval('function ' . $functionName . '($context) {return ' . $compiledEvaluation . ';}');
        $this->assertEquals($expected, BooleanNode::convertToBoolean($functionName($variables), $this->renderingContext), 'compiled Expression: ' . $compiledEvaluation);
    }

    /**
     * @return array
     */
    public function getSomeEvaluationTestValues()
    {
        return [
            ['(1 && false) || false || \'foobar\' == \'foobar\'', true],

            ['0', false],
            ['!(1)', false],
            ['!1', false],
            ['', false],
            ['false', false],
            ['false || false', false],
            ['FALSE', false],
            ['fAlSe', false],
            ['   false   ', false],
            ['   FALSE   ', false],
            ['     ', false],
            ['\'foo\' == \'bar\'', false],
            ['\'foo\' != \'foo\'', false],

            ['1', true],
            ['true', true],
            ['TRUE', true],
            ['tRuE', true],
            ['   true   ', true],
            ['   TRUE   ', true],
            ['\' FALSE \'', true],
            ['\' \\\'FALSE \'', true],
            ['\' \\"FALSE \'', true],
            ['foo', true],
            ['\'foo\' == \'foo\'', true],
            ['\'foo\' != \'bar\'', true],
            ['(1 && false) || false || \'foobar\' == \'foobar\'', true],

            ['0 == \'0\'', true, []],
            ['0 == "0"', true, []],
            ['0 === \'0\'', false, []],

            ['1 == 1', true],
            ['1 == 0', false],
            ['1 >= 1', true],
            ['1 <= 1', true],
            ['1 >= 2', false],
            ['2 <= 1', false],
            ['-1 != -1', false],
            ['-1 == -1', true],
            ['-1 < 0', true],
            ['-1 > -2', true],

            ['1 > FALSE',  true],
            ['FALSE > 0',  false],

            ['2 % 2', false],
            ['1 % 2', true],

            ['0 && 1', false],
            ['1 && 1', true],
            ['0 || 0', false],
            ['0 || 1', true],
            ['(0 && 1) || 1', true],
            ['(0 && 0) || 0', false],
            ['(1 && 1) || 0', true],

            ['0 and 1', false],
            ['1 and 1', true],
            ['0 or 0', false],
            ['0 or 1', true],
            ['(0 and 1) or 1', true],
            ['(0 and 0) or 0', false],
            ['(1 and 1) or 0', true],
            ['0 And 1', false],
            ['1 anD 1', true],
            ['0 oR 0', false],
            ['0 Or 1', true],
            ['0 AND 1', false],
            ['1 AND 1', true],
            ['0 OR 0', false],
            ['0 OR 1', true],

            // edge cases as per https://github.com/TYPO3Fluid/Fluid/issues/7
            ['\'foo\' == 0', true],
            ['1.1 >= foo', true],
            ['\'foo\' > 0', false],

            ['{foo}', true, ['foo' => true]],
            ['{foo} == FALSE', true, ['foo' => false]],
            ['!{foo}', true, ['foo' => false]]
        ];
    }
}
