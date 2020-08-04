<?php
namespace TYPO3Fluid\Fluid\Tests\Functional\Cases\Conditions;

use TYPO3Fluid\Fluid\Tests\Functional\BaseFunctionalTestCase;

/**
 * Class VariableConditionsTest
 */
class TernaryConditionsTest extends BaseFunctionalTestCase
{

    /**
     * @return array
     */
    public function getTemplateCodeFixturesAndExpectations()
    {
        $someObject = new \stdClass();
        $someObject->someString = 'bar';
        $someObject->someInt = 1337;
        $someObject->someFloat = 13.37;
        $someObject->someBoolean = true;
        return [
            'quoted string then/else' => [
                '{true ? \'yes\' : \'no\'}',
                [],
                'yes',
            ],
            'unquoted integer then/else' => [
                '{true ? 1 : 2}',
                [],
                1,
            ],
            'unquoted then, quoted string else' => [
                '{true ? foo : \'bar\'}',
                ['foo' => 'bar'],
                'bar',
            ],
            'single-item grouped condition true, quoted string then/else' => [
                '{(true) ? \'yes\' : \'no\'}',
                [],
                'yes',
            ],
            'grouped or condition true, quoted string then/else' => [
                '{(true || false) ? \'yes\' : \'no\'}',
                [],
                'yes',
            ],
            'grouped or condition false, quoted string then/else' => [
                '{(false || false) ? \'yes\' : \'no\'}',
                [],
                'no',
            ],
            'object accessor condition true, quoted string then/else' => [
                '{foo ? \'yes\' : \'no\'}',
                ['foo' => true],
                'yes',
            ],
            'object accessor condition false, quoted string then/else' => [
                '{foo ? \'yes\' : \'no\'}',
                ['foo' => false],
                'no',
            ],
            'negated object accessor condition false, quoted string then/else' => [
                '{!foo ? \'yes\' : \'no\'}',
                ['foo' => false],
                'yes',
            ],
            'grouped variable accessor or false condition true, quoted string then/else' => [
                '{(foo || false) ? \'yes\' : \'no\'}',
                ['foo' => true],
                'yes',
            ],
            'grouped variable accessor or false condition false, quoted string then/else' => [
                '{(foo || false) ? \'yes\' : \'no\'}',
                ['foo' => false],
                'no',
            ],
            'grouped dotted variable accessor or false condition true, quoted string then/else' => [
                '{(foo.bar || false) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => true]],
                'yes',
            ],
            'grouped dotted variable accessor or false condition false, quoted string then/else' => [
                '{(foo.bar && false) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => true]],
                'no',
            ],
            'dotted variable accessor greater than condition true, quoted string then/else' => [
                '{(foo.bar > 10) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => 11]],
                'yes',
            ],
            'dotted variable accessor less than condition true, quoted string then/else' => [
                '{(foo.bar < 10) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => 11]],
                'no',
            ],
            'dotted variable accessor less than condition false, quoted string then/else' => [
                '{(foo.bar < 10) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => 11]],
                'no',
            ],
            'dotted variable accessor modulo condition true, quoted string then/else' => [
                '{(foo.bar % 10) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => 11]],
                'yes',
            ],
            'dotted variable accessor modulo condition false, quoted string then/else' => [
                '{(foo.bar % 10) ? \'yes\' : \'no\'}',
                ['foo' => ['bar' => 10]],
                'no',
            ],
        ];
    }
}
