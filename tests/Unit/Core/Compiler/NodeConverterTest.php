<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Compiler;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Compiler\NodeConverter;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ArrayNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EscapingNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\ExpressionNodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\TernaryExpressionNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NumericNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;

/**
 * Class NodeConverterTest
 */
class NodeConverterTest extends UnitTestCase
{

    /**
     * @test
     */
    public function testSetVariableCounter()
    {
        $instance = new NodeConverter(new TemplateCompiler());
        $instance->setVariableCounter(10);
        $this->assertAttributeEquals(10, 'variableCounter', $instance);
    }

    /**
     * @test
     * @dataProvider getConvertMethodCallTestValues
     * @param NodeInterface $node
     * @param string $expected
     */
    public function testConvertCallsExpectedMethod(NodeInterface $node, $expected)
    {
        $instance = $this->getMock(NodeConverter::class, [$expected], [], '', false);
        $instance->expects($this->once())->method($expected);
        $instance->convert($node);
    }

    /**
     * @return array
     */
    public function getConvertMethodCallTestValues()
    {
        return [
            [$this->getMock(TextNode::class, [], [], '', false), 'convertTextNode'],
            [$this->getMock(ExpressionNodeInterface::class), 'convertExpressionNode'],
            [$this->getMock(NumericNode::class, [], [], '', false), 'convertNumericNode'],
            [$this->getMock(ViewHelperNode::class, [], [], '', false), 'convertViewHelperNode'],
            [$this->getMock(ObjectAccessorNode::class, [], [], '', false), 'convertObjectAccessorNode'],
            [$this->getMock(ArrayNode::class, [], [], '', false), 'convertArrayNode'],
            [$this->getMock(RootNode::class, [], [], '', false), 'convertListOfSubNodes'],
            [$this->getMock(BooleanNode::class, [], [], '', false), 'convertBooleanNode'],
            [$this->getMock(EscapingNode::class, [], [], '', false), 'convertEscapingNode'],
        ];
    }

    /**
     * @test
     * @dataProvider getConvertTestValues
     * @param NodeInterface $node
     * @param string $expected
     */
    public function testConvert(NodeInterface $node, $expected)
    {
        $instance = new NodeConverter(new TemplateCompiler());
        $result = $instance->convert($node);
        $this->assertEquals($expected, $result['execution']);
    }

    /**
     * @return array
     */
    public function getConvertTestValues()
    {
        $treeBooleanRoot = new RootNode();
        $treeBooleanRoot->addChildNode(new TextNode('1'));
        $treeBooleanRoot->addChildNode(new TextNode('!='));
        $treeBooleanRoot->addChildNode(new TextNode('2'));
        $treeBoolean = new BooleanNode($treeBooleanRoot);
        $simpleRoot = new RootNode();
        $simpleRoot->addChildNode(new TextNode('foobar'));
        $multiRoot = new RootNode();
        $multiRoot->addChildNode(new TextNode('foo'));
        $multiRoot->addChildNode(new TextNode('bar'));
        $multiRoot->addChildNode(new TextNode('baz'));
        return [
            'Reference to all variable converts to getAll() call' => [
                new ObjectAccessorNode('_all'),
                '$renderingContext->getVariableProvider()->getAll()'
            ],
            'Reference to foo.bar converts to getByPath() call' => [
                new ObjectAccessorNode('foo.bar'),
                '$renderingContext->getVariableProvider()->getByPath(\'foo.bar\', $array0)'
            ],
            'Reference to foo.bar with array accessors converts to array-access inline statement' => [
                new ObjectAccessorNode('foo.bar', ['array', 'array']),
                'isset($renderingContext->getVariableProvider()[\'foo\'][\'bar\']) ? $renderingContext->getVariableProvider()[\'foo\'][\'bar\'] : NULL'
            ],
            'Boolean node with text TRUE converts to boolean true' => [
                new BooleanNode(new TextNode('TRUE')),
                'true'
            ],
            'Boolean node with text FALSE converts to boolean false' => [
                new BooleanNode(new TextNode('FALSE')),
                'false'
            ],
            'Boolean node with text 1 converts to boolean true' => [
                new BooleanNode(new TextNode('1')),
                'true'
            ],
            'Boolean node with text 0 converts to boolean false' => [
                new BooleanNode(new TextNode('0')),
                'false'
            ],
            'Boolean node with number 1 converts to boolean true' => [
                new BooleanNode(new NumericNode(1)),
                'true'
            ],
            'Boolean node with number 0 converts to boolean false' => [
                new BooleanNode(new NumericNode(0)),
                'false'
            ],
            'Boolean node with empty string converts to boolean false' => [
                new BooleanNode(new TextNode('')),
                'false'
            ],
            'Arbitrary expression passed to boolean parser runtime evaluation' => [
                new BooleanNode(new TextNode('1 == 1')),
                'TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode::convertToBoolean(
					$expression1(
						TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode::gatherContext($renderingContext, $array0)
					),
					$renderingContext
				)'
            ],
            'Tree boolean converts to convertToBoolean() call' => [
                $treeBoolean,
                'TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode::convertToBoolean(
					$expression1(
						TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode::gatherContext($renderingContext, $array0)
					),
					$renderingContext
				)'
            ],
            'Ternary expression node converts to closure' => [
                new TernaryExpressionNode('1 ? 2 : 3', [1, 2, 3]),
                '$ternaryExpression1(TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\TernaryExpressionNode::gatherContext($renderingContext, $array0[1]), $renderingContext)'
            ],
            'Escaping node converts to inline htmlspecialchars() check/call' => [
                new EscapingNode(new TextNode('foo')),
                'call_user_func_array( function ($var) { return (is_string($var) || (is_object($var) && method_exists($var, \'__toString\')) ? htmlspecialchars((string) $var, ENT_QUOTES) : $var); }, [\'foo\'])'
            ],
            'ViewHelperNode converts to renderStatic() call' => [
                new ViewHelperNode(
                    new RenderingContextFixture(),
                    'f',
                    'render',
                    ['section' => new TextNode('test'), 'partial' => 'test'],
                    new ParsingState()
                ),
                'TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper::renderStatic($arguments0, $renderChildrenClosure1, $renderingContext)'
            ],
            'Simple single-child root node converts to first child of root node' => [$simpleRoot, '\'foobar\''],
            'Complex multi-child root node converts to variable reference' => [$multiRoot, '$output0'],
            'Text node converts to string' => [new TextNode('test'), '\'test\''],
            'Numeric node converts to string' => [new NumericNode('3'), '3'],
            'Numeric node converts to string and preserves decimals' => [new NumericNode('4.5'), '4.5'],
            'Array node converts to variable reference' => [new ArrayNode(['foo', 'bar']), '$array0'],
            'Empty node converts to empty string' => [$this->getMockBuilder(NodeInterface::class)->getMockForAbstractClass(), '']
        ];
    }
}
