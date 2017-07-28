<?php

namespace TYPO3Fluid\Fluid\Utility;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EscapingNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Root node of every syntax tree.
 */
class NodeVisualizer
{
    /**
     * @param NodeInterface $node
     * @param $renderingContext
     * @param int $depth
     */
    public static function visualize($node, $renderingContext, $depth = 0)
    {
        if (stristr(get_class($node), 'Mock_')) {
            return;
        }
        if ($depth == 0) {
            echo chr(10) . str_repeat('-', 120) . chr(10);
        }

        echo str_repeat(' ', $depth * 2);
        self::output(self::nodeType($node), 'cyan');
        self::output(' (escaping: "' . ($node->isEscapeOutputEnabled() ? 'enabled' : 'disabled') . '")', 'green');

        switch (true) {
            case $node instanceof ViewHelperNode:
                self::output(' (viewHelper: "' . $node->getViewHelperClassName() . '")', 'green');
                break;
            case $node instanceof ObjectAccessorNode:
                self::output(' (objectPath: "' . $node->getObjectPath() . '")', 'green');
                break;
            case $node instanceof BooleanNode:
                self::output(' (stack: "' . $node->reconcatenateExpression($node->getStack()) . '")', 'green');
                break;
        }

        self::outputLine();

        foreach ($node->getChildNodes() as $childNode) {
            self::visualize($childNode, $renderingContext, $depth + 1);
        }

        switch (true) {
            case $node instanceof EscapingNode:
                self::visualize($node->getNode(), $renderingContext, $depth + 1);
                break;
            case $node instanceof ViewHelperNode:
                foreach ($node->getArguments() as $name => $argumentNode) {
                    echo str_repeat(' ', ($depth + 1) * 2);
                    self::outputLine($name . ': ', 'red');
                    self::visualize($argumentNode, $renderingContext, $depth + 2);
                }
                break;
        }

        if ($depth == 0) {
            echo str_repeat('-', 120) . chr(10) . chr(10);
        }
    }

    public static function nodeType($node)
    {
        $className = get_class($node);

        return str_replace('TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\\', '', $className);
    }

    public static function output($text = null, $color = null, $background = null)
    {
        if ($text === null) {
            return;
        }
        $colors = [
            'black' => '0;30',
            'dark_gray' => '1;30',
            'blue' => '0;34',
            'light_blue' => '1;34',
            'green' => '0;32',
            'light_green' => '1;32',
            'cyan' => '0;36',
            'light_cyan' => '1;36',
            'red' => '0;31',
            'light_red' => '1;31',
            'purple' => '0;35',
            'light_purple' => '1;35',
            'brown' => '0;33',
            'yellow' => '1;33',
            'light_gray' => '0;37',
            'white' => '1;37',
        ];
        $backgrounds = [
            'black' => '40',
            'red' => '41',
            'green' => '42',
            'yellow' => '43',
            'blue' => '44',
            'magenta' => '45',
            'cyan' => '46',
            'light_gray' => '47',
        ];
        $output = '';

        if (isset($colors[$color])) {
            $output .= "\033[" . $colors[$color] . "m";
        }
        // Check if given background color found
        if (isset($backgrounds[$background])) {
            $output .= "\033[" . $backgrounds[$background] . "m";
        }

        $output .= $text . "\033[0m";

        echo $output;
    }

    public static function outputLine($text = null, $color = null, $background = null)
    {
        self::output($text, $color, $background);
        echo chr(10);
    }
}
