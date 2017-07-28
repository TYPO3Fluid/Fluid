<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Compiler\StopCompilingException;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\Configuration;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Parser\InterceptorInterface;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\AbstractNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;
use TYPO3Fluid\Fluid\Core\Parser\TemplateProcessorInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Parser\Fixtures\PostParseFacetViewHelper;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;

/**
 * Testcase for TemplateParser.
 *
 * This is to at least half a system test, as it compares rendered results to
 * expectations, and does not strictly check the parsing...
 */
class TemplateParserTest extends UnitTestCase
{

    /**
     * @test
     */
    public function testInitializeViewHelperAndAddItToStackReturnsFalseIfNamespaceNotValid()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['isNamespaceValid']);
        $resolver->expects($this->once())->method('isNamespaceValid')->willReturn(false);
        $context = new RenderingContextFixture();
        $context->setViewHelperResolver($resolver);
        $templateParser = new TemplateParser();
        $templateParser->setRenderingContext($context);
        $method = new \ReflectionMethod($templateParser, 'initializeViewHelperAndAddItToStack');
        $method->setAccessible(true);
        $result = $method->invokeArgs($templateParser, [new ParsingState(), 'f', 'render', []]);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function testClosingViewHelperTagHandlerReturnsFalseIfNamespaceNotValid()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['isNamespaceValid']);
        $resolver->expects($this->once())->method('isNamespaceValid')->willReturn(false);
        $context = new RenderingContextFixture();
        $context->setViewHelperResolver($resolver);
        $templateParser = new TemplateParser();
        $templateParser->setRenderingContext($context);
        $method = new \ReflectionMethod($templateParser, 'closingViewHelperTagHandler');
        $method->setAccessible(true);
        $result = $method->invokeArgs($templateParser, [new ParsingState(), 'f', 'render']);
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testPublicGetAndSetEscapingEnabledWorks()
    {
        $subject = new TemplateParser();
        $default = $subject->isEscapingEnabled();
        $subject->setEscapingEnabled(!$default);
        $this->assertAttributeSame(!$default, 'escapingEnabled', $subject);
    }

    /**
     * @test
     */
    public function testBuildObjectTreeThrowsExceptionOnUnclosedViewHelperTag()
    {
        $renderingContext = new RenderingContextFixture();
        $renderingContext->setVariableProvider(new StandardVariableProvider());
        $templateParser = new TemplateParser();
        $templateParser->setRenderingContext($renderingContext);
        $this->setExpectedException(Exception::class);
        $method = new \ReflectionMethod($templateParser, 'buildObjectTree');
        $method->setAccessible(true);
        $method->invokeArgs($templateParser, [['<f:render>'], TemplateParser::CONTEXT_INSIDE_VIEWHELPER_ARGUMENTS]);
    }

    /**
     * @test
     */
    public function testParseCallsPreProcessOnTemplateProcessors()
    {
        $templateParser = new TemplateParser();
        $processor1 = $this->getMockForAbstractClass(
            TemplateProcessorInterface::class,
            [],
            '',
            false,
            false,
            true,
            ['preProcessSource']
        );
        $processor2 = clone $processor1;
        $processor1->expects($this->once())->method('preProcessSource')->with('source1')->willReturn('source2');
        $processor2->expects($this->once())->method('preProcesssource')->with('source2')->willReturn('final');
        $context = new RenderingContextFixture();
        $context->setTemplateProcessors([$processor1, $processor2]);
        $context->setVariableProvider(new StandardVariableProvider());
        $templateParser->setRenderingContext($context);
        $result = $templateParser->parse('source1')->render($context);
        $this->assertEquals('final', $result);
    }

    /**
     * @test
     */
    public function getOrParseAndStoreTemplateSetsCompilableStateAndReturnsOnStopComilingException()
    {
        $parsedTemplate = $this->getMock(ParsingState::class, ['setCompilable']);
        $parsedTemplate->expects($this->once())->method('setCompilable')->with(false);
        $templateParser = $this->getMock(TemplateParser::class, ['parse']);
        $templateParser->expects($this->once())->method('parse')->willReturn($parsedTemplate);
        $context = new RenderingContextFixture();
        $compiler = $this->getMock(TemplateCompiler::class, ['store', 'has']);
        $compiler->expects($this->once())->method('has')->willReturn(false);
        $compiler->expects($this->once())->method('store')->willThrowException(new StopCompilingException());
        $context->setTemplateCompiler($compiler);
        $context->setVariableProvider(new StandardVariableProvider());
        $templateParser->setRenderingContext($context);
        $parsedTemplate = $templateParser->getOrParseAndStoreTemplate('fake-foo-baz', function ($a, $b) {
            return 'test';
        });
    }

    /**
     * @test
     * @expectedException Exception
     */
    public function parseThrowsExceptionWhenStringArgumentMissing()
    {
        $templateParser = new TemplateParser();
        $templateParser->parse(123);
    }

    /**
     */
    public function quotedStrings()
    {
        return [
            ['"no quotes here"', 'no quotes here'],
            ["'no quotes here'", 'no quotes here'],
            ["'this \"string\" had \\'quotes\\' in it'", 'this "string" had \'quotes\' in it'],
            ['"this \\"string\\" had \'quotes\' in it"', 'this "string" had \'quotes\' in it'],
            ['"a weird \"string\" \'with\' *freaky* \\\\stuff', 'a weird "string" \'with\' *freaky* \\stuff'],
            ['\'\\\'escaped quoted string in string\\\'\'', '\'escaped quoted string in string\'']
        ];
    }

    /**
     * @dataProvider quotedStrings
     * @test
     */
    public function unquoteStringReturnsUnquotedStrings($quoted, $unquoted)
    {
        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $this->assertEquals($unquoted, $templateParser->_call('unquoteString', $quoted));
    }

    /**
     */
    public function templatesToSplit()
    {
        return [
            ['TemplateParserTestFixture01-shorthand'],
            ['TemplateParserTestFixture06'],
            ['TemplateParserTestFixture14']
        ];
    }

    /**
     * @dataProvider templatesToSplit
     * @test
     */
    public function splitTemplateAtDynamicTagsReturnsCorrectlySplitTemplate($templateName)
    {
        $template = file_get_contents(__DIR__ . '/Fixtures/' . $templateName . '.html', FILE_TEXT);
        $expectedResult = require __DIR__ . '/Fixtures/' . $templateName . '-split.php';
        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $this->assertSame($expectedResult, $templateParser->_call('splitTemplateAtDynamicTags', $template), 'Filed for ' . $templateName);
    }

    /**
     * @test
     */
    public function buildObjectTreeCreatesRootNodeAndSetsUpParsingState()
    {
        $context = new RenderingContextFixture();
        $context->setVariableProvider(new StandardVariableProvider());
        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $templateParser->setRenderingContext($context);
        $result = $templateParser->_call('buildObjectTree', [], TemplateParser::CONTEXT_OUTSIDE_VIEWHELPER_ARGUMENTS);
        $this->assertInstanceOf(ParsingState::class, $result);
    }

    /**
     * @test
     */
    public function buildObjectTreeDelegatesHandlingOfTemplateElements()
    {
        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            [
                'textHandler',
                'openingViewHelperTagHandler',
                'closingViewHelperTagHandler',
                'textAndShorthandSyntaxHandler'
            ]
        );
        $context = new RenderingContextFixture();
        $context->setVariableProvider(new StandardVariableProvider());
        $templateParser->setRenderingContext($context);
        $splitTemplate = $templateParser->_call('splitTemplateAtDynamicTags', 'The first part is simple<![CDATA[<f:for each="{a: {a: 0, b: 2, c: 4}}" as="array"><f:for each="{array}" as="value">{value} </f:for>]]><f:format.printf arguments="{number : 362525200}">%.3e</f:format.printf>and here goes some {text} that could have {shorthand}');
        $result = $templateParser->_call('buildObjectTree', $splitTemplate, TemplateParser::CONTEXT_OUTSIDE_VIEWHELPER_ARGUMENTS);
        $this->assertInstanceOf(ParsingState::class, $result);
    }

    /**
     * @test
     */
    public function openingViewHelperTagHandlerDelegatesViewHelperInitialization()
    {
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->never())->method('popNodeFromStack');
        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['parseArguments', 'initializeViewHelperAndAddItToStack']
        );
        $context = new RenderingContextFixture();
        $templateParser->setRenderingContext($context);
        $templateParser->expects($this->once())->method('parseArguments')
            ->with(['arguments'])->will($this->returnValue(['parsedArguments']));
        $templateParser->expects($this->once())->method('initializeViewHelperAndAddItToStack')
            ->with($mockState, 'namespaceIdentifier', 'methodIdentifier', ['parsedArguments']);

        $templateParser->_call('openingViewHelperTagHandler', $mockState, 'namespaceIdentifier', 'methodIdentifier', ['arguments'], false, '');
    }

    /**
     * @test
     */
    public function openingViewHelperTagHandlerPopsNodeFromStackForSelfClosingTags()
    {
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('popNodeFromStack')->will($this->returnValue($this->getMock(NodeInterface::class)));
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($this->getMock(NodeInterface::class)));

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['parseArguments', 'initializeViewHelperAndAddItToStack']
        );
        $node = $this->getMock(ViewHelperNode::class, ['dummy'], [], '', false);
        $templateParser->expects($this->once())->method('initializeViewHelperAndAddItToStack')->will($this->returnValue($node));

        $templateParser->_call('openingViewHelperTagHandler', $mockState, '', '', [], true, '');
    }

    /**
     * @__test
     * @expectedException Exception
     */
    public function initializeViewHelperAndAddItToStackThrowsExceptionIfViewHelperClassDoesNotExisit()
    {
        $mockState = $this->getMock(ParsingState::class);

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            [
                'abortIfUnregisteredArgumentsExist',
                'abortIfRequiredArgumentsAreMissing',
                'rewriteBooleanNodesInArgumentsObjectTree'
            ]
        );

        $templateParser->_call('initializeViewHelperAndAddItToStack', $mockState, 'f', 'nonExisting', ['arguments']);
    }

    /**
     * @__test
     * @expectedException Exception
     */
    public function initializeViewHelperAndAddItToStackThrowsExceptionIfViewHelperClassNameIsWronglyCased()
    {
        $mockState = $this->getMock(ParsingState::class);

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            [
                'abortIfUnregisteredArgumentsExist',
                'abortIfRequiredArgumentsAreMissing',
                'rewriteBooleanNodesInArgumentsObjectTree'
            ]
        );

        $templateParser->_call('initializeViewHelperAndAddItToStack', $mockState, 'f', 'wRongLyCased', ['arguments']);
    }

    /**
     * @__test
     */
    public function initializeViewHelperAndAddItToStackCreatesRequestedViewHelperAndViewHelperNode()
    {
        $mockViewHelper = $this->getMock(AbstractViewHelper::class);
        $mockViewHelperNode = $this->getMock(ViewHelperNode::class, [], [], '', false);

        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('pushNodeToStack')->with($this->anything());

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            [
                'abortIfUnregisteredArgumentsExist',
                'abortIfRequiredArgumentsAreMissing',
                'rewriteBooleanNodesInArgumentsObjectTree'
            ]
        );

        $templateParser->_call('initializeViewHelperAndAddItToStack', $mockState, 'f', 'render', ['arguments']);
    }

    /**
     * @test
     * @expectedException Exception
     */
    public function closingViewHelperTagHandlerThrowsExceptionBecauseOfClosingTagWhichWasNeverOpened()
    {
        $mockNodeOnStack = $this->getMock(NodeInterface::class, [], [], '', false);
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('popNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $templateParser->_set('renderingContext', new RenderingContextFixture());

        $templateParser->_call('closingViewHelperTagHandler', $mockState, 'f', 'render');
    }

    /**
     * @test
     * @expectedException Exception
     */
    public function closingViewHelperTagHandlerThrowsExceptionBecauseOfWrongTagNesting()
    {
        $mockNodeOnStack = $this->getMock(ViewHelperNode::class, [], [], '', false);
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('popNodeFromStack')->will($this->returnValue($mockNodeOnStack));
        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $templateParser->_set('renderingContext', new RenderingContextFixture());
        $templateParser->_call('closingViewHelperTagHandler', $mockState, 'f', 'render');
    }

    /**
     * @test
     */
    public function objectAccessorHandlerCallsInitializeViewHelperAndAddItToStackIfViewHelperSyntaxIsPresent()
    {
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->exactly(2))->method('popNodeFromStack')
            ->will($this->returnValue($this->getMock(NodeInterface::class)));
        $mockState->expects($this->exactly(2))->method('getNodeFromStack')
            ->will($this->returnValue($this->getMock(NodeInterface::class)));

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['recursiveArrayHandler', 'initializeViewHelperAndAddItToStack']
        );
        $templateParser->expects($this->at(0))->method('recursiveArrayHandler')
            ->with('format: "H:i"')->will($this->returnValue(['format' => 'H:i']));
        $templateParser->expects($this->at(1))->method('initializeViewHelperAndAddItToStack')
            ->with($mockState, 'f', 'format.date', ['format' => 'H:i'])->will($this->returnValue(true));
        $templateParser->expects($this->at(2))->method('initializeViewHelperAndAddItToStack')
            ->with($mockState, 'f', 'debug', [])->will($this->returnValue(true));

        $templateParser->_call('objectAccessorHandler', $mockState, '', '', 'f:debug() -> f:format.date(format: "H:i")', '');
    }

    /**
     * @test
     */
    public function objectAccessorHandlerCreatesObjectAccessorNodeWithExpectedValueAndAddsItToStack()
    {
        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockNodeOnStack->expects($this->once())->method('addChildNode')->with($this->anything());
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);

        $templateParser->_call('objectAccessorHandler', $mockState, 'objectAccessorString', '', '', '');
    }

    /**
     * @test
     */
    public function valuesFromObjectAccessorsAreRunThroughEscapingInterceptorsByDefault()
    {
        $objectAccessorNodeInterceptor = $this->getMock(InterceptorInterface::class);
        $objectAccessorNodeInterceptor->expects($this->once())->method('process')
            ->with($this->anything())->willReturnArgument(0);

        $parserConfiguration = $this->getMock(Configuration::class);
        $parserConfiguration->expects($this->any())->method('getInterceptors')->willReturn([]);
        $parserConfiguration->expects($this->once())->method('getEscapingInterceptors')
            ->with(InterceptorInterface::INTERCEPT_OBJECTACCESSOR)
            ->will($this->returnValue([$objectAccessorNodeInterceptor]));

        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $templateParser->_set('configuration', $parserConfiguration);

        $templateParser->_call('objectAccessorHandler', $mockState, 'objectAccessorString', '', '', '');
    }

    /**
     * @test
     */
    public function valuesFromObjectAccessorsAreNotRunThroughEscapingInterceptorsIfEscapingIsDisabled()
    {
        $objectAccessorNode = $this->getMock(ObjectAccessorNode::class, [], [], '', false);

        $parserConfiguration = $this->getMock(Configuration::class);
        $parserConfiguration->expects($this->any())->method('getInterceptors')->will($this->returnValue([]));
        $parserConfiguration->expects($this->never())->method('getEscapingInterceptors');

        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $templateParser->_set('configuration', $parserConfiguration);
        $templateParser->_set('escapingEnabled', false);

        $templateParser->_call('objectAccessorHandler', $mockState, 'objectAccessorString', '', '', '');
    }


    /**
     * @test
     */
    public function valuesFromObjectAccessorsAreRunThroughInterceptors()
    {
        $objectAccessorNode = $this->getMock(ObjectAccessorNode::class, [], [], '', false);
        $objectAccessorNodeInterceptor = $this->getMock(InterceptorInterface::class);
        $objectAccessorNodeInterceptor->expects($this->once())->method('process')
            ->with($this->anything())->will($this->returnArgument(0));

        $parserConfiguration = $this->getMock(Configuration::class);
        $parserConfiguration->expects($this->any())->method('getEscapingInterceptors')->willReturn([]);
        $parserConfiguration->expects($this->once())->method('getInterceptors')
            ->with(InterceptorInterface::INTERCEPT_OBJECTACCESSOR)->willReturn([$objectAccessorNodeInterceptor]);

        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->willReturn($mockNodeOnStack);

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);
        $templateParser->_set('configuration', $parserConfiguration);
        $templateParser->_set('escapingEnabled', false);

        $templateParser->_call('objectAccessorHandler', $mockState, 'objectAccessorString', '', '', '');
    }

    /**
     */
    public function argumentsStrings()
    {
        return [
            ['a="2"', ['a' => '2']],
            ['a="2" b="foobar \' with \\" quotes"', ['a' => '2', 'b' => 'foobar \' with " quotes']],
            [' arguments="{number : 362525200}"', ['arguments' => '{number : 362525200}']]
        ];
    }

    /**
     * @test
     * @dataProvider argumentsStrings
     * @param string $argumentsString
     * @param array $expected
     */
    public function parseArgumentsWorksAsExpected($argumentsString, array $expected)
    {
        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['buildArgumentObjectTree']);
        $templateParser->expects($this->any())->method('buildArgumentObjectTree')->will($this->returnArgument(0));

        $this->assertSame($expected, $templateParser->_call('parseArguments', $argumentsString));
    }

    /**
     * @test
     */
    public function buildArgumentObjectTreeReturnsTextNodeForSimplyString()
    {

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['dummy']);

        $this->assertInstanceof(
            TextNode::class,
            $templateParser->_call('buildArgumentObjectTree', 'a very plain string')
        );
    }

    /**
     * @test
     */
    public function buildArgumentObjectTreeBuildsObjectTreeForComplexString()
    {
        $objectTree = $this->getMock(ParsingState::class);
        $objectTree->expects($this->once())->method('getRootNode')->will($this->returnValue('theRootNode'));

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['splitTemplateAtDynamicTags', 'buildObjectTree']
        );
        $templateParser->expects($this->at(0))->method('splitTemplateAtDynamicTags')
            ->with('a <very> {complex} string')->will($this->returnValue(['split string']));
        $templateParser->expects($this->at(1))->method('buildObjectTree')
            ->with(['split string'])->will($this->returnValue($objectTree));

        $this->assertEquals('theRootNode', $templateParser->_call('buildArgumentObjectTree', 'a <very> {complex} string'));
    }

    /**
     * @test
     */
    public function textAndShorthandSyntaxHandlerDelegatesAppropriately()
    {
        $mockState = $this->getMock(ParsingState::class, ['getNodeFromStack']);
        $mockState->expects($this->any())->method('getNodeFromStack')->willReturn(new RootNode());

        $templateParser = $this->getMock(
            TemplateParser::class,
            ['objectAccessorHandler', 'arrayHandler', 'textHandler']
        );
        $context = new RenderingContextFixture();
        $templateParser->setRenderingContext($context);
        $templateParser->expects($this->at(0))->method('textHandler')->with($mockState, ' ');
        $templateParser->expects($this->at(1))->method('objectAccessorHandler')->with($mockState, 'someThing.absolutely', '', '', '');
        $templateParser->expects($this->at(2))->method('textHandler')->with($mockState, ' "fishy" is \'going\' ');
        $templateParser->expects($this->at(3))->method('arrayHandler')->with($mockState, $this->anything());

        $text = ' {someThing.absolutely} "fishy" is \'going\' {on: "here"}';
        $method = new \ReflectionMethod(TemplateParser::class, 'textAndShorthandSyntaxHandler');
        $method->setAccessible(true);
        $method->invokeArgs($templateParser, [$mockState, $text, TemplateParser::CONTEXT_INSIDE_VIEWHELPER_ARGUMENTS]);
    }

    /**
     * @test
     */
    public function arrayHandlerAddsArrayNodeWithProperContentToStack()
    {
        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockNodeOnStack->expects($this->once())->method('addChildNode')->with($this->anything());
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['recursiveArrayHandler']
        );
        $templateParser->expects($this->any())->method('recursiveArrayHandler')
            ->with(['arrayText'])->will($this->returnValue('processedArrayText'));

        $templateParser->_call('arrayHandler', $mockState, ['arrayText']);
    }

    /**
     */
    public function arrayTexts()
    {
        return [
            [
                'key1: "foo", key2: \'bar\', key3: someVar, key4: 123, key5: { key6: "baz" }',
                ['key1' => 'foo', 'key2' => 'bar', 'key3' => 'someVar', 'key4' => 123.0, 'key5' => ['key6' => 'baz']]
            ],
            [
                'key1: "\'foo\'", key2: \'\\\'bar\\\'\'',
                ['key1' => '\'foo\'', 'key2' => '\'bar\'']
            ]
        ];
    }

    /**
     * @__test
     * @dataProvider arrayTexts
     */
    public function recursiveArrayHandlerReturnsExpectedArray($arrayText, $expectedArray)
    {
        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['buildArgumentObjectTree']);
        $templateParser->expects($this->any())->method('buildArgumentObjectTree')->willReturnArgument(0);
        $this->assertSame($expectedArray, $templateParser->_call('recursiveArrayHandler', $arrayText));
    }

    /**
     * @test
     */
    public function textNodesAreRunThroughEscapingInterceptorsByDefault()
    {
        $textNode = $this->getMock(TextNode::class, [], [], '', false);
        $textInterceptor = $this->getMock(InterceptorInterface::class);
        $textInterceptor->expects($this->once())->method('process')->with($this->anything())->willReturnArgument(0);

        $parserConfiguration = $this->getMock(Configuration::class);
        $parserConfiguration->expects($this->once())->method('getEscapingInterceptors')
            ->with(InterceptorInterface::INTERCEPT_TEXT)->will($this->returnValue([$textInterceptor]));
        $parserConfiguration->expects($this->any())->method('getInterceptors')->will($this->returnValue([]));

        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockNodeOnStack->expects($this->once())->method('addChildNode')->with($this->anything());
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(TemplateParser::class, ['splitTemplateAtDynamicTags', 'buildObjectTree']);
        $templateParser->_set('configuration', $parserConfiguration);

        $templateParser->_call('textHandler', $mockState, 'string');
    }

    /**
     * @test
     */
    public function textNodesAreNotRunThroughEscapingInterceptorsIfEscapingIsDisabled()
    {
        $parserConfiguration = $this->getMock(Configuration::class);
        $parserConfiguration->expects($this->never())->method('getEscapingInterceptors');
        $parserConfiguration->expects($this->any())->method('getInterceptors')->will($this->returnValue([]));

        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockNodeOnStack->expects($this->once())->method('addChildNode')->with($this->anything());
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['splitTemplateAtDynamicTags', 'buildObjectTree']
        );
        $templateParser->_set('configuration', $parserConfiguration);
        $templateParser->_set('escapingEnabled', false);

        $templateParser->_call('textHandler', $mockState, 'string');
    }

    /**
     * @test
     */
    public function textNodesAreRunThroughInterceptors()
    {
        $textInterceptor = $this->getMock(InterceptorInterface::class);
        $textInterceptor->expects($this->once())->method('process')->with($this->anything())->will($this->returnArgument(0));

        $parserConfiguration = $this->getMock(Configuration::class);
        $parserConfiguration->expects($this->once())->method('getInterceptors')
            ->with(InterceptorInterface::INTERCEPT_TEXT)->will($this->returnValue([$textInterceptor]));
        $parserConfiguration->expects($this->any())->method('getEscapingInterceptors')->will($this->returnValue([]));

        $mockNodeOnStack = $this->getMock(AbstractNode::class, [], [], '', false);
        $mockNodeOnStack->expects($this->once())->method('addChildNode')->with($this->anything());
        $mockState = $this->getMock(ParsingState::class);
        $mockState->expects($this->once())->method('getNodeFromStack')->will($this->returnValue($mockNodeOnStack));

        $templateParser = $this->getAccessibleMock(
            TemplateParser::class,
            ['splitTemplateAtDynamicTags', 'buildObjectTree']
        );
        $templateParser->_set('configuration', $parserConfiguration);
        $templateParser->_set('escapingEnabled', false);

        $templateParser->_call('textHandler', $mockState, 'string');
    }

    /**
     * @return array
     */
    public function getExampleScriptTestValues()
    {
        return [
            [
                '<f:format.raw></f:format.raw>'
            ],
            [
                '{foo -> f:format.raw()}'
            ],
            [
                '{f:format.raw(value: foo)}'
            ],

            [
                '<foo:bar></foo:bar>',
                [],
                Exception::class
            ],
            [
                '{foo -> foo:bar()}',
                [],
                Exception::class
            ],
            [
                '{foo:bar(value: foo)}',
                [],
                Exception::class
            ],

            [
                '{namespace *} <foo:bar></foo:bar>',
                ['foo']
            ],
            [
                '{namespace foo} {foo -> foo:bar()}',
                ['foo']
            ],
            [
                '{namespace fo*} {foo:bar(value: foo)}',
                ['foo']
            ],
            [
                '
				{namespace a=Foo\A\ViewHelpers}
				<![CDATA[
					{namespace b=Foo\B\ViewHelpers}
					<![CDATA[
						{namespace c=Foo\C\ViewHelpers}
					]]>
					{namespace d=Foo\D\ViewHelpers}
				]]>
				{namespace e=Foo\E\ViewHelpers}
				',
                [],
                null,
                [
                    'f' => 'TYPO3Fluid\Fluid\ViewHelpers',
                    'a' => 'Foo\A\ViewHelpers',
                    'e' => 'Foo\E\ViewHelpers'
                ]
            ],
            [
                '<a href="javascript:window.location.reload()">reload</a>'
            ],

            [
                '\{namespace f4=F7\Rocks} {namespace f4=TYPO3\Rocks\Really}',
                [],
                null,
                [
                    'f' => 'TYPO3Fluid\Fluid\ViewHelpers',
                    'f4' => 'TYPO3\Rocks\Really'
                ]
            ],

            // old test method: extractNamespaceDefinitionsResolveNamespacesWithDefaultPattern
            [
                '<xml xmlns="http://www.w3.org/1999/xhtml" xmlns:xyz="http://typo3.org/ns/Some/Package/ViewHelpers" />',
                [],
                null,
                [
                    'f' => 'TYPO3Fluid\Fluid\ViewHelpers',
                    'xyz' => 'Some\Package\ViewHelpers'
                ]
            ],

            // old test method: extractNamespaceDefinitionsSilentlySkipsXmlNamespaceDeclarationForTheDefaultFluidNamespace
            [
                '<foo xmlns="http://www.w3.org/1999/xhtml" xmlns:f="http://domain.tld/this/will/be/ignored" />',
                [],
                null,
                [
                    'f' => 'TYPO3Fluid\Fluid\ViewHelpers'
                ]
            ],

            // old test method: extractNamespaceDefinitionsThrowsExceptionIfNamespaceIsRedeclared
            [
                '{namespace typo3=TYPO3\Fluid\Blablubb} {namespace typo3= TYPO3\Rocks\Blu}',
                [],
                '\TYPO3Fluid\Fluid\Core\Parser\Exception'
            ],

            // old test method: extractNamespaceDefinitionsThrowsExceptionIfFluidNamespaceIsRedeclaredAsXmlNamespace
            [
                '{namespace typo3=TYPO3\Fluid\Blablubb} <foo xmlns="http://www.w3.org/1999/xhtml" xmlns:typo3="http://typo3.org/ns/Some/Package/ViewHelpers" />',
                [],
                '\TYPO3Fluid\Fluid\Core\Parser\Exception'
            ],
        ];
    }
}
