<?php
namespace TYPO3Fluid\Fluid\Core\Compiler;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\BooleanParser;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ArrayNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EscapingNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\ExpressionNodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NumericNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Variables\VariableExtractor;

/**
 * Class NodeConverter
 */
class NodeConverter
{

    /**
     * @var integer
     */
    protected $variableCounter = 0;

    /**
     * @var TemplateCompiler
     */
    protected $templateCompiler;

    /**
     * @param TemplateCompiler $templateCompiler
     */
    public function __construct(TemplateCompiler $templateCompiler)
    {
        $this->templateCompiler = $templateCompiler;
    }

    /**
     * @param integer $variableCounter
     * @return void
     */
    public function setVariableCounter($variableCounter)
    {
        $this->variableCounter = $variableCounter;
    }

    /**
     * Returns an array with two elements:
     * - initialization: contains PHP code which is inserted *before* the actual rendering call. Must be valid, i.e. end with semi-colon.
     * - execution: contains *a single PHP instruction* which needs to return the rendered output of the given element. Should NOT end with semi-colon.
     *
     * @param NodeInterface $node
     * @return array two-element array, see above
     * @throws FluidException
     */
    public function convert(NodeInterface $node)
    {
        switch (true) {
            case $node instanceof TextNode:
                $converted = $this->convertTextNode($node);
                break;
            case $node instanceof ExpressionNodeInterface:
                $converted = $this->convertExpressionNode($node);
                break;
            case $node instanceof NumericNode:
                $converted = $this->convertNumericNode($node);
                break;
            case $node instanceof ViewHelperNode:
                $converted = $this->convertViewHelperNode($node);
                break;
            case $node instanceof ObjectAccessorNode:
                $converted = $this->convertObjectAccessorNode($node);
                break;
            case $node instanceof ArrayNode:
                $converted = $this->convertArrayNode($node);
                break;
            case $node instanceof RootNode:
                $converted = $this->convertListOfSubNodes($node);
                break;
            case $node instanceof BooleanNode:
                $converted = $this->convertBooleanNode($node);
                break;
            case $node instanceof EscapingNode:
                $converted = $this->convertEscapingNode($node);
                break;
            default:
                $converted = [
                    'initialization' => '// Uncompilable/convertible node type: ' . get_class($node) . chr(10),
                    'execution' => ''
                ];
        }
        return $converted;
    }

    /**
     * @param EscapingNode $node
     * @return array
     */
    protected function convertEscapingNode(EscapingNode $node)
    {
        $configuration = $this->convert($node->getNode());
        $configuration['execution'] = sprintf(
            'call_user_func_array( function ($var) { ' .
            'return (is_string($var) || (is_object($var) && method_exists($var, \'__toString\')) ' .
            '? htmlspecialchars((string) $var, ENT_QUOTES) : $var); }, [%s])',
            $configuration['execution']
        );
        return $configuration;
    }

    /**
     * @param TextNode $node
     * @return array
     * @see convert()
     */
    protected function convertTextNode(TextNode $node)
    {
        return [
            'initialization' => '',
            'execution' => '\'' . $this->escapeTextForUseInSingleQuotes($node->getText()) . '\''
        ];
    }

    /**
     * @param NumericNode $node
     * @return array
     * @see convert()
     */
    protected function convertNumericNode(NumericNode $node)
    {
        return [
            'initialization' => '',
            'execution' => $node->getValue()
        ];
    }

