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
        if (!empty($configuration['execution']) && strtolower($configuration['execution']) !== 'null' && !is_numeric(trim($configuration['execution'], '\'"'))) {
            $configuration['execution'] = sprintf(
                'call_user_func_array( function ($var) { ' .
                'return (is_string($var) || (is_object($var) && method_exists($var, \'__toString\')) ' .
                '? htmlspecialchars((string) $var, ENT_QUOTES) : $var); }, [%s])',
                $configuration['execution']
            );
        }
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

            $arguments = $node->getArgumentDefinitions();
            $argumentInitializationCode = sprintf('%s = array();', $argumentsVariableName) . chr(10);
            foreach ($arguments as $argumentName => $argumentDefinition) {
                if (!isset($alreadyBuiltArguments[$argumentName])) {
                    $argumentInitializationCode .= sprintf(
                        '%s[\'%s\'] = %s;%s',
                        $argumentsVariableName,
                        $argumentName,
                        var_export($argumentDefinition->getDefaultValue(), true),
                        chr(10)
                    );
                }
            }

            $alreadyBuiltArguments = [];
            foreach ($node->getArguments() as $argumentName => $argumentValue) {
                if ($argumentValue instanceof NodeInterface) {
                    $converted = $this->convert($argumentValue);
                } else {
                    $converted = [
                        'initialization' => '',
                        'execution' => $argumentValue
                    ];
                }
                $argumentInitializationCode .= $converted['initialization'];
                $argumentInitializationCode .= sprintf(
                    '%s[\'%s\'] = %s;',
                    $argumentsVariableName,
                    $argumentName,
                    $converted['execution']
                ) . chr(10);
                $alreadyBuiltArguments[$argumentName] = true;
            }

            // Build up closure which renders the child nodes
            $initializationPhpCode .= sprintf(
                '%s = %s;',
                $renderChildrenClosureVariableName,
                $this->templateCompiler->wrapChildNodesInClosure($node)
            ) . chr(10);

            $initializationPhpCode .= $argumentInitializationCode . $viewHelperInitializationPhpCode;
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
        $initializationPhpCode = '// Rendering Array' . chr(10);
        $arrayVariableName = $this->variableName('array');

        $initializationPhpCode .= sprintf('%s = array();', $arrayVariableName) . chr(10);

        foreach ($node->getInternalArray() as $key => $value) {
            if ($value instanceof NodeInterface) {
                $converted = $this->convert($value);
                if (!empty($converted['initialization'])) {
                    $initializationPhpCode .= $converted['initialization'];
                }
                $initializationPhpCode .= sprintf(
                    '%s[\'%s\'] = %s;',
                    $arrayVariableName,
                    $key,
                    $converted['execution']
                ) . chr(10);
            } elseif (is_numeric($value)) {
                // this case might happen for simple values
                $initializationPhpCode .= sprintf(
                    '%s[\'%s\'] = %s;',
                    $arrayVariableName,
                    $key,
                    $value
                ) . chr(10);
            } else {
                // this case might happen for simple values
                $initializationPhpCode .= sprintf(
                    '%s[\'%s\'] = \'%s\';',
                    $arrayVariableName,
                    $key,
                    $this->escapeTextForUseInSingleQuotes($value)
                ) . chr(10);
            }
        }
        return [
            'initialization' => $initializationPhpCode,
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
                    'execution' => 'NULL',
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

                    if (!empty($converted['initialization'])) {
                        $initializationPhpCode .= $converted['initialization'] . chr(10);
                    }
                    if (!empty($converted['execution'])) {
                        $initializationPhpCode .= sprintf('%s .= %s;', $outputVariableName, $converted['execution']) . chr(10);
                    }
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
        $booleanStack = $node->getStack();

        // Quick decisions: if there is only one node and it can be determined to be a string/numeric/boolean already
        // then use it directly.
        if (count($booleanStack) === 1) {
            $compiledOnlyNode = $this->convert($booleanStack[0]);
            // Execution without possible quotation marks, then converted to lowercase for easier comparison.
            $execution = trim($compiledOnlyNode['execution'], '"\'\\');
            $lowercaseExecution = strtolower($execution);
            if ($lowercaseExecution === 'true') {
                $execution = 'true';
            } elseif ($lowercaseExecution === 'false' || empty($execution)) {
                $execution = 'false';
            } elseif (is_numeric($execution) || is_bool($execution)) {
                $execution = $execution ? 'true' : 'false';
            } else {
                $execution = null;
            }
            if ($execution !== null) {
                // Execution was re-written by one of the above cases, so we return this different execution. If it
                // was not rewritten we fall through and proceed with the stack-based strategy below.
                return [
                    'initialization' => $compiledOnlyNode['initialization'],
                    'execution' => $execution,
                ];
            }
        }

        $stack = $this->convertArrayNode(new ArrayNode($booleanStack));
        $initializationPhpCode = $stack['initialization'] . chr(10);

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
