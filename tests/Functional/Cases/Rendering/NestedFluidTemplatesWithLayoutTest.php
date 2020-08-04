<?php
namespace TYPO3Fluid\Fluid\Tests\Functional\Cases\Escaping;

use TYPO3Fluid\Fluid\Core\Cache\SimpleFileCache;
use TYPO3Fluid\Fluid\Tests\Functional\BaseFunctionalTestCase;

/**
 * Class NestedFluidTemplatesWithLayout
 */
class NestedFluidTemplatesWithLayoutTest extends BaseFunctionalTestCase
{
    /**
     * Variables array constructed to expect exactly three
     * recursive renderings followed by a single rendering.
     *
     * @var array
     */
    protected $variables = [
        'anotherFluidTemplateContent' => '',
    ];

    /**
     * @return array
     */
    public function getTemplateCodeFixturesAndExpectations()
    {
        return [
            'Nested template rendering with different layout paths' => [
                '<f:layout name="Layout"/><f:section name="main"><f:format.raw>{anotherFluidTemplateContent}</f:format.raw></f:section>',
                $this->variables,
                ['DefaultLayoutLayoutOverride'],
                [],
            ],
        ];
    }

    /**
     * Perform a standard test on the source or stream provided,
     * rendering it with $variables assigned and checking the
     * output for presense of $expected values and confirming
     * that none of the $notExpected values are present.
     *
     * @param string|resource $source
     * @param array $variables
     * @param array|string $expected
     * @param array|string|null $notExpected
     * @param string|NULL $expectedException
     * @param boolean $withCache
     * @test
     * @dataProvider getTemplateCodeFixturesAndExpectations
     */
    public function testTemplateCodeFixture($source, array $variables, $expected, $notExpected = null, $expectedException = null, $withCache = false)
    {
        $view = $this->getView($withCache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($source);
        $view->getRenderingContext()->getTemplatePaths()->setLayoutRootPaths([__DIR__ . '/../../Fixtures/Layouts/']);
        $view->getRenderingContext()->getViewHelperResolver()->addNamespace('test', 'TYPO3Fluid\\Fluid\\Tests\\Functional\\Fixtures\\ViewHelpers');

        $innerView = $this->getView($withCache);
        $innerView->getRenderingContext()->getTemplatePaths()->setTemplateSource($source);
        $innerView->getRenderingContext()->getTemplatePaths()->setLayoutRootPaths([__DIR__ . '/../../Fixtures/LayoutsOverride/Layouts/']);
        $innerView->getRenderingContext()->getViewHelperResolver()->addNamespace('test', 'TYPO3Fluid\\Fluid\\Tests\\Functional\\Fixtures\\ViewHelpers');
        $innerView->assignMultiple($variables);
        $innerOutput = trim($innerView->render());

        $view->assignMultiple(['anotherFluidTemplateContent' => $innerOutput]);
        $output = trim($view->render());

        foreach ($expected as $expectedValue) {
            if (is_string($expectedValue) === true) {
                $this->assertStringContainsString($expectedValue, $output);
            } else {
                $this->assertEquals($expectedValue, $output);
            }
        }
        foreach ($notExpected as $notExpectedValue) {
            if (is_string($notExpectedValue) === true) {
                $this->assertStringNotContainsString($notExpectedValue, $output);
            } else {
                $this->assertNotEquals($notExpectedValue, $output);
            }
        }
    }
}