    /**
     * Convert a single ViewHelperNode into its cached representation. If the ViewHelper implements the "Compilable" facet,
     * the ViewHelper itself is asked for its cached PHP code representation. If not, a ViewHelper is built and then invoked.
     *
     * @param ViewHelperNode $node
     * @return array
     * @see convert()
     */
    protected function convertViewHelperNode(ViewHelperNode $node)
    {
        $initializationPhpCode = '// Rendering ViewHelper ' . $node->getViewHelperClassName() . chr(10);

        // Build up $arguments array
        $argumentsVariableName = $this->variableName('arguments');
        $renderChildrenClosureVariableName = $this->variableName('renderChildrenClosure');
        $viewHelperInitializationPhpCode = '';

        try {
            $convertedViewHelperExecutionCode = $node->getUninitializedViewHelper()->compile(
                $argumentsVariableName,
                $renderChildrenClosureVariableName,
                $viewHelperInitializationPhpCode,
                $node,
                $this->templateCompiler
            );

            $accumulatedArgumentInitializationCode = '';
            $argumentInitializationCode = sprintf('%s = [', $argumentsVariableName);

            $arguments = $node->getArguments();
            foreach ($arguments as $argumentName => $argumentValue) {
                if ($argumentValue instanceof NodeInterface) {
                    $converted = $this->convert($argumentValue);
                } else {
                    $converted = [
                        'initialization' => '',
                        'execution' => $argumentValue
                    ];
                }
                $accumulatedArgumentInitializationCode .= $converted['initialization'];
                $argumentInitializationCode .= sprintf(
                    '\'%s\' => %s',
                    $argumentName,
                    $converted['execution']
                );

                $argumentInitializationCode .= ', ';
            }

            foreach ($node->getArgumentDefinitions() as $argumentName => $argumentDefinition) {
                if (!isset($arguments[$argumentName])) {
                    $defaultValue = $argumentDefinition->getDefaultValue();
                    $argumentInitializationCode .= sprintf(
                        '\'%s\' => %s',
                        $argumentName,
                        is_array($defaultValue) && empty($defaultValue) ? '[]' : var_export($defaultValue, true)
                    );
                    $argumentInitializationCode .= ', ';
                }
            }

            $argumentInitializationCode = rtrim($argumentInitializationCode, ', ');
            $argumentInitializationCode .= '];' . PHP_EOL;

            $initializationPhpCode = '// Rendering ViewHelper ' . $node->getViewHelperClassName() . chr(10);

            // Build up closure which renders the child nodes
            $initializationPhpCode .= sprintf(
                '%s = %s;',
                $renderChildrenClosureVariableName,
                $this->templateCompiler->wrapChildNodesInClosure($node)
            ) . chr(10);

            $initializationPhpCode .= $accumulatedArgumentInitializationCode . PHP_EOL . $argumentInitializationCode . $viewHelperInitializationPhpCode;
        } catch (StopCompilingChildrenException $stopCompilingChildrenException) {
            $convertedViewHelperExecutionCode = '\'' . str_replace("'", "\'", $stopCompilingChildrenException->getReplacementString()) . '\'';
        }
        $initializationArray = [
            'initialization' => $initializationPhpCode,
            'execution' => $convertedViewHelperExecutionCode === null ? 'NULL' : $convertedViewHelperExecutionCode
        ];
        return $initializationArray;
    }

    /**
     * @param ObjectAccessorNode $node
     * @return array
     * @see convert()
     */
    protected function convertObjectAccessorNode(ObjectAccessorNode $node)
    {
        $arrayVariableName = $this->variableName('array');
        $accessors = $node->getAccessors();
        $providerReference = '$renderingContext->getVariableProvider()';
        $path = $node->getObjectPath();
        $pathSegments = explode('.', $path);
        if ($path === '_all') {
            return [
                'initialization' => '',
                'execution' => sprintf('%s->getAll()', $providerReference)
            ];
        } elseif (1 === count(array_unique($accessors))
            && reset($accessors) === VariableExtractor::ACCESSOR_ARRAY
            && count($accessors) === count($pathSegments)
            && false === strpos($path, '{')
        ) {
            // every extractor used in this path is a straight-forward arrayaccess.
            // Create the compiled code as a plain old variable assignment:
            return [
                'initialization' => '',
                'execution' => sprintf(
                    'isset(%s[\'%s\']) ? %s[\'%s\'] : NULL',
                    $providerReference,
                    str_replace('.', '\'][\'', $path),
                    $providerReference,
                    str_replace('.', '\'][\'', $path)
                )
            ];
        }
        $accessorsVariable = var_export($accessors, true);
        $initialization = sprintf('%s = %s;', $arrayVariableName, $accessorsVariable);
        return [
            'initialization' => $initialization,
            'execution' => sprintf(
                '%s->getByPath(\'%s\', %s)',
                $providerReference,
                $path,
                $arrayVariableName
            )
        ];
    }

