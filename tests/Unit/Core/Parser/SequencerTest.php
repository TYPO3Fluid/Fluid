<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\Parser;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use org\bovigo\vfs\vfsStream;
use TYPO3Fluid\Fluid\Component\ComponentInterface;
use TYPO3Fluid\Fluid\Component\ExpressionComponentInterface;
use TYPO3Fluid\Fluid\Core\ErrorHandler\StandardErrorHandler;
use TYPO3Fluid\Fluid\Core\ErrorHandler\TolerantErrorHandler;
use TYPO3Fluid\Fluid\Core\Parser\Configuration;
use TYPO3Fluid\Fluid\Core\Parser\Contexts;
use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Parser\FileSource;
use TYPO3Fluid\Fluid\Core\Parser\Interceptor\Escape;
use TYPO3Fluid\Fluid\Core\Parser\PassthroughSourceException;
use TYPO3Fluid\Fluid\Core\Parser\Sequencer;
use TYPO3Fluid\Fluid\Core\Parser\Source;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ArrayNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EntryNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EscapingNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Parser\Fixtures\ViewHelpers\CViewHelper;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\ViewHelpers\DescriptionViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\Expression\CastViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\Expression\MathViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\Format\PrintfViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\Format\RawViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\HtmlViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\IfViewHelper;
use TYPO3Fluid\Fluid\ViewHelpers\SectionViewHelper;


/**
 * Testcase for SequencedTemplateParser.
 */
class SequencerTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider getErrorExpectations
     * @param string $template
     * @param RenderingContextInterface $context
     * @param int $expectedExceptionCode
     */
    public function failsWithExpectedSequencingExceptionUsingFileSource(string $template, RenderingContextInterface $context, int $expectedExceptionCode)
    {
        $dir = vfsStream::setup('root');
        $file = vfsStream::newFile(sha1($template));
        $dir->addChild($file);
        $file->setContent($template);
        $this->setExpectedException(Exception::class, '', $expectedExceptionCode);
        $context->getTemplateParser()->parse(new FileSource($file->url()));
    }

    /**
     * @test
     * @dataProvider getErrorExpectations
     * @param string $template
     * @param RenderingContextInterface $context
     * @param int $expectedExceptionCode
     */
    public function failsWithExpectedSequencingException(string $template, RenderingContextInterface $context, int $expectedExceptionCode)
    {
        $this->setExpectedException(Exception::class, '', $expectedExceptionCode);
        $context->getTemplateParser()->parse(new Source($template));
    }

    public function getErrorExpectations(): array
    {
        $context = $this->createContext();

        return [
            'Unterminated active tag' => [
                '<div',
                $context,
                1557700786
            ],
            'Unterminated inactive tag' => [
                '<f:example>foo',
                $context,
                1564665730
            ],
            'Unterminated inline syntax' => [
                '{foo',
                $context,
                1557838506
            ],
            'Unexpected token in tag sequencing, inactive tag (EOF, null byte)' => [
                '<foo',
                $context,
                1557700786
            ],
            'Unexpected token in tag sequencing (EOF, null byte)' => [
                '<f:c',
                $context,
                1557700786
            ],
            'Unterminated expression inside quotes' => [
                '<f:c s="',
                $context,
                1557700793
            ],
            'Unterminated array with curly braces in active tag' => [
                '<f:c a="{foo:',
                $context,
                1557838506
            ],
            'Unterminated array with square brackets in active tag' => [
                '<f:c a="[foo:',
                $context,
                1557748574
            ],
            'Unterminated boolean node' => [
                '<f:c b="',
                $context,
                1564159986
            ],
            'Unsupported argument before equals sign in active tag' => [
                '<f:c foo="no" />',
                $context,
                1558298976
            ],
            'Quoted value without a key is not allowed in tags' => [
                '<f:c "foo" />',
                $context,
                1558952412
            ],
            'Mismatched closing tag' => [
                '<f:c></f:comment>',
                $context,
                1557700789
            ],
            'Unresolved ViewHelper as active self-closed tag' => [
                '<f:notfound />',
                $context,
                1407060572
            ],
            'Unresolved ViewHelper as active tag' => [
                '<f:notfound></f:notfound>',
                $context,
                1407060572
            ],
            'Unresolved ViewHelper as inline' => [
                '{f:notfound()}',
                $context,
                1407060572
            ],
            'Invalid inline syntax with Fluid-like symbols' => [
                '{- > foo}',
                $context,
                1558782228
            ],
            'Colon without preceding key' => [
                '{f:c(: 1)}',
                $context,
                1559250839
            ],
            'Equals sign without preceding key' => [
                '{f:c(="1")}',
                $context,
                1559250839
            ],
            'Unsupported argument in ViewHelper as inline' => [
                '{f:c(unsupported: 1)}',
                $context,
                1558298976
            ],
            'Unsupported key-less argument with subsequent valid argument in ViewHelper as inline' => [
                '{f:c(unsupported, s)}',
                $context,
                1558298976
            ],
            'Unsupported key-less argument in ViewHelper as inline' => [
                '{f:c(unsupported)}',
                $context,
                1558298976
            ],
            'Unexpected content before quote start in associative array' => [
                '<f:c a="{foo: {0: BAD"bar"})}',
                $context,
                1559145560
            ],
            'Unexpected content before array/inline start in associative array' => [
                '<f:c a="{foo: {0: BAD{bar: baz}"})}',
                $context,
                1559131849
            ],
            'Unexpected equals sign without preceding argument name or array key' => [
                '<f:c ="foo" />',
                $context,
                1561039838
            ],
            'Unexpected content before array/inline start in numeric array' => [
                '<f:c a="{foo: {0: BAD[bar, baz]}}" />',
                $context,
                1559131849
            ],
            'Unexpected array/inline start in associative array without preceding key' => [
                '<f:c a="{foo: {{foo: bar}: "value"}" />',
                $context,
                1559131848
            ],
            'Invalid cast (confirm expression error pass-through)' => [
                '{foo as invalid}',
                $context,
                1559248372
            ],
            'Unterminated feature toggle' => [
                '{@parsing not terminated',
                $context,
                1563383038
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getInlineWithoutViewHelpersTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesInlineWithoutViewHelpers(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getInlineWithoutViewHelpersTestValues(): array
    {
        $context = $this->createContext();
        return [

            /* INLINE */
            'simple inline in root context' => [
                '{foo}',
                $context,
                false,
                (new EntryNode())->addChild(new ObjectAccessorNode('foo')),
            ],
            'inline namespace without at-sign prefix' => [
                '{namespace foo=Bar}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('')),
            ],
            'accessor with dynamic part last in inline in root context' => [
                '{foo.{bar}}',
                $context,
                false,
                (new EntryNode())->addChild((new ObjectAccessorNode())->addChild(new TextNode('foo.'))->addChild(new ObjectAccessorNode('bar'))),
            ],
            'accessor with dynamic part with dot in middle in inline in root context' => [
                '{foo.{sub.bar}.baz}',
                $context,
                false,
                (new EntryNode())->addChild((new ObjectAccessorNode())->addChild(new TextNode('foo.'))->addChild(new ObjectAccessorNode('sub.bar'))->addChild(new TextNode('.baz'))),
            ],
            'simple inline with text before in root context' => [
                'before {foo}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('before '))->addChild(new ObjectAccessorNode('foo')),
            ],
            'simple inline with text after in root context' => [
                '{foo} after',
                $context,
                false,
                (new EntryNode())->addChild(new ObjectAccessorNode('foo'))->addChild(new TextNode(' after')),
            ],
            'simple inline with text before and after in root context' => [
                'before {foo} after',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('before '))->addChild(new ObjectAccessorNode('foo'))->addChild(new TextNode(' after')),
            ],
            'escaped inline with text before and after in root context' => [
                'before \\{foo} after',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('before {foo} after')),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getInlineWithViewHelpersTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesInlineWithViewHelpers(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getInlineWithViewHelpersTestValues(): array
    {
        $context = $this->createContext();
        return [

            'simple inline ViewHelper without arguments in root context' => [
                '{f:c()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class)),
            ],
            'simple inline ViewHelper with single hardcoded integer argument in root context' => [
                '{f:c(i: 1)}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => 1])),
            ],
            'simple inline ViewHelper with single hardcoded integer argument using tag attribute syntax in root context' => [
                '{f:c(i="1")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => 1])),
            ],
            'simple inline ViewHelper with single value-less hardcoded integer argument using tag attribute syntax in root context' => [
                '{f:c(i)}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => new ObjectAccessorNode('i')])),
            ],
            'simple inline ViewHelper with two comma separated value-less hardcoded integer argument in root context' => [
                '{f:c(i, s)}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => new ObjectAccessorNode('i'), 's' => new ObjectAccessorNode('s')])),
            ],
            'simple inline ViewHelper with two hardcoded arguments using tag attribute syntax without commas in root context' => [
                '{f:c(i="1" s="foo")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => 1, 's' => 'foo'])),
            ],
            'simple inline ViewHelper with two hardcoded arguments using tag attribute syntax with comma in root context' => [
                '{f:c(i="1", s="foo")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => 1, 's' => 'foo'])),
            ],
            'simple inline ViewHelper with two hardcoded arguments using tag attribute syntax with comma and tailing comma in root context' => [
                '{f:c(i="1", s="foo",)}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['i' => 1, 's' => 'foo'])),
            ],
            'simple inline ViewHelper with square brackets array argument using tag attribute syntax and array value using tag attribute syntax in root context' => [
                '{f:c(a="[foo="bar"]")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 'bar'])])),
            ],
            'simple inline ViewHelper with square brackets array argument with implied numeric keys with comma in root context' => [
                '{f:c(a="[foo, bar]")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([new ObjectAccessorNode('foo'), new ObjectAccessorNode('bar')])])),
            ],
            'simple inline ViewHelper with square brackets array argument with two items using implied numeric keys with quoted values using comma in root context' => [
                '{f:c(a="["foo", "bar"]")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo', 'bar'])])),
            ],
            'simple inline ViewHelper with curly braces array argument with explicit numeric keys in root context' => [
                '{f:c(a="{0: foo, 1: bar}")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([new ObjectAccessorNode('foo'), new ObjectAccessorNode('bar')])])),
            ],
            'simple inline ViewHelper with curly braces array argument with redundant escapes in root context' => [
                '{f:c(a: {0: \\\'foo\\\'})}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo'])])),
            ],
            'simple inline ViewHelper with curly braces array argument with incorrect number of redundant escapes in root context' => [
                '{f:c(a: \'{0: \\\\\'{f:c(a: {foo})}\\\\\'}\')}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([$this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => new ObjectAccessorNode('foo')])])])])),
            ],
            'simple inline ViewHelper with curly brackets array argument using tag attribute syntax and array value using tag attribute syntax in root context' => [
                '{f:c(a="{foo="bar"}")}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 'bar'])])),
            ],
            'simple inline ViewHelper with value pipe in root context' => [
                '{string | f:c()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class)->addChild(new ObjectAccessorNode('string'))),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getInactiveTagsTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesInactiveTags(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getInactiveTagsTestValues(): array
    {
        $context = $this->createContext();
        return [

            /* TAGS */
            'simple open+close non-active tag in root context' => [
                '<tag>x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag>x</tag>')),
            ],
            'simple open+close non-active tag with hardcoded attribute in root context' => [
                '<tag attr="foo">x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag attr="foo">x</tag>')),
            ],
            'simple open+close non-active tag with object accessor attribute value in root context' => [
                '<tag attr="{string}">x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag attr="'))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('">x</tag>')),
            ],
            'simple open+close non-active tag with value-less object accessor attribute in root context' => [
                '<tag {string}>x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('>x</tag>')),
            ],
            'simple open+close non-active tag with object accessor attribute name in root context' => [
                '<tag {string}="foo">x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('="foo">x</tag>')),
            ],
            'simple open+close non-active tag with object accessor attribute name and value in root context' => [
                '<tag {string}="{string}">x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('="'))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('">x</tag>')),
            ],
            'simple open+close non-active tag with unquoted object accessor attribute name and value in root context' => [
                '<tag {string}={string}>x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('='))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('>x</tag>')),
            ],
            'simple open+close non-active tag with unquoted object accessor attribute name and static value in root context' => [
                '<tag {string}=1>x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('=1>x</tag>')),
            ],
            'simple open+close non-active tag with value-less unquoted object accessor attribute name in root context' => [
                '<tag {string}>x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode('>x</tag>')),
            ],
            'simple tag with inline ViewHelper and legacy pass to other ViewHelper as string argument value' => [
                '<tag s="{f:c() -> f:c()}">x</tag>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag s="'))->addChild($this->createViewHelper($context, CViewHelper::class)->addChild($this->createViewHelper($context, CViewHelper::class)))->addChild(new TextNode('">x</tag>')),
            ],
            'self-closed non-active, two value-less static attributes' => [
                '<tag static1 static2 />',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag static1 static2 />')),
            ],
            'self-closed non-active, space before closing tag, tag in root context' => [
                '<tag />',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag />')),
            ],
            'self-closed non-active, no space before closing tag, tag in root context' => [
                '<tag/>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag/>')),
            ],
            'self-closed non-active, dynamic tag name, no space before closing tag, tag in root context' => [
                '<{tag}/>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<'))->addChild(new ObjectAccessorNode('tag'))->addChild(new TextNode('/>')),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getActiveTagsTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesActiveTags(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getActiveTagsTestValues(): array
    {
        $context = $this->createContext();
        return [

            'simple open+close active tag in root context' => [
                '<f:c>x</f:c>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, [], [new TextNode('x')])),
            ],
            'simple open+close active tag with string parameter in root context' => [
                '<f:c s="foo">x</f:c>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'foo'])->addChild(new TextNode('x'))),
            ],
            'self-closed active tag, space before tag close, in root context' => [
                '<f:c />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class)),
            ],
            'self-closed active tag, no space before tag close, in root context' => [
                '<f:c/>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class)),
            ],
            'self-closed active tag with hardcoded string argument' => [
                '<f:c s="foo" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'foo'])),
            ],
            'self-closed active tag with hardcoded string argument with backslash that must not be removed/ignored' => [
                '<f:c s="foo\\bar" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'foo\\bar'])),
            ],
            'self-closed active tag with hardcoded string argument with multiple backslashes that must not be removed/ignored' => [
                '<f:c s="foo\\bar\\\\baz" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'foo\\bar\\\\baz'])),
            ],
            'self-closed active tag with hardcoded string and integer arguments' => [
                '<f:c s="foo" i="1" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'foo', 'i' => 1])),
            ],
            'self-closed active tag with object accessor string argument in string argument' => [
                '<f:c s="{string}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => new ObjectAccessorNode('string')])),
            ],
            'self-closed active tag with object accessor with sub-variable at end in string argument' => [
                '<f:c s="{string.{sub}}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => (new ObjectAccessorNode())->addChild(new TextNode('string.'))->addChild(new ObjectAccessorNode('sub'))])),
            ],
            'self-closed active tag with object accessor string argument with string before accessor in string argument' => [
                '<f:c s="before {string}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => (new RootNode())->addChild(new TextNode('before '))->addChild(new ObjectAccessorNode('string'))])),
            ],
            'self-closed active tag with object accessor string argument with string after accessor in string argument' => [
                '<f:c s="{string} after" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => (new RootNode())->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode(' after'))])),
            ],
            'self-closed active tag with object accessor string argument with string before and after accessor in string argument' => [
                '<f:c s="before {string} after" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => (new RootNode())->addChild(new TextNode('before '))->addChild(new ObjectAccessorNode('string'))->addChild(new TextNode(' after'))])),
            ],
            'self-closed active tag with string argument containing different quotes inside in string argument' => [
                '<f:c s="before \'quoted\' after" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'before \'quoted\' after'])),
            ],
            'self-closed active tag with string argument containing escaped same type quotes inside in string argument' => [
                '<f:c s="before \\"quoted\\" after" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'before "quoted" after'])),
            ],
            'self-closed active tag with array argument with single unquoted element in array argument' => [
                '<f:c a="{foo:bar}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => new ObjectAccessorNode('bar')])])),
            ],
            'self-closed active tag with array argument with two unquoted elements with comma and space in array argument' => [
                '<f:c a="{foo:bar, baz:honk}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => new ObjectAccessorNode('bar'), 'baz' => new ObjectAccessorNode('honk')])])),
            ],
            'self-closed active tag with array argument with two unquoted elements with comma without space in array argument' => [
                '<f:c a="{foo:bar,baz:honk}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => new ObjectAccessorNode('bar'), 'baz' => new ObjectAccessorNode('honk')])])),
            ],
            'self-closed active tag with array argument with single double-quoted element (no escaping) in array argument' => [
                '<f:c a="{foo:"bar"}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 'bar'])])),
            ],
            'self-closed active tag with array argument with single single-quoted element (no escaping) in array argument' => [
                '<f:c a="{foo:\'bar\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 'bar'])])),
            ],
            'self-closed active tag with array argument with single integer element in array argument' => [
                '<f:c a="{foo:1}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 1])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with quoted string values in array argument' => [
                '<f:c a="{1: \'foo\', 2: \'bar\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => 'foo', 2 => 'bar'])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with quoted integer values in array argument' => [
                '<f:c a="{1: \'0\', 2: \'1\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => 0, 2 => 1])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with quoted inline legacy pass without spaces as array argument' => [
                '<f:c a="{1: \'{foo->f:c()}\', 2: \'1\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => $this->createViewHelper($context, CViewHelper::class)->addChild(new ObjectAccessorNode('foo')), 2 => 1])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with quoted inline legacy pass with spaces as array argument' => [
                '<f:c a="{1: \'{foo -> f:c()}\', 2: \'1\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => $this->createViewHelper($context, CViewHelper::class)->addChild(new ObjectAccessorNode('foo')), 2 => 1])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with quoted inline pipe pass without spaces as array argument' => [
                '<f:c a="{1: \'{foo|f:c()}\', 2: \'1\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => $this->createViewHelper($context, CViewHelper::class)->addChild(new ObjectAccessorNode('foo')), 2 => 1])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with quoted inline pipe pass with spaces as array argument' => [
                '<f:c a="{1: \'{foo | f:c()}\', 2: \'1\'}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => $this->createViewHelper($context, CViewHelper::class)->addChild(new ObjectAccessorNode('foo')), 2 => 1])])),
            ],
            'self-closed active tag with array argument with numeric non-sequential keys with object accessor values in array argument' => [
                '<f:c a="{1: foo.bar, 2: baz.buzz}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([1 => new ObjectAccessorNode('foo.bar'), 2 => new ObjectAccessorNode('baz.buzz')])])),
            ],
            'self-closed active tag with array argument with double-quoted key and single integer element in array argument' => [
                '<f:c a="{"foo":1}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 1])])),
            ],
            'self-closed active tag with array argument with square brackets and single double-quoted string element in array argument' => [
                '<f:c a="["foo"]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo'])])),
            ],
            'self-closed active tag with array argument with square brackets and single double-quoted object accessor element in array argument' => [
                '<f:c a="["{foo}"]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([new ObjectAccessorNode('foo')])])),
            ],
            'self-closed active tag with array argument with square brackets and two-element ECMA literal array in array argument' => [
                '<f:c a="[{foo, bar}]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([new ArrayNode(['foo' => new ObjectAccessorNode('foo'), 'bar' => new ObjectAccessorNode('bar')])])])),
            ],
            'self-closed active tag with square brackets array argument with double-quoted key and single integer element in root context' => [
                '<f:c a="["foo":1]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => 1])])),
            ],
            'self-closed active tag with square brackets array argument with sub-array element with quoted key and object accessor value in root context' => [
                '<f:c a="[foo:[baz:\'foo\']]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => new ArrayNode(['baz' => 'foo'])])])),
            ],
            'self-closed active tag with square brackets array argument with sub-array element with escaped and quoted key and object accessor value in root context' => [
                '<f:c a="[foo:[baz:\\\'foo\\\']]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode(['foo' => new ArrayNode(['baz' => 'foo'])])])),
            ],
            'self-closed active tag with square brackets array argument with multiple sub-array values in root context' => [
                '<f:c a="[["foo", "bar"], ["baz", "buzz"]]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['a' => new ArrayNode([new ArrayNode(['foo', 'bar']), new ArrayNode(['baz', 'buzz'])])])),
            ],
            'self-closed active tag with string argument with square bracket start not at first position in root context' => [
                '<f:c s="my [a:b]" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => 'my [a:b]'])),
            ],
            'self-closed active tag with value-less ECMA literal shorthand argument in root context' => [
                '<f:c s/>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => new ObjectAccessorNode('s')])),
            ],
            'self-closed active tag with two value-less ECMA literal shorthand arguments in root context' => [
                '<f:c s i/>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => new ObjectAccessorNode('s'), 'i' => new ObjectAccessorNode('i')])),
            ],
            'simple open + close active tag with value-less ECMA literal shorthand argument in root context' => [
                '<f:c s>x</f:c>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => new ObjectAccessorNode('s')], [new TextNode('x')])),
            ],
            'simple open + close active tag with two value-less ECMA literal shorthand arguments in root context' => [
                '<f:c s i>x</f:c>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['s' => new ObjectAccessorNode('s'), 'i' => new ObjectAccessorNode('i')], [new TextNode('x')])),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getInlineArrayPassTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesInlineArrayPass(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getInlineArrayPassTestValues(): array
    {
        $context = $this->createContext();
        return [

            /* INLINE PASS OF ARRAY */
            'inline ViewHelper with single-item quoted static key and value associative array in square brackets piped value in root context' => [
                '{["foo": "bar"] | f:format.raw()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ArrayNode(['foo' => 'bar'])])),
            ],
            'inline ViewHelper with single-item implied numeric index array in square brackets piped value in root context' => [
                '{["bar"] | f:format.raw()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ArrayNode(['bar'])])),
            ],
            'inline ViewHelper with multi-item implied numeric index array in square brackets piped value in root context' => [
                '{["foo", "bar", "baz"] | f:format.raw()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ArrayNode(['foo', 'bar', 'baz'])])),
            ],
            'inline ViewHelper with multi-item implied numeric index array with tailing comma in square brackets piped value in root context' => [
                '{["foo", "bar", "baz", ] | f:format.raw()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ArrayNode(['foo', 'bar', 'baz'])])),
            ],
            'inline ViewHelper with multi-item associative index array with space-separated key and value pairs in root context' => [
                '{[foo "foo", bar "bar"] | f:format.raw()}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ArrayNode(['foo' => 'foo', 'bar' => 'bar'])])),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getViewHelperAliasTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesViewHelperAliases(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getViewHelperAliasTestValues(): array
    {
        $context = $this->createContext();
        return [
            'simple inline ViewHelper with value pipe to ViewHelper alias in root context' => [
                '{string | raw}',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ObjectAccessorNode('string')])),
            ],
            'simple tag ViewHelper with tag content being ViewHelper alias' => [
                '<raw>{string}</raw>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, [], [new ObjectAccessorNode('string')])),
            ],
            'simple tag ViewHelper with tag argument being ViewHelper alias' => [
                '<raw value="{string}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, RawViewHelper::class, ['value' => new ObjectAccessorNode('string')])),
            ],
            'aliased ViewHelper supports namespaced attributes' => [
                '<html foo:bar="string">test</html>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, HtmlViewHelper::class, ['foo:bar' => 'string'], [new TextNode('test')])),
            ],
            'html pseudo ViewHelper supports namespace registration' => [
                '<html xmlns:foo="http://typo3.org/ns/Foo/Bar/ViewHelpers">test</html>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, HtmlViewHelper::class, ['xmlns:foo' => 'http://typo3.org/ns/Foo/Bar/ViewHelpers'])->addChild(new TextNode('test'))),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getSequenceExpectations
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function createsExpectedNodeStructure(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getSequenceExpectations(): array
    {
        $context = $this->createContext();

        return [

            /* EMPTY */
            'empty source' => [
                '',
                $context,
                false,
                new EntryNode()
            ],

            /*
            'simple inline ViewHelper with multiple values pipe in root context' => [
                '{{string}{string}{string} | f:c()}',
                $context,
                false,
                (new RootNode())->addChild((new CViewHelper())->onOpen([], $state)->addChild(new ObjectAccessorNode('string'))->addChild(new ObjectAccessorNode('string'))->addChild(new ObjectAccessorNode('string'))),
            ],
            */

            /* PROTECTED INLINE */
            'inline syntax with explicitly escaped sub-syntax creates explicit accessor' => [
                '{foo.\{bar}}',
                $context,
                false,
                (new EntryNode())->addChild(new ObjectAccessorNode('foo.{bar}')),
            ],
            'inline syntax with comma when array is not allowed is detected as possible expression that becomes TextNode' => [
                '{foo, bar, baz}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{foo, bar, baz}')),
            ],
            'complex inline syntax not detected as Fluid in root context' => [
                '{string - with < raw || something}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{string - with < raw || something}')),
            ],
            'complex inline syntax with sub-accessor-like syntax not detected as Fluid in root context' => [
                '{string with < raw {notFluid} || something}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{string with < raw {notFluid} || something}')),
            ],
            'likely JS syntax not detected as Fluid in root context' => [
                '{"object": "something"}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{"object": "something"}')),
            ],
            'likely CSS syntax not detected as Fluid in root context' => [
                'something { background-color: #000000; }',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('something { background-color: #000000; }')),
            ],
            'likely JS syntax not detected as Fluid in inactive attribute value' => [
                '<tag prop="if(x.y[f.z].v){w.l.h=(this.o[this.s].v);}" />',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<tag prop="if(x.y[f.z].v){w.l.h=(this.o[this.s].v);}" />')),
            ],
            'empty inline renders as plaintext curly brace pair' => [
                '{}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{}'))
            ],

            /* BACKTICK EXPLICIT VARIABLES */
            'backtick quotes in protected inline is detected as correct object accessor' => [
                'something { background-color: `{color}`; }',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('something { background-color: '))->addChild(new ObjectAccessorNode('color'))->addChild(new TextNode('; }')),
            ],

            /* EXPRESSIONS */
            'inline that might be an expression node' => [
                '{inline something foo}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{inline something foo}'))
            ],
            'inline that might be an expression node with parenthesis' => [
                '{inline (something) foo}',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('{inline (something) foo}'))
            ],
            'inline math expression with spaces is detected as expression' => [
                '{i + 1}',
                $context,
                false,
                (new EntryNode())->addChild(new MathViewHelper(['i', '+', '1']))
            ],
            'inline math expression without spaces is not detected as expression' => [
                '{i+1}',
                $context,
                false,
                (new EntryNode())->addChild(new ObjectAccessorNode('i+1'))
            ],
            'casting expression is recognized as expression' => [
                '{i as string}',
                $context,
                false,
                (new EntryNode())->addChild(new CastViewHelper(['i', 'as', 'string']))
            ],
            'expression error with tolerant error handler is created as TextNode' => [
                '{i as invalid}',
                $this->createContext(TolerantErrorHandler::class),
                false,
                (new EntryNode())->addChild(new TextNode('Invalid expression: Invalid target conversion type "invalid" specified in casting expression "{i as invalid}".'))
            ],

            /* CDATA and PCDATA */
            'cdata node becomes text node without parsing content' => [
                '<![CDATA[{notparsed}]]>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<![CDATA[{notparsed}]]>'))
            ],
            'cdata node with unmatched closing square brackets inside becomes text node without parsing content' => [
                '<![CDATA[{not [] parsed}]]>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<![CDATA[{not [] parsed}]]>'))
            ],
            'pcdata node becomes text node without parsing content' => [
                '<![PCDATA[{notparsed}]]>',
                $context,
                false,
                (new EntryNode())->addChild(new TextNode('<![PCDATA[{notparsed}]]>'))
            ],

            /* STRUCTURAL */
            'section with name' => [
                '<f:section name="Default">DefaultSection</f:section>',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, SectionViewHelper::class, ['name' => 'Default'])->addChild(new TextNode('DefaultSection'))),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getBooleanNodeTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    public function sequencesBooleanNodes(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $this->performSequencerAssertions($template, $context, $escapingEnabled, $expectedRootNode);
    }

    public function getBooleanNodeTestValues(): array
    {
        $context = $this->createContext();
        return [
            'simple numeric boolean true value' => [
                '<f:c b="1" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => true])),
            ],
            'simple numeric boolean false value' => [
                '<f:c b="0" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => false])),
            ],
            'single object accessor boolean value' => [
                '<f:c b="{foo}" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new ObjectAccessorNode('foo'))])),
            ],
            'expression boolean value split to parts' => [
                '<f:c b="1 == 1" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('1'))->addChild(new TextNode('=='))->addChild(new TextNode('1'))])),
            ],
            'expression boolean value split to parts with tab whitespace' => [
                "<f:c b=\"1\t==\t1\" />",
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('1'))->addChild(new TextNode('=='))->addChild(new TextNode('1'))])),
            ],
            'expression boolean value split to parts with carriage return whitespace' => [
                "<f:c b=\"1\r==\r1\" />",
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('1'))->addChild(new TextNode('=='))->addChild(new TextNode('1'))])),
            ],
            'expression boolean value split to parts with line feed whitespace' => [
                "<f:c b=\"1\n==\n1\" />",
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('1'))->addChild(new TextNode('=='))->addChild(new TextNode('1'))])),
            ],
            'expression boolean value split to parts with line feed and carriage return whitespace' => [
                "<f:c b=\"1\r\n==\r\n1\" />",
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('1'))->addChild(new TextNode('=='))->addChild(new TextNode('1'))])),
            ],
            'string comparison with escaped quote outside string' => [
                '<f:c b="1 == \\\'test\\\'" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('1'))->addChild(new TextNode('=='))->addChild((new RootNode())->setQuoted(true)->addChild(new TextNode('test')))])),
            ],
            'object accessor with comparison boolean value' => [
                '<f:c b="{foo} === 1" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new ObjectAccessorNode('foo'))->addChild(new TextNode('==='))->addChild(new TextNode('1'))])),
            ],
            'quoted string comparison' => [
                '<f:c b="\'foo\' == \'bar\'" />',
                $context,
                false,
                (new EntryNode())->addChild(
                    $this->createViewHelper(
                        $context,
                        CViewHelper::class,
                        [
                            'b' => (new BooleanNode())
                                ->addChild((new RootNode())
                                    ->setQuoted(true)
                                    ->addChild(new TextNode('foo'))
                                )->addChild(new TextNode('=='))
                                ->addChild((new RootNode())
                                    ->setQuoted(true)
                                    ->addChild(new TextNode('bar'))
                                )
                        ]
                    )
                ),
            ],
            'object accessor with comparison boolean value and && combination' => [
                '<f:c b="{foo} === 1 && true" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new ObjectAccessorNode('foo'))->addChild(new TextNode('==='))->addChild(new TextNode('1'))->addChild(new TextNode('&&'))->addChild(new TextNode('true'))])),
            ],
            'multiple groupings' => [
                '<f:c b="0 || 1 && 0" />',
                $context,
                false,
                (new EntryNode())->addChild($this->createViewHelper($context, CViewHelper::class, ['b' => (new BooleanNode())->addChild(new TextNode('0'))->addChild(new TextNode('||'))->addChild(new TextNode('1'))->addChild(new TextNode('&&'))->addChild(new TextNode('0'))])),
            ],
            'multiple groupings and comparisons' => [
                '<f:c b="0 || (\'foo\' == \'foo\')" />',
                $context,
                false,
                (new EntryNode())->addChild(
                    $this->createViewHelper(
                        $context,
                        CViewHelper::class,
                        [
                            'b' => (new BooleanNode())
                                ->addChild(new TextNode('0'))
                                ->addChild(new TextNode('||'))
                                ->addChild(
                                    (new BooleanNode())
                                        ->addChild((new RootNode())->setQuoted(true)->addChild(new TextNode('foo')))
                                        ->addChild(new TextNode('=='))
                                        ->addChild((new RootNode())->setQuoted(true)->addChild(new TextNode('foo')))
                                )
                        ]
                    )
                ),
            ],
            'grouped expressions become nested BooleanNode' => [
                '<f:c b="({foo} != 1) && ({foo} != 2)" />',
                $context,
                false,
                (new EntryNode())->addChild(
                    $this->createViewHelper(
                        $context,
                        CViewHelper::class,
                        [
                            'b' => (new BooleanNode())
                                ->addChild(
                                    (new BooleanNode())
                                        ->addChild(new ObjectAccessorNode('foo'))
                                        ->addChild(new TextNode('!='))
                                        ->addChild(new TextNode('1'))
                                )->addChild(new TextNode('&&'))
                                ->addChild(
                                    (new BooleanNode())
                                        ->addChild(new ObjectAccessorNode('foo'))
                                        ->addChild(new TextNode('!='))
                                        ->addChild(new TextNode('2'))
                                )
                        ]
                    )
                ),
            ],
            'multiple grouped expressions become nested BooleanNode' => [
                '<f:c b="(1 != 2) && (1 != 3) && (1 != 4) && 1" />',
                $context,
                false,
                (new EntryNode())->addChild(
                    $this->createViewHelper(
                        $context,
                        CViewHelper::class,
                        [
                            'b' => (new BooleanNode())
                                ->addChild(
                                    (new BooleanNode())
                                        ->addChild(new TextNode('1'))
                                        ->addChild(new TextNode('!='))
                                        ->addChild(new TextNode('2'))
                                )->addChild(new TextNode('&&'))
                                ->addChild(
                                    (new BooleanNode())
                                        ->addChild(new TextNode('1'))
                                        ->addChild(new TextNode('!='))
                                        ->addChild(new TextNode('3'))
                                )->addChild(new TextNode('&&'))
                                ->addChild(
                                    (new BooleanNode())
                                        ->addChild(new TextNode('1'))
                                        ->addChild(new TextNode('!='))
                                        ->addChild(new TextNode('4'))
                                )->addChild(new TextNode('&&')
                                )->addChild(new TextNode('1')
                            )
                        ]
                    )
                ),
            ],
        ];
    }

    /**
     * @test
     */
    public function complexGroupedConditionParsesCorrectly(): void
    {
        $expression = '(1 && (\'foo\' == \'foo\') && (TRUE || 1)) && 0 != 1 && FALSE';
        $context = $this->createContext();
        $sequencer = new Sequencer(
            $context,
            new Contexts(),
            new Source('<f:if condition="' . $expression . '" />')
        );
        $result = $sequencer->sequence();

        $firstInnerGroup = (new BooleanNode())
            ->addChild((new RootNode())->setQuoted(true)->addChild(new TextNode('foo')))
            ->addChild(new TextNode('=='))
            ->addChild((new RootNode())->setQuoted(true)->addChild(new TextNode('foo')));
        $secondInnerGroup = (new BooleanNode())
            ->addChild(new TextNode('TRUE'))
            ->addChild(new TextNode('||'))
            ->addChild(new TextNode('1'));
        $firstGroup = (new BooleanNode())
            ->addChild(new TextNode('1'))
            ->addChild(new TextNode('&&'))
            ->addChild($firstInnerGroup)
            ->addChild(new TextNode('&&'))
            ->addChild($secondInnerGroup);

        $expected = (new EntryNode())
            ->addChild(
                $this->createViewHelper(
                    $context,
                    IfViewHelper::class,
                    [
                        'condition' => (new BooleanNode())->addChild($firstGroup)
                            ->addChild(new TextNode('&&'))
                            ->addChild(new TextNode('0'))
                            ->addChild(new TextNode('!='))
                            ->addChild(new TextNode('1'))
                            ->addChild(new TextNode('&&'))
                            ->addChild(new TextNode('FALSE'))
                    ]
                )
        );
        $this->assertNodeEquals($result, $expected);
    }

    /**
     * @test
     */
    public function conditionWithNestedInlineViewHelperParsesCorrectly(): void
    {
        $expression = '(TRUE && ({f:if(condition: \'TRUE\', then: \'1\')} == 1))';
        $context = $this->createContext();
        $sequencer = new Sequencer(
            $context,
            new Contexts(),
            new Source('<f:if condition="' . $expression . '" />')
        );
        $result = $sequencer->sequence();

        $firstInnerGroup = $this->createViewHelper(
            $context,
            IfViewHelper::class,
            [
                'condition' => true,
                'then' => 1
            ]
        );
        $firstGroup = (new BooleanNode())
            ->addChild(new TextNode('TRUE'))
            ->addChild(new TextNode('&&'))
            ->addChild((new BooleanNode())->addChild($firstInnerGroup)->addChild(new TextNode('=='))->addChild(new TextNode('1'))
        );

        $expected = (new EntryNode())
            ->addChild(
                $this->createViewHelper(
                    $context,
                    IfViewHelper::class,
                    [
                        'condition' => (new BooleanNode())->addChild($firstGroup)
                    ]
                )
        );
        $this->assertNodeEquals($result, $expected);
    }

    /**
     * @test
     */
    public function arrayComparisonConditionParsesCorrectly(): void
    {
        $expression = '{someArray} == {foo: \'bar\'}';
        $context = $this->createContext();
        $sequencer = new Sequencer(
            $context,
            new Contexts(),
            new Source('<f:if condition="' . $expression . '" />')
        );
        $result = $sequencer->sequence();

        $expected = (new EntryNode())
            ->addChild(
                $this->createViewHelper(
                    $context,
                    IfViewHelper::class,
                    [
                        'condition' => (new BooleanNode())
                            ->addChild(new ObjectAccessorNode('someArray'))
                            ->addChild(new TextNode('=='))
                            ->addChild(new ArrayNode(['foo' => 'bar']))
                    ]
                )
            )
            ;
        $this->assertNodeEquals($result, $expected);
    }

    /**
     * @test
     */
    public function nestedConditionInConditionParsesCorrectly(): void
    {
        $expression = '{f:if(condition: \'{var}\', else: \'1\')}';
        $context = new RenderingContextFixture();
        $context->setVariableProvider(new StandardVariableProvider(['var' => new \SplObjectStorage()]));

        $sequencer = new Sequencer(
            $context,
            new Contexts(),
            new Source('<f:if condition="' . $expression . '" then="yes" else="no" />')
        );
        $result = $sequencer->sequence();

        $nestedConditionArgument = (new BooleanNode())->addChild(new ObjectAccessorNode('var'));
        $nestedViewHelper = $this->createViewHelper($context, IfViewHelper::class, ['condition' => $nestedConditionArgument, 'else' => 1]);

        $expected = (new EntryNode())
            ->addChild(
                $this->createViewHelper(
                    $context,
                    IfViewHelper::class,
                    [
                        'then' => 'yes',
                        'else' => 'no',
                        'condition' => (new BooleanNode())->addChild($nestedViewHelper)
                    ]
                )
            );
        $this->assertNodeEquals($expected, $result);
    }

    /**
     * @test
     */
    public function featureToggleParsingOffThrowsPassthroughException()
    {
        $configuration = $this->getMockBuilder(Configuration::class)->getMock();
        $configuration->method('getInterceptors')->willReturn([]);
        $sequencer = new Sequencer(
            $this->createContext(),
            new Contexts(),
            new Source('{@parsing off}'),
            $configuration
        );
        $this->setExpectedException(PassthroughSourceException::class);
        $sequencer->sequence();
    }

    /**
     * @test
     */
    public function featureToggleEscapingOffSetsFeatureStateAvoidsOutput()
    {
        $configuration = $this->getMockBuilder(Configuration::class)->setMethods(['setFeatureState', 'getInterceptors'])->getMock();
        $configuration->method('getInterceptors')->willReturn([]);
        $sequencer = new Sequencer(
            $this->createContext(),
            new Contexts(),
            new Source('{@escaping off} kept text'),
            $configuration
        );
        $configuration->expects($this->once())->method('setFeatureState')->with(Configuration::FEATURE_ESCAPING, 'off');
        $state = $sequencer->sequence();
        $this->assertEquals(' kept text', $state->flatten(true));
    }

    /**
     * @test
     */
    public function inactiveTagSequencingTreatsAllContentAsPureText(): void
    {
        $context = $this->createContext();
        $sequencer = new Sequencer(
            $this->createContext(),
            new Contexts(),
            new Source('<f:description> text and <f:render /> <f:description>test</f:description> </f:description> outside')
        );
        $expectedNode = (new EntryNode());
        $expectedNode->addChild($this->createViewHelper($context, DescriptionViewHelper::class, [], [new TextNode(' text and <f:render /> <f:description>test</f:description> ')]));
        $expectedNode->addChild(new TextNode(' outside'));
        $node = $sequencer->sequence();
        $this->assertNodeEquals($node, $expectedNode);
    }

    /**
     * @test
     */
    public function stressTestOneThousandArrayItems()
    {
        $context = $this->createContext();
        $thousandRandomArrayItemsInline = '{f:c(a: {';
        $thousandRandomArray = [];
        $chars = str_split('abcdef1234567890');
        $createRandomString = function (int $length) use ($chars): string {
            $string = '';
            for ($i = 0; $i < $length; $i++) {
                $string .= array_rand($chars);
            }
            return $string;
        };
        for ($i = 0; $i < 1000; $i++) {
            $key = 'k' . $createRandomString(rand(16, 32));
            $value = 'v' . $createRandomString(rand(16, 32));
            $thousandRandomArrayItemsInline .= $key . ': "' . $value . '", ';
            $thousandRandomArray[$key] = $value;
        }
        $thousandRandomArrayItemsInline .= '})}';
        $viewHelper = new CViewHelper();
        $viewHelper->getArguments()->assignAll(['a' => new ArrayNode($thousandRandomArray)]);
        $expectedRootNode = (new EntryNode())->addChild($viewHelper->onOpen($context));
        $this->createsExpectedNodeStructure($thousandRandomArrayItemsInline, $context, false, $expectedRootNode);
    }

    /**
     * @test
     */
    public function stressTestFiftyInlinePasses()
    {
        $context = $this->createContext();
        $template = '{foo ';

        $expectedRootNode = new EntryNode();
        $node = $expectedRootNode;
        for ($i = 0; $i < 50; $i++) {
            $childNode = $this->createViewHelper($context, RawViewHelper::class);
            $node->addChild($childNode);
            $template .= '| f:format.raw() ';
            $node = $childNode;
        }
        $node->addChild(new ObjectAccessorNode('foo'));
        $template .= '}';

        $this->createsExpectedNodeStructure($template, $context, false, $expectedRootNode);
    }

    /**
     * @test
     * @dataProvider getEscapingTestValues
     * @param string $template
     * @param RenderingContextInterface $context
     * @param EntryNode $expectedRootNode
     */
    public function escapingTest(string $template, RenderingContextInterface $context, EntryNode $expectedRootNode)
    {
        $this->createsExpectedNodeStructure($template, $context, true, $expectedRootNode);
    }

    public function getEscapingTestValues(): array
    {
        $context = $this->createContext();
        return [
            'escapes object accessors' => [
                '{foo}',
                $context,
                (new EntryNode())->addChild((new EscapingNode(new ObjectAccessorNode('foo')))),
            ],
            'escapes expressions' => [
                '{foo as string}',
                $context,
                (new EntryNode())->addChild((new EscapingNode(new CastViewHelper(['foo', 'as', 'string'])))),
            ],
            'escapes self-closing ViewHelper' => [
                '<f:format.printf value="%s" arguments="["foo"]" />',
                $context,
                (new EntryNode())->addChild(
                    new EscapingNode(
                        $this->createViewHelper(
                            $context,
                            PrintfViewHelper::class,
                            ['value' => '%s', 'arguments' => new ArrayNode(['foo'])]
                        )
                    )
                ),
            ],
            'escapes open-and-closing ViewHelper' => [
                '<f:format.printf arguments="["foo"]">%s</f:format.printf>',
                $context,
                (new EntryNode())->addChild(
                    new EscapingNode(
                        $this->createViewHelper(
                            $context,
                            PrintfViewHelper::class,
                            ['arguments' => new ArrayNode(['foo'])],
                            [new TextNode('%s')]
                        )
                    )
                ),
            ],
        ];
    }

    /**
     * @test
     */
    public function togglingParsingOffSequencesRemainderAsText(): void
    {
        $template = '{@parsing off}' . PHP_EOL . '<f:format.raw>{value}</f:format.raw>';
        $context = $this->createContext();
        $this->setExpectedException(PassthroughSourceException::class);
        $this->createsExpectedNodeStructure($template, $context, true, new EntryNode());
    }

    protected function createViewHelper(RenderingContextInterface $context, string $viewHelperClassName, array $arguments = [], iterable $children = []): ComponentInterface
    {
        /** @var ComponentInterface $instance */
        $instance = new $viewHelperClassName();
        $instance->getArguments()->assignAll($arguments);
        $instance->onOpen($context);
        foreach ($children as $child) {
            $instance->addChild($child);
        }
        return $instance->onClose($context);
    }

    protected function createContext(string $errorHandlerClass = StandardErrorHandler::class): RenderingContextInterface
    {
        $variableProvider = $this->getMockBuilder(VariableProviderInterface::class)->getMock();
        $variableProvider->expects($this->any())->method('getScopeCopy')->willReturnSelf();
        $variableProvider->add('s', 'I am a shorthand string');
        $variableProvider->add('i', 321);
        $variableProvider->add('string', 'I am a string');
        $variableProvider->add('integer', 42);
        $variableProvider->add('numericArray', ['foo', 'bar']);
        $variableProvider->add('associativeArray', ['foo' => 'bar']);
        $context = $this->getMockBuilder(RenderingContextInterface::class)->getMock();
        $viewHelperResolver = new ViewHelperResolver($context);
        $errorHandler = new $errorHandlerClass();
        $viewHelperResolver->addNamespace('f', 'TYPO3Fluid\\Fluid\\Tests\\Unit\\Core\\Parser\\Fixtures\\ViewHelpers');
        $viewHelperResolver->addViewHelperAlias('raw', 'f', 'format.raw');
        $parserConfiguration = new Configuration();
        $templateParser = $this->getMockBuilder(TemplateParser::class)->setMethods(['getConfiguration'])->setConstructorArgs([$context])->getMock();
        $templateParser->expects($this->any())->method('getConfiguration')->willReturn($parserConfiguration);
        $context->setViewHelperResolver($viewHelperResolver);
        $context->expects($this->any())->method('getParserConfiguration')->willReturn($parserConfiguration);
        $context->expects($this->any())->method('getTemplateParser')->willReturn($templateParser);
        $context->expects($this->any())->method('getViewHelperResolver')->willReturn($viewHelperResolver);
        $context->expects($this->any())->method('getVariableProvider')->willReturn($variableProvider);
        $context->expects($this->any())->method('getErrorHandler')->willReturn($errorHandler);
        $context->expects($this->any())->method('getExpressionNodeTypes')->willReturn([MathViewHelper::class, CastViewHelper::class, IfViewHelper::class]);
        return $context;
    }

    protected function assertNodeEquals(ComponentInterface $subject, ComponentInterface $expected, string $path = '')
    {

        $this->assertInstanceOf(get_class($expected), $subject, 'Node types not as expected at path: ' . $path);
        if ($subject instanceof ObjectAccessorNode) {
            $this->assertEquals($expected->getChildren(), $subject->getChildren(), 'ObjectAccessors do not have the same child nodes at path ' . $path);
        } elseif ($subject instanceof TextNode) {
            /** @var TextNode $subject */
            /** @var TextNode $expected */
            $this->assertSame($expected->getText(), $subject->getText(), 'TextNodes do not match at path ' . $path);
        } elseif ($subject instanceof ArrayNode) {
            $this->assertEquals($expected, $subject, 'Arrays do not match at path ' . $path);
        } elseif ($subject instanceof EntryNode) {
            //$this->assertEquals($expected->getChildren(), $subject->getChildren(), 'RootNode does not have expected children at path ' . $path);
            // NO-OP; the assertion on children consistency is done below.
        } elseif ($subject instanceof ExpressionComponentInterface) {
            $this->assertEquals($expected, $subject, 'Expression matches are not equal at path ' . $path);
        } elseif ($subject instanceof ComponentInterface) {
            $this->assertEquals($expected, $subject, 'Components do not match at path ' . $path);
        }

        $children = $subject->getChildren();
        $expectedChildren = $expected->getChildren();
        $this->assertEquals($expectedChildren, $children);
    }

    /**
     * @param string $template
     * @param RenderingContextInterface $context
     * @param bool $escapingEnabled
     * @param ComponentInterface $expectedRootNode
     */
    protected function performSequencerAssertions(
        string $template,
        RenderingContextInterface $context,
        bool $escapingEnabled,
        ComponentInterface $expectedRootNode
    ) {
        $configuration = new Configuration();
        $configuration->setFeatureState(Configuration::FEATURE_ESCAPING, $escapingEnabled);
        $parser = $context->getTemplateParser();
        $node = $parser->parse(new Source($template), $configuration);
        $this->assertNodeEquals($node, $expectedRootNode);
    }
}