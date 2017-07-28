<?php
namespace TYPO3Fluid\Fluid\Tests\Functional\Cases\Escaping;

use TYPO3Fluid\Fluid\Tests\Functional\BaseFunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Class EscapingTest
 */
class EscapingTest extends BaseFunctionalTestCase
{

    /**
     * @var array
     */
    protected $variables = ['settings' => ['test' => '<strong>Bla</strong>']];

    /**
     * @return array
     */
    public function getTemplateCodeFixturesAndExpectations()
    {
        return [
            'escapeChildren can be disabled in template' => [
                '{escapingEnabled=false}<test:escapeChildrenEnabledAndEscapeOutputDisabled>{settings.test}</test:escapeChildrenEnabledAndEscapeOutputDisabled>',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'escapeOutput can be disabled in template' => [
                '{escapingEnabled=false}<test:escapeChildrenDisabledAndEscapeOutputEnabled>{settings.test}</test:escapeChildrenDisabledAndEscapeOutputEnabled>',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'Disabling escaping twice in template throws parsing exception' => [
                '{escapingEnabled=false}<test:escapeChildrenDisabledAndEscapeOutputEnabled>{settings.test}</test:escapeChildrenDisabledAndEscapeOutputEnabled>{escapingEnabled=false}',
                [],
                [],
                [],
                'TYPO3Fluid\\Fluid\\Core\\Parser\\Exception'
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Tag syntax with children, properly encodes variable value' => [
                '<test:escapeChildrenEnabledAndEscapeOutputDisabled>{settings.test}</test:escapeChildrenEnabledAndEscapeOutputDisabled>',
                $this->variables,
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
                ['<strong>Bla</strong>'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Inline syntax with children, properly encodes variable value' => [
                '{settings.test -> test:escapeChildrenEnabledAndEscapeOutputDisabled()}',
                $this->variables,
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
                ['<strong>Bla</strong>'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Tag syntax with argument, does not encode variable value' => [
                '<test:escapeChildrenEnabledAndEscapeOutputDisabled content="{settings.test}" />',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Inline syntax with argument, does not encode variable value' => [
                '{test:escapeChildrenEnabledAndEscapeOutputDisabled(content: settings.test)}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Inline syntax with string, does not encode string value' => [
                '{test:escapeChildrenEnabledAndEscapeOutputDisabled(content: \'<strong>Bla</strong>\')}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Inline syntax with argument in quotes, does not encode variable value passed as argument (encoded before passed to VH)' => [
                '{test:escapeChildrenEnabledAndEscapeOutputDisabled(content: \'{settings.test}\')}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Tag syntax with nested inline syntax and children rendering, does not encode variable value' => [
                '<test:escapeChildrenEnabledAndEscapeOutputDisabled content="{settings.test -> test:escapeChildrenEnabledAndEscapeOutputDisabled()}" />',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenEnabledAndEscapeOutputDisabled: Tag syntax with nested inline syntax and argument in inline, does not encode variable value' => [
                '<test:escapeChildrenEnabledAndEscapeOutputDisabled content="{test:escapeChildrenEnabledAndEscapeOutputDisabled(content: settings.test)}" />',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Tag syntax with children, properly encodes variable value' => [
                '<test:escapeChildrenDisabledAndEscapeOutputDisabled>{settings.test}</test:escapeChildrenDisabledAndEscapeOutputDisabled>',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Inline syntax with children, properly encodes variable value' => [
                '{settings.test -> test:escapeChildrenDisabledAndEscapeOutputDisabled()}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Tag syntax with argument, does not encode variable value' => [
                '<test:escapeChildrenDisabledAndEscapeOutputDisabled content="{settings.test}" />',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Inline syntax with argument, does not encode variable value' => [
                '{test:escapeChildrenDisabledAndEscapeOutputDisabled(content: settings.test)}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Inline syntax with string, does not encode string value' => [
                '{test:escapeChildrenDisabledAndEscapeOutputDisabled(content: \'<strong>Bla</strong>\')}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Inline syntax with argument in quotes, does not encode variable value passed by argument (encoded before passed to VH)' => [
                '{test:escapeChildrenDisabledAndEscapeOutputDisabled(content: \'{settings.test}\')}',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Tag syntax with nested inline syntax and children rendering, does not encode variable value' => [
                '<test:escapeChildrenDisabledAndEscapeOutputDisabled content="{settings.test -> test:escapeChildrenDisabledAndEscapeOutputDisabled()}" />',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
            'EscapeChildrenDisabledAndEscapeOutputDisabled: Tag syntax with nested inline syntax and argument in inline, does not encode variable value' => [
                '<test:escapeChildrenDisabledAndEscapeOutputDisabled content="{test:escapeChildrenDisabledAndEscapeOutputDisabled(content: settings.test)}" />',
                $this->variables,
                ['<strong>Bla</strong>'],
                ['&lt;strong&gt;Bla&lt;/strong&gt;'],
            ],
        ];
    }

    public function viewHelperTemplateSourcesDataProvider()
    {
        return [
            'Tag syntax argument is string' => [
                '<f:if condition="true" then="{content}"/>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Tag syntax argument is string with string' => [
                '<f:if condition="true" then="{content} world"/>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt; world'
            ],
            'Tag syntax argument is array' => [
                '<f:if condition="true" then="{content}"/>',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Tag syntax argument is array with string' => [
                '<f:if condition="true" then="{content} world"/>',
                ['content' => ['<html>Hello</html>']],
                'Array world'
            ],
            'Tag syntax children is string' => [
                '<f:if condition="true">{content}</f:if>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Tag syntax children is string with string' => [
                '<f:if condition="true">{content} world</f:if>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt; world'
            ],
            'Tag syntax children is array' => [
                '<f:if condition="true">{content}</f:if>',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Tag syntax children is array with string' => [
                '<f:if condition="true">{content} world</f:if>',
                ['content' => ['<html>Hello</html>']],
                'Array world'
            ],
            'Inline syntax argument is string' => [
                '{f:if(condition: \'true\', then: content)}',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Inline syntax argument is string in quotes' => [
                '{f:if(condition: \'true\', then: \'{content}\')}',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Inline syntax argument is array' => [
                '{f:if(condition: \'true\', then: content)}',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Inline syntax argument is array in quotes' => [
                '{f:if(condition: \'true\', then: \'{content}\')}',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Inline syntax children is string' => [
                '{content -> f:if(condition: \'true\')}',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Inline syntax children is array' => [
                '{content -> f:if(condition: \'true\')}',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Nested tag syntax children is string' => [
                '<f:if condition="true"><f:if condition="true">{content}</f:if></f:if>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Nested tag syntax children is array' => [
                '<f:if condition="true"><f:if condition="true">{content}</f:if></f:if>',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Nested tag with inline syntax children is string' => [
                '<f:if condition="true">{content -> f:if(condition: \'true\')}</f:if>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Nested tag with inline syntax children is array' => [
                '<f:if condition="true">{content -> f:if(condition: \'true\')}</f:if>',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Nested tag syntax children is array with string' => [
                '<f:if condition="true"><f:if condition="true">{content} world</f:if></f:if>',
                ['content' => ['<html>Hello</html>']],
                'Array world'
            ],
            'Nested inline syntax children is string' => [
                '{content -> f:if(condition: \'true\') -> f:if(condition: \'true\')}',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Nested inline syntax children is array' => [
                '{content -> f:if(condition: \'true\') -> f:if(condition: \'true\')}',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Tag syntax nested inline with argument is string' => [
                '<f:if condition="true" then="{content -> f:if(condition: \'true\')}"/>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'Tag syntax nested inline with argument is string with string' => [
                '<f:if condition="true" then="{content -> f:if(condition: \'true\')} world"/>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt; world'
            ],
            'Tag syntax nested inline with argument is array' => [
                '<f:if condition="true" then="{content -> f:if(condition: \'true\')}"/>',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'Tag syntax nested inline with argument is array in string' => [
                '<f:if condition="true" then="{content -> f:if(condition: \'true\')} world"/>',
                ['content' => ['<html>Hello</html>']],
                'Array world'
            ],
            'inline syntax nested inline with argument is string' => [
                '{f:if(condition: \'true\', then: \'{content -> f:if(condition: \\\'true\\\')}\')}',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'inline syntax nested inline with argument is string with string' => [
                '<f:if condition="true" then="{content -> f:if(condition: \'true\')} world"/>',
                ['content' => '<html>Hello</html>'],
                '&lt;html&gt;Hello&lt;/html&gt;'
            ],
            'inline syntax nested inline with argument is array' => [
                '{f:if(condition: \'true\', then: \'{content -> f:if(condition: \\\'true\\\')}\')}',
                ['content' => ['<html>Hello</html>']],
                '<html>Hello</html>'
            ],
            'inline syntax nested inline with argument is array with string' => [
                '{f:if(condition: \'true\', then: \'{content -> f:if(condition: \\\'true\\\')} world\')}',
                ['content' => ['<html>Hello</html>']],
                'Array world'
            ]
        ];
    }

    /**
     * @param string $viewHelperTemplate
     * @param array $vars
     * @param string $expectedOutput
     *
     * @test
     * @dataProvider viewHelperTemplateSourcesDataProvider
     */
    public function renderingTest($viewHelperTemplate, array $vars, $expectedOutput)
    {
        $view = new TemplateView();
        $view->assignMultiple($vars);
        $viewHelperTemplate = '{namespace ft=TYPO3Fluid\Fluid\Tests\Functional\Fixtures\ViewHelpers}' . $viewHelperTemplate;
        $view->getTemplatePaths()->setTemplateSource($viewHelperTemplate);
        if (is_array($expectedOutput)) {
            $expectedOutput = var_export($expectedOutput, true);
        }
        $result = $view->render();
        if (is_array($result)) {
            $result = var_export($result, true);
        }
        $this->assertContains($expectedOutput, $result);
    }
}