    /**
     * @param ArrayNode $node
     * @return array
     * @see convert()
     */
    protected function convertArrayNode(ArrayNode $node)
    {
        $arrayVariableName = $this->variableName('array');

        $accumulatedInitializationPhpCode = '';
        $initializationPhpCode = sprintf('%s = [', $arrayVariableName);

        foreach ($node->getInternalArray() as $key => $value) {
            if ($value instanceof NodeInterface) {
                $converted = $this->convert($value);
                if (!empty($converted['initialization'])) {
                    $accumulatedInitializationPhpCode .= $converted['initialization'];
                }
                $initializationPhpCode .= sprintf(
                    '\'%s\' => %s',
                    $key,
                    $converted['execution']
                );
            } elseif (is_numeric($value)) {
                // this case might happen for simple values
                $initializationPhpCode .= sprintf(
                    '\'%s\' => %s',
                    $key,
                    $value
                );
            } else {
                // this case might happen for simple values
                $initializationPhpCode .= sprintf(
                    '\'%s\' => \'%s\'',
                    $key,
                    $this->escapeTextForUseInSingleQuotes($value)
                );
            }
            $initializationPhpCode .= ', ';
        }

        $initializationPhpCode = rtrim($initializationPhpCode, ', ');
        $initializationPhpCode .= '];' . PHP_EOL;

        return [
            'initialization' => $accumulatedInitializationPhpCode . PHP_EOL . $initializationPhpCode,
            'execution' => $arrayVariableName
        ];
    }

    /**
     * @param NodeInterface $node
     * @return array
     * @see convert()
     */
    public function convertListOfSubNodes(NodeInterface $node)
    {
        switch (count($node->getChildNodes())) {
            case 0:
                return [
                    'initialization' => '',
                    'execution' => 'NULL'
                ];
            case 1:
                $childNode = current($node->getChildNodes());
                if ($childNode instanceof NodeInterface) {
                    return $this->convert($childNode);
                }
            default:
                $outputVariableName = $this->variableName('output');
                $initializationPhpCode = sprintf('%s = \'\';', $outputVariableName) . chr(10);

                foreach ($node->getChildNodes() as $childNode) {
                    $converted = $this->convert($childNode);

                    $initializationPhpCode .= $converted['initialization'] . chr(10);
                    $initializationPhpCode .= sprintf('%s .= %s;', $outputVariableName, $converted['execution']) . chr(10);
                }

                return [
                    'initialization' => $initializationPhpCode,
                    'execution' => $outputVariableName
                ];
        }
    }

    /**
     * @param ExpressionNodeInterface $node
     * @return array
     * @see convert()
     */
    protected function convertExpressionNode(ExpressionNodeInterface $node)
    {
        return $node->compile($this->templateCompiler);
    }

    /**
     * @param BooleanNode $node
     * @return array
     * @see convert()
     */
    protected function convertBooleanNode(BooleanNode $node)
    {
        $stack = $this->convertArrayNode(new ArrayNode($node->getStack()));
        $initializationPhpCode = '// Rendering Boolean node' . chr(10);
        $initializationPhpCode .= $stack['initialization'] . chr(10);

        $parser = new BooleanParser();
        $compiledExpression = $parser->compile(BooleanNode::reconcatenateExpression($node->getStack()));
        $functionName = $this->variableName('expression');
        $initializationPhpCode .= $functionName . ' = function($context) {return ' . $compiledExpression . ';};' . chr(10);

        return [
            'initialization' => $initializationPhpCode,
            'execution' => sprintf(
                '%s::convertToBoolean(
					%s(
						%s::gatherContext($renderingContext, %s)
					),
					$renderingContext
				)',
                BooleanNode::class,
                $functionName,
                BooleanNode::class,
                $stack['execution']
            )
        ];
    }

    /**
     * @param string $text
     * @return string
     */
    protected function escapeTextForUseInSingleQuotes($text)
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $text);
    }

    /**
     * Returns a unique variable name by appending a global index to the given prefix
     *
     * @param string $prefix
     * @return string
     */
    public function variableName($prefix)
    {
        return '$' . $prefix . $this->variableCounter++;
    }
}
