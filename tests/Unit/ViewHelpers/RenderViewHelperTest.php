<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Rendering\RenderableInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3Fluid\Fluid\Tests\Unit\Core\Rendering\RenderingContextFixture;
use TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers\Fixtures\ParsedTemplateImplementationFixture;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\ViewHelpers\RenderViewHelper;

/**
 * Testcase for RenderViewHelper
 */
class RenderViewHelperTest extends ViewHelperBaseTestcase
{

    /**
     * @var RenderViewHelper
     */
    protected $subject;

    /**
     * @var TemplateView
     */
    protected $view;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMock(RenderViewHelper::class, ['renderChildren']);
        $this->view = $this->getMock(TemplateView::class, ['renderPartial', 'renderSection']);
        $this->view->setRenderingContext($this->renderingContext);
        $container = new ViewHelperVariableContainer();
        $container->setView($this->view);
        $this->renderingContext->setViewHelperVariableContainer($container);
        $this->subject->setRenderingContext($this->renderingContext);
    }

    /**
     * @test
     */
    public function testInitializeArgumentsRegistersExpectedArguments()
    {
        $instance = $this->getMock(RenderViewHelper::class, ['registerArgument']);
        $instance->expects($this->at(0))->method('registerArgument')->with('section', 'string', $this->anything());
        $instance->expects($this->at(1))->method('registerArgument')->with('partial', 'string', $this->anything());
        $instance->expects($this->at(2))->method('registerArgument')->with('delegate', 'string', $this->anything());
        $instance->expects($this->at(3))->method('registerArgument')->with('renderable', RenderableInterface::class, $this->anything());
        $instance->expects($this->at(4))->method('registerArgument')->with('arguments', 'array', $this->anything(), false, []);
        $instance->expects($this->at(5))->method('registerArgument')->with('optional', 'boolean', $this->anything(), false, false);
        $instance->expects($this->at(6))->method('registerArgument')->with('default', 'mixed', $this->anything());
        $instance->expects($this->at(7))->method('registerArgument')->with('contentAs', 'string', $this->anything());
        $instance->initializeArguments();
    }

    public function testThrowsExceptionIfExecutedWithoutViewSetOnViewHelperVariableContainerRegardlessOfInvalidArguments()
    {
        $renderingContext = new RenderingContextFixture();
        $this->setExpectedException(Exception::class);
        $arguments = [
            'partial' => null,
            'section' => null,
            'delegate' => null,
            'renderable' => null,
            'arguments' => [],
            'optional' => true,
            'default' => null,
            'contentAs' => null
        ];
        RenderViewHelper::renderStatic($arguments, function() {}, $renderingContext);
    }

    /**
     * @test
     */
    public function testThrowsInvalidArgumentExceptionWhenNoTargetSpecifiedIfOptionalIsFalse()
    {
        $this->subject->expects($this->never())->method('renderChildren');
        $this->subject->setArguments([
            'partial' => null,
            'section' => null,
            'delegate' => null,
            'renderable' => null,
            'arguments' => [],
            'optional' => false,
            'default' => null,
            'contentAs' => null
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $this->subject->render();
    }

    /**
     * @test
     */
    public function testThrowsInvalidArgumentExceptionOnInvalidDelegateType()
    {
        $this->subject->expects($this->never())->method('renderChildren');
        $this->subject->setArguments([
            'partial' => null,
            'section' => null,
            'delegate' => RenderingContextFixture::class,
            'renderable' => null,
            'arguments' => [],
            'optional' => false,
            'default' => null,
            'contentAs' => null
        ]);
        $this->setExpectedException(\InvalidArgumentException::class);
        $this->subject->render();
    }

    /**
     * @test
     */
    public function testRenderWithDelegate()
    {
        $this->subject->expects($this->never())->method('renderChildren');
        $this->subject->setArguments([
            'partial' => null,
            'section' => null,
            'delegate' => ParsedTemplateImplementationFixture::class,
            'renderable' => null,
            'arguments' => [],
            'optional' => false,
            'default' => null,
            'contentAs' => null
        ]);
        $result = $this->subject->render();
        $this->assertEquals('rendered by fixture', $result);
    }

    /**
     * @test
     */
    public function testRenderWithRenderable()
    {
        $renderable = $this->getMockBuilder(RenderableInterface::class)->getMockForAbstractClass();
        $renderable->expects($this->once())->method('render')->willReturn('rendered by fixture');
        $this->subject->expects($this->never())->method('renderChildren');
        $this->subject->setArguments([
            'partial' => null,
            'section' => null,
            'delegate' => null,
            'renderable' => $renderable,
            'arguments' => [],
            'optional' => false,
            'default' => null,
            'contentAs' => null
        ]);
        $result = $this->subject->render();
        $this->assertEquals('rendered by fixture', $result);
    }

    /**
     * @test
     * @dataProvider getRenderTestValues
     * @param array $arguments
     * @param string|NULL $expectedViewMethod
     */
    public function testRender(array $arguments, $expectedViewMethod)
    {
        if ($expectedViewMethod !== null) {
            $this->view->expects($this->once())->method($expectedViewMethod)->willReturn(null);
        }
        $this->subject->setArguments($arguments);
        $result = $this->subject->render();
        $this->assertNull(null);
    }

    /**
     * @return array
     */
    public function getRenderTestValues()
    {
        return [
            [
                ['partial' => null, 'section' => 'foo-section', 'delegate' => null, 'renderable' => null, 'arguments' => [], 'optional' => false, 'default' => null, 'contentAs' => null],
                null
            ],
            [
                ['partial' => 'foo-partial', 'section' => null, 'delegate' => null, 'renderable' => null, 'arguments' => [], 'optional' => false, 'default' => null, 'contentAs' => null],
                'renderPartial'
            ],
            [
                ['partial' => 'foo-partial', 'section' => 'foo-section', 'delegate' => null, 'renderable' => null, 'arguments' => [], 'optional' => false, 'default' => null, 'contentAs' => null],
                'renderPartial'
            ],
            [
                ['partial' => null, 'section' => 'foo-section', 'delegate' => null, 'renderable' => null, 'arguments' => [], 'optional' => false, 'default' => null, 'contentAs' => null],
                'renderSection'
            ],
        ];
    }

    /**
     * @test
     */
    public function testRenderWithDefaultReturnsDefaultIfContentEmpty()
    {
        $this->view->expects($this->once())->method('renderPartial')->willReturn('');
        $this->subject->expects($this->never())->method('renderChildren');
        $this->subject->setArguments(
            [
                'partial' => 'test',
                'section' => null,
                'delegate' => null,
                'renderable' => null,
                'arguments' => [],
                'optional' => true,
                'default' => 'default-foobar',
                'contentAs' => null
            ]
        );
        $output = $this->subject->render();
        $this->assertEquals('default-foobar', $output);
    }

    /**
     * @test
     */
    public function testRenderSupportsContentAs()
    {
        $variables = ['foo' => 'bar', 'foobar' => 'tagcontent-foobar'];
        $this->view->expects($this->once())->method('renderPartial')->with('test1', 'test2', $variables, true)->willReturn('baz');
        $this->subject->expects($this->once())->method('renderChildren')->willReturn('tagcontent-foobar');
        $this->subject->setArguments(
            [
                'partial' => 'test1',
                'section' => 'test2',
                'delegate' => null,
                'renderable' => null,
                'arguments' => [
                    'foo' => 'bar'
                ],
                'optional' => true,
                'default' => null,
                'contentAs' => 'foobar'
            ]
        );
        $output = $this->subject->render();
        $this->assertEquals('baz', $output);
    }

    /**
     * @test
     */
    public function testRenderChildrenWhenNoContentAndNoDefault()
    {
        $this->subject->expects($this->once())->method('renderChildren')->willReturn('foobar');
        $this->subject->setArguments(
            [
                'partial' => null,
                'section' => null,
                'delegate' => null,
                'renderable' => null,
                'arguments' => [],
                'optional' => true,
                'default' => null,
                'contentAs' => null
            ]
        );
        $output = $this->subject->render();
        $this->assertEquals('foobar', $output);
    }
}
