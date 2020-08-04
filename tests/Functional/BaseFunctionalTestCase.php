<?php
namespace TYPO3Fluid\Fluid\Tests\Functional;

use org\bovigo\vfs\vfsStream;
use TYPO3Fluid\Fluid\Core\Cache\FluidCacheInterface;
use TYPO3Fluid\Fluid\Core\Cache\SimpleFileCache;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Class BaseFunctionalTestCase
 */
abstract class BaseFunctionalTestCase extends UnitTestCase
{

    /**
     * If your test case requires a cache, override this
     * method and return an instance.
     *
     * @return FluidCacheInterface
     */
    protected function getCache()
    {
        return new SimpleFileCache(vfsStream::setup()->url());
    }

    /**
     * If your test case requires a custom View instance
     * return the instance from this method.
     *
     * @return ViewInterface
     */
    protected function getView($withCache = false)
    {
        $view = new TemplateView();
        $cache = $this->getCache();
        if ($cache && $withCache) {
            $view->getRenderingContext()->setCache($cache);
        }
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
    public function getTemplateCodeFixturesAndExpectations()
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
     * @param array|string $expected
     * @param array|string|null $notExpected
     * @param string|NULL $expectedException
     * @param boolean $withCache
     * @test
     * @dataProvider getTemplateCodeFixturesAndExpectations
     */
    public function testTemplateCodeFixture($source, array $variables, $expected, $notExpected = null, $expectedException = null, $withCache = false)
    {
        if (!empty($expectedException)) {
            $this->setExpectedException($expectedException);
        }
        $view = $this->getView($withCache);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($source);
        $view->getRenderingContext()->getViewHelperResolver()->addNamespace('test', 'TYPO3Fluid\\Fluid\\Tests\\Functional\\Fixtures\\ViewHelpers');
        $view->assignMultiple($variables);
        $output = trim($view->render());
        $this->assertNotEquals($view->getRenderingContext()->getTemplatePaths()->getTemplateSource(), $output, 'Input and output were the same');
        if (empty($expected) && empty($notExpected)) {
            $this->fail('Test performs no assertions!');
        }
        if (is_array($expected)) {
            foreach ($expected as $expectedValue) {
                if (is_string($expectedValue) === true) {
                    $this->assertStringContainsString($expectedValue, $output);
                } else {
                    $this->assertEquals($expectedValue, $output);
                }
            }
        } else {
            $this->assertEquals($expected, $output);
        }
        if (is_array($notExpected)) {
            foreach ($notExpected as $notExpectedValue) {
                if (is_string($notExpectedValue) === true) {
                    $this->assertStringNotContainsString($notExpectedValue, $output);
                } else {
                    $this->assertNotEquals($notExpectedValue, $output);
                }
            }
        } elseif (is_string($notExpected)) {
            $this->assertStringNotContainsString($notExpected, $output);
        }
    }

    /**
     * Perform a standard test on the source or stream provided,
     * rendering it with $variables assigned and checking the
     * output for presense of $expected values and confirming
     * that none of the $notExpected values are present.
     *
     * Same as testTemplateCodeFixture() but includes a cache
     * in the tests. Silently skipped if the test case does not
     * return a valid cache.
     *
     * @param string|resource $sourceOrStream
     * @param array $variables
     * @param array|string $expected
     * @param array|string|null $notExpected
     * @param string|NULL $expectedException
     * @test
     * @dataProvider getTemplateCodeFixturesAndExpectations
     */
    public function testTemplateCodeFixtureWithCache($sourceOrStream, array $variables, $expected, $notExpected = null, $expectedException = null)
    {
        if ($this->getCache()) {
            $this->getCache()->flush();
            $this->testTemplateCodeFixture($sourceOrStream, $variables, $expected, $notExpected, $expectedException, true);
            $this->testTemplateCodeFixture($sourceOrStream, $variables, $expected, $notExpected, $expectedException, true);
        } else {
            $this->markTestSkipped('Cache-specific test skipped');
        }
    }
}
