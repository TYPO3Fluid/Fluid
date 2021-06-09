<?php
namespace TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Math Expression Syntax Node - is a container for numeric values.
 */
class MathExpressionNode extends AbstractExpressionNode
{

    /**
     * Pattern which detects the mathematical expressions with either
     * object accessor expressions or numbers on left and right hand
     * side of a mathematical operator inside curly braces, e.g.:
     *
     * {variable * 10}, {100 / variable}, {variable + variable2} etc.
     */
    public static $detectionExpression = '/
		(
			{                                # Start of shorthand syntax
				(?:                          # Math expression is composed of...
					[_a-zA-Z0-9\.]+(?:[\s]?[*+\^\/\%\-]{1}[\s]?[_a-zA-Z0-9\.]+)+   # Various math expressions left and right sides with any spaces
					|(?R)                    # Other expressions inside
				)+
			}                                # End of shorthand syntax
		)/x';

    /**
     * @param RenderingContextInterface $renderingContext
     * @param string $expression
     * @param array $matches
     * @return integer|float
     */
    public static function evaluateExpression(RenderingContextInterface $renderingContext, $expression, array $matches)
    {
        // Split the expression on all recognized operators
        $matches = [];
        preg_match_all('/([+\-*\^\/\%]|[_a-zA-Z0-9\.]+)/s', $expression, $matches);
        $matches[0] = array_map('trim', $matches[0]);
        // Like the BooleanNode, we dumb down the processing logic to not apply
        // any special precedence on the priority of operators. We simply process
        // them in order.
        $result = array_shift($matches[0]);
        $firstPart = static::getTemplateVariableOrValueItself($result, $renderingContext);
        if ($firstPart === $result && !is_numeric($firstPart)) {
            // Pitfall: the expression part was not numeric and did not resolve to a variable. We null the
            // value - although this means the edge case of a variable's value being the same as its name,
            // results in the expression part being treated as zero. Which is different from how PHP would
            // coerce types in earlier versions, implying that a non-numeric string just counts as "1".
            // Here, it counts as zero with the intention of error prevention on undeclared variables.
            // Note that the same happens in the loop below.
            $firstPart = null;
        }
        $result = $firstPart;
        $operator = null;
        $operators = ['*', '^', '-', '+', '/', '%'];
        foreach ($matches[0] as $part) {
            if (in_array($part, $operators)) {
                $operator = $part;
            } else {
                $newPart = static::getTemplateVariableOrValueItself($part, $renderingContext);
                if ($newPart === $part && !is_numeric($part)) {
                    $newPart = null;
                }
                $result = self::evaluateOperation($result, $operator, $newPart);
            }
        }
        return $result;
    }

    /**
     * @param integer|float $left
     * @param string $operator
     * @param integer|float $right
     * @return integer|float
     */
    protected static function evaluateOperation($left, $operator, $right)
    {
        // Special case: the "+" operator can be used with two arrays which will combine the two arrays. But it is
        // only allowable if both sides are in fact arrays and only for this one operator. Please see PHP documentation
        // about "union" on https://secure.php.net/manual/en/language.operators.array.php for specific behavior!
        if ($operator === '+' && is_array($left) && is_array($right)) {
            return $left + $right;
        }

        // Guard: if left or right side are not numeric values, infer a value for the expression part based on how
        // PHP would coerce types in versions that are not strict typed. We do this to avoid fatal PHP errors about
        // encountering non-numeric values.
        $left = static::coerceNumericValue($left);
        $right = static::coerceNumericValue($right);

        if ($operator === '%') {
            return $left % $right;
        } elseif ($operator === '-') {
            return $left - $right;
        } elseif ($operator === '+') {
            return $left + $right;
        } elseif ($operator === '*') {
            return $left * $right;
        } elseif ($operator === '/') {
            return (integer) $right !== 0 ? $left / $right : 0;
        } elseif ($operator === '^') {
            return pow($left, $right);
        }
        return 0;
    }

    protected static function coerceNumericValue($value)
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            // Delegate to another coercion call after casting to string
            return static::coerceNumericValue((string) $value);
        }
        if (is_null($value)) {
            return 0;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return $value;
        }
        return 0;
    }
}
