<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Tests\Functional\Cases\Rendering;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Tests\Functional\BaseFunctionalTestCase;

/**
 * Class RecursiveObjectAccessorNodeTest
 */
class RecursiveObjectAccessorNodeTest extends BaseFunctionalTestCase
{

    /**
     * Variables array constructed to expect exactly three
     * recursive renderings followed by a single rendering.
     *
     * @var array
     */
    protected $variables = [
        'foo' => [
            'bar' => 'fooBarValue',
        ],
        'subArrayKeyValue' => 'bar',
    ];

    /**
     * @return array
     */
    public function getTemplateCodeFixturesAndExpectations(): array
    {
        return [
            'Recursive object accessor in root node rendering' => [
                file_get_contents(__DIR__ . '/../../Fixtures/Templates/RecursiveObjectAccessorInRootNodeRendering.html'),
                $this->variables,
                ['fooBarValue'],
                [],
            ],
            'Recursive object accessor in parameter rendering' => [
                file_get_contents(__DIR__ . '/../../Fixtures/Templates/RecursiveObjectAccessorInParameterRendering.html'),
                $this->variables,
                ['1:fooBarValue', '2:fooBarValue', '3:fooBarValue', '4:fooBarValue', '5:fooBarValue', '6:fooBarValue'],
                [],
            ],
        ];
    }
}
