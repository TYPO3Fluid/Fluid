<?php
namespace TYPO3Fluid\Fluid\Tests\Functional;

use TYPO3Fluid\Fluid\Core\Cache\FluidCacheInterface;
use TYPO3Fluid\Fluid\Core\Cache\SimpleFileCache;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Class BaseFunctionalTestCase
 */
abstract class BaseConditionalFunctionalTestCase extends UnitTestCase
{

    /**
     * If your test case requires a cache, override this
     * method and return an instance.
     *
     * @return FluidCacheInterface
     */
    protected function getCache()
    {
        return new SimpleFileCache(sys_get_temp_dir());
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
     * @param boolean $expected
     * @param array $variables
     * @param boolean $withCache
     * @test
     * @dataProvider getTemplateCodeFixturesAndExpectations
     */
    public function testTemplateCodeFixture($source, $expected, array $variables = [], $withCache = false)
    {
        $source = '<f:if condition="' . $source . '" then="yes" else="no" />';
        $view = $this->getView(false);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource($source);
        $view->assignMultiple($variables);
        $output = $view->render();
        $this->assertNotEquals($view->getRenderingContext()->getTemplatePaths()->getTemplateSource(), $output, 'Input and output were the same');

        if ($expected === true) {
            $this->assertEquals('yes', $output);
        } else {
            $this->assertEquals('no', $output);
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
     * @param boolean $expectation
     * @param array $variables
     * @test
     * @dataProvider getTemplateCodeFixturesAndExpectations
     */
    public function testTemplateCodeFixtureWithCache($sourceOrStream, $expectation, array $variables = [])
    {
        if ($this->getCache()) {
            $this->testTemplateCodeFixture($sourceOrStream, $expectation, $variables, true);
            $this->testTemplateCodeFixture($sourceOrStream, $expectation, $variables, true);
        } else {
            $this->markTestSkipped('Cache-specific test skipped');
        }
    }
}
