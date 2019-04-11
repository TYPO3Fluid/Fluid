<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Tests\UnitTestCase;

/**
 * Class ViewHelperResolverTest
 */
class ViewHelperResolverTest extends UnitTestCase
{

    /**
     * @test
     */
    public function testAddNamespaceWithStringRecordsNamespace()
    {
        $resolver = new ViewHelperResolver();
        $resolver->addNamespace('t', 'test');
        $this->assertAttributeContains(['test'], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testAddNamespaceWithArrayRecordsNamespace()
    {
        $resolver = new ViewHelperResolver();
        $resolver->addNamespace('t', ['test']);
        $this->assertAttributeContains(['test'], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testSetNamespacesSetsNamespaces()
    {
        $resolver = new ViewHelperResolver();
        $resolver->setNamespaces(['t' => ['test']]);
        $this->assertAttributeEquals(['t' => ['test']], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testSetNamespacesSetsNamespacesAndConvertsStringNamespaceToArray()
    {
        $resolver = new ViewHelperResolver();
        $resolver->setNamespaces(['t' => 'test']);
        $this->assertAttributeEquals(['t' => ['test']], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testIsNamespaceReturnsFalseIfNamespaceNotValid()
    {
        $resolver = new ViewHelperResolver();
        $result = $resolver->isNamespaceValid('test2');
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testResolveViewHelperClassNameThrowsExceptionIfClassNotResolved()
    {
        $resolver = new ViewHelperResolver();
        $this->setExpectedException(Exception::class);
        $resolver->resolveViewHelperClassName('f', 'invalid');
    }

    /**
     * @test
     */
    public function testResolveViewHelperClassNameSupportsMultipleNamespaces()
    {
        $resolver = $this->getAccessibleMock(ViewHelperResolver::class, ['dummy']);
        $resolver->_set('namespaces', [
            'f' => [
                'TYPO3Fluid\\Fluid\\ViewHelpers',
                'Foo\\Bar'
            ]
        ]);
        $result = $resolver->_call('resolveViewHelperName', 'f', 'render');
        $this->assertEquals('TYPO3Fluid\\Fluid\\ViewHelpers\\RenderViewHelper', $result);
    }

    /**
     * @test
     */
    public function testResolveViewHelperClassNameSupportsDirectDottedNamespace()
    {
        $resolver = $this->getAccessibleMock(ViewHelperResolver::class, ['dummy']);
        $result = $resolver->_call('resolveViewHelperName', 'TYPO3Fluid.Fluid', 'render');
        $this->assertEquals('TYPO3Fluid\\Fluid\\ViewHelpers\\RenderViewHelper', $result);
    }

    /**
     * @test
     */
    public function testResolveViewHelperClassNameTrimsBackslashSuffixFromNamespace()
    {
        $resolver = $this->getAccessibleMock(ViewHelperResolver::class, ['dummy']);
        $resolver->_set('namespaces', ['f' => ['TYPO3Fluid\\Fluid\\ViewHelpers\\']]);
        $result = $resolver->_call('resolveViewHelperName', 'f', 'render');
        $this->assertEquals('TYPO3Fluid\\Fluid\\ViewHelpers\\RenderViewHelper', $result);
    }

    /**
     * @test
     */
    public function testResolveViewHelperClassNameSupportsVendorAndPackageNameAsNamespace()
    {
        $resolver = $this->getAccessibleMock(ViewHelperResolver::class, ['dummy']);
        $result = $resolver->_call('resolveViewHelperName', 'TYPO3Fluid.Fluid', 'render');
        $this->assertEquals('TYPO3Fluid\\Fluid\\ViewHelpers\\RenderViewHelper', $result);
    }

    /**
     * @test
     */
    public function testAddNamespaceWithString()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['dummy']);
        $resolver->addNamespace('f', 'Foo\\Bar');
        $this->assertAttributeEquals([
            'f' => [
                'TYPO3Fluid\\Fluid\\ViewHelpers',
                'Foo\\Bar'
            ]
        ], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testAddNamespaceWithArray()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['dummy']);
        $resolver->addNamespace('f', ['Foo\\Bar']);
        $this->assertAttributeEquals([
            'f' => [
                'TYPO3Fluid\\Fluid\\ViewHelpers',
                'Foo\\Bar'
            ]
        ], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testAddNamespaceWithNull()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['dummy']);
        $resolver->addNamespace('ignored', null);
        $this->assertAttributeEquals(['f' => ['TYPO3Fluid\\Fluid\\ViewHelpers'], 'ignored' => null], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testAddSecondNamespaceWithNullWithExistingNullStillIgnoresNamespace()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['dummy']);
        $resolver->addNamespace('ignored', null);
        $resolver->addNamespace('ignored', null);
        $this->assertAttributeEquals(['f' => ['TYPO3Fluid\\Fluid\\ViewHelpers'], 'ignored' => null], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testAddSecondNamespaceWithExistingNullConvertsToNotIgnoredNamespace()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['dummy']);
        $resolver->addNamespace('ignored', null);
        $resolver->addNamespace('ignored', ['Foo\\Bar']);
        $this->assertAttributeEquals(['f' => ['TYPO3Fluid\\Fluid\\ViewHelpers'], 'ignored' => ['Foo\\Bar']], 'namespaces', $resolver);
    }

    /**
     * @test
     */
    public function testAddNamespaces()
    {
        $resolver = $this->getMock(ViewHelperResolver::class, ['dummy']);
        $resolver->addNamespaces(['f' => 'Foo\\Bar']);
        $this->assertAttributeEquals([
            'f' => [
                'TYPO3Fluid\\Fluid\\ViewHelpers',
                'Foo\\Bar'
            ]
        ], 'namespaces', $resolver);
    }

    /**
     * @param string $input
     * @param string $expected
     * @test
     * @dataProvider getResolvePhpNamespaceFromFluidNamespaceTestValues
     */
    public function testResolvePhpNamespaceFromFluidNamespace($input, $expected)
    {
        $resolver = new ViewHelperResolver();
        $this->assertEquals($expected, $resolver->resolvePhpNamespaceFromFluidNamespace($input));
    }

    /**
     * @return array
     */
    public function getResolvePhpNamespaceFromFluidNamespaceTestValues()
    {
        return [
            ['Foo\\Bar', 'Foo\\Bar\\ViewHelpers'],
            ['Foo\\Bar\\ViewHelpers', 'Foo\\Bar\\ViewHelpers'],
            ['http://typo3.org/ns/Foo/Bar/ViewHelpers', 'Foo\\Bar\\ViewHelpers'],
        ];
    }

    /**
     * @test
     */
    public function testCreateViewHelperInstance()
    {
        $resolver = $this->getMock(
            ViewHelperResolver::class,
            ['resolveViewHelperClassName', 'createViewHelperInstanceFromClassName']
        );
        $resolver->expects($this->once())->method('resolveViewHelperClassName')->with('foo', 'bar')->willReturn('foobar');
        $resolver->expects($this->once())->method('createViewHelperInstanceFromClassName')->with('foobar')->willReturn('baz');
        $this->assertEquals('baz', $resolver->createViewHelperInstance('foo', 'bar'));
    }

    /**
     * @test
     */
    public function addNamespaceDoesNotThrowAnExceptionIfTheAliasExistAlreadyAndPointsToTheSamePhpNamespace()
    {
        $resolver = new ViewHelperResolver();
        $resolver->addNamespace('foo', 'Some\Namespace');
        $this->assertAttributeEquals(['f' => ['TYPO3Fluid\Fluid\ViewHelpers'], 'foo' => ['Some\Namespace']], 'namespaces', $resolver);
        $resolver->addNamespace('foo', 'Some\Namespace');
        $this->assertAttributeEquals(['f' => ['TYPO3Fluid\Fluid\ViewHelpers'], 'foo' => ['Some\Namespace']], 'namespaces', $resolver);
    }

    /**
     * @param array $namespaces
     * @param string $subject
     * @param boolean $expected
     * @test
     * @dataProvider getIsNamespaceValidTestValues
     */
    public function testIsNamespaceValidOrIgnored(array $namespaces, $subject, $expected)
    {
        $resolver = new ViewHelperResolver();
        $resolver->setNamespaces($namespaces);
        $result = $resolver->isNamespaceValid($subject);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getIsNamespaceValidTestValues()
    {
        return [
            [['foo' => null], 'foo', false],
            [['foo' => ['test']], 'foo', true],
            [['foo' => ['test']], 'foobar', false],
            [['foo*' => null], 'foo', false],
            [[], 'Vendor.Namespace', true],
        ];
    }

    /**
     * @param array $namespaces
     * @param string $subject
     * @param boolean $expected
     * @test
     * @dataProvider getIsNamespaceIgnoredTestValues
     */
    public function testIsNamespaceIgnored(array $namespaces, $subject, $expected)
    {
        $resolver = new ViewHelperResolver();
        $resolver->setNamespaces($namespaces);
        $result = $resolver->isNamespaceIgnored($subject);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getIsNamespaceIgnoredTestValues()
    {
        return [
            [['foo' => null], 'foo', true],
            [['foo' => ['test']], 'foo', false],
            [['foo' => ['test']], 'foobar', false],
            [['foo*' => null], 'foobar', true],
        ];
    }

    /**
     * @param array $namespaces
     * @param string $subject
     * @param boolean $expected
     * @test
     * @dataProvider getIsNamespaceValidOrIgnoredTestValues
     */
    public function testIsNamespaceValidOrIgnoredTestValues(array $namespaces, $subject, $expected)
    {
        $resolver = new ViewHelperResolver();
        $resolver->setNamespaces($namespaces);
        $result = $resolver->isNamespaceValidOrIgnored($subject);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getIsNamespaceValidOrIgnoredTestValues()
    {
        return [
            [['foo' => null], 'foo', true],
            [['foo' => ['test']], 'foo', true],
            [['foo' => ['test']], 'foobar', false],
            [['foo*' => null], 'foobar', true],
        ];
    }
}
