<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Functional;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Cache\FluidCacheInterface;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Class BaseFunctionalTestCase
 */
abstract class BaseFunctionalTestCase extends UnitTestCase
{
    /**
     * If your test case requires a custom View instance
     * return the instance from this method.
     *
     * @return TemplateView
     */
    protected function getView(): TemplateView
    {
        $view = new TemplateView();
        return $view;
    }

    /**
     * Data sets used by the standard function test in test case.
     *
     * Override this method and return an array of arrays
     * which each must contain, in order:
     *
     * array(
     *     'template {code} piece', // the template code as string; or an (open) stream
     *     array('code' => 'test'), // the variables that will be assigned
     *     array('template test', 'test piece'), // list of values that MUST all be present
     *     array('negative test', 'test bad') // list of values that MUST NOT be present
     *     $exceptionClass, // NULL or a class name of an exception that is expected when executing the snippet
     *     $withCache // TRUE or FALSE depending on whether or not you wish to test the snippet with caching
     * )
     *
     * Name your sets in order to improve the error reporting:
     *
     * return array(
     *     'Outputs foobar and baz' => array(
     *          // ...
     *     ),
     *     'Outputs not foobar' => array(
     *          // ...
     *     ),
     *     'Outputs baz but not foobar' => array(
     *          // ...
     *     )
     * );
     *
     * This will clearly report *which* of the sets caused failure
     * when you run the test case.
     *
     * @return array
     */
    public function getTemplateCodeFixturesAndExpectations(): array
    {
        return [];
    }

    /**
     * Perform a standard test on the source or stream provided,
     * rendering it with $variables assigned and checking the
     * output for presense of $expected values and confirming
     * that none of the $notExpected values are present.
     *
     * @param string|resource $source
     * @param array $variables
     * @param array $expected
     * @param array $notExpected
     * @param string|NULL $expectedException
     * @test
     * @dataProvider getTemplateCodeFixturesAndExpectations
     */
    public function testTemplateCodeFixture($source, array $variables, array $expected, array $notExpected, ?string $expectedException = null): void
    {
        if (empty($expected) && empty($notExpected) && empty($expectedException)) {
            $this->fail('Test performs no assertions!');
        }
        if (!empty($expectedException)) {
            $this->expectException($expectedException);
        }
        $view = $this->getView();
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($source);
        $view->getRenderingContext()->getViewHelperResolver()->addNamespace('test', 'TYPO3Fluid\\Fluid\\Tests\\Functional\\Fixtures\\ViewHelpers');
        $view->getRenderingContext()->getViewHelperResolver()->addAtomPath('test', __DIR__ . '/Fixtures/Atoms/');

        $view->assignMultiple($variables);
        $output = trim((string)$view->render());
        foreach ($expected as $expectedValue) {
            if (is_string($expectedValue)) {
                $this->assertStringContainsString($expectedValue, $output);
            } else {
                $this->assertEquals($expectedValue, $output);
            }
        }
        foreach ($notExpected as $notExpectedValue) {
            if (is_string($notExpectedValue)) {
                $this->assertStringNotContainsString($notExpectedValue, $output);
            } else {
                $this->assertNotEquals($notExpectedValue, $output);
            }
        }
    }
}
