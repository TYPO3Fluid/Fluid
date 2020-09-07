<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Core\Parser;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Component\Argument\ArgumentCollection;
use TYPO3Fluid\Fluid\Component\ComponentInterface;
use TYPO3Fluid\Fluid\Component\SequencingComponentInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ArrayNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EntryNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\EscapingNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;

/**
 * Sequencer for Fluid syntax
 *
 * Uses a NoRewindIterator around a sequence of byte values to
 * iterate over each syntax-relevant character and determine
 * which nodes to create.
 *
 * Passes the outer iterator between functions that perform the
 * iterations. Since the iterator is a NoRewindIterator it will
 * not be reset before the start of each loop - meaning that
 * when it is passed to a new function, that function continues
 * from the last touched index in the byte sequence.
 *
 * The circumstances around "break or return" in the switches is
 * very, very important to understand in context of how iterators
 * work. Returning does not advance the iterator like breaking
 * would and this causes a different position in the byte sequence
 * to be experienced in the method that uses the return value (it
 * sees the index of the symbol which terminated the expression,
 * not the next symbol after that).
 */
class Sequencer
{
    public const BYTE_NULL = Splitter::BYTE_NULL; // Zero-byte for terminating documents
    public const BYTE_INLINE = 123; // The "{" character indicating an inline expression started
    public const BYTE_INLINE_END = 125; // The "}" character indicating an inline expression ended
    public const BYTE_PIPE = 124; // The "|" character indicating an inline expression pass operation
    public const BYTE_MINUS = 45; // The "-" character (for legacy pass operations)
    public const BYTE_TAG = 60; // The "<" character indicating a tag has started
    public const BYTE_TAG_END = 62; // The ">" character indicating a tag has ended
    public const BYTE_TAG_CLOSE = 47; // The "/" character indicating a tag is a closing tag
    public const BYTE_QUOTE_DOUBLE = 34; // The " (standard double-quote) character
    public const BYTE_QUOTE_SINGLE = 39; // The ' (standard single-quote) character
    public const BYTE_WHITESPACE_SPACE = 32; // A standard space character
    public const BYTE_WHITESPACE_TAB = 9; // A standard carriage-return character
    public const BYTE_WHITESPACE_RETURN = 13; // A standard tab character
    public const BYTE_WHITESPACE_EOL = 10; // A standard (UNIX) line-break character
    public const BYTE_SEPARATOR_EQUALS = 61; // The "=" character
    public const BYTE_SEPARATOR_COLON = 58; // The ":" character
    public const BYTE_SEPARATOR_COMMA = 44; // The "," character
    public const BYTE_PARENTHESIS_START = 40; // The "(" character
    public const BYTE_PARENTHESIS_END = 41; // The ")" character
    public const BYTE_ARRAY_START = 91; // The "[" character
    public const BYTE_ARRAY_END = 93; // The "]" character
    public const BYTE_BACKSLASH = 92; // The "\" character
    public const BYTE_BACKTICK = 96; // The "`" character
    public const BYTE_AT = 64; // The "@" character
    public const MASK_LINEBREAKS = 0 | (1 << self::BYTE_WHITESPACE_EOL) | (1 << self::BYTE_WHITESPACE_RETURN);

    /**
     * A counter of nodes which currently disable the interceptor.
     * Needed to enable the interceptor again.
     *
     * @var int
     */
    protected $viewHelperNodesWhichDisableTheInterceptor = 0;

    /**
     * @var RenderingContextInterface
     */
    public $renderingContext;

    /**
     * @var Contexts
     */
    public $contexts;

    /**
     * @var Source
     */
    public $source;

    /**
     * @var Splitter
     */
    public $splitter;

    /** @var \NoRewindIterator */
    public $sequence;

    /**
     * @var Configuration
     */
    public $configuration;

    /**
     * @var ViewHelperResolver
     */
    protected $resolver;

    /**
     * Whether or not the escaping interceptors are active
     *
     * @var boolean
     */
    protected $escapingEnabled = true;

    /**
     * @var ComponentInterface[]
     */
    protected $nodeStack = [];

    public function __construct(
        RenderingContextInterface $renderingContext,
        Contexts $contexts,
        Source $source,
        ?Configuration $configuration = null
    ) {
        $this->source = $source;
        $this->contexts = $contexts;
        $this->renderingContext = $renderingContext;
        $this->resolver = $renderingContext->getViewHelperResolver();
        $this->configuration = $configuration ?? $renderingContext->getParserConfiguration();
        $this->escapingEnabled = $this->configuration->isFeatureEnabled(Configuration::FEATURE_ESCAPING);
        $this->splitter = new Splitter($this->source, $this->contexts);
        $this->nodeStack[] = (new EntryNode())->onOpen($this->renderingContext);
    }

    public function getComponent(): ComponentInterface
    {
        return reset($this->nodeStack) ?: $this->sequence();
    }

    public function sequence(): ComponentInterface
    {
        // Root context - the only symbols that cause any context switching are curly brace open and tag start, but
        // only if they are not preceded by a backslash character; in which case the symbol is ignored and merely
        // collected as part of the output string. NULL bytes are ignored in this context (the Splitter will yield
        // a single NULL byte when end of source is reached).
        $this->sequence = $this->splitter->parse();
        $countedEscapes = 0;
        foreach ($this->sequence as $symbol => $captured) {
            $node = end($this->nodeStack);
            $text = $captured . ($countedEscapes > 0 ? chr($symbol) : '');
            if ($text !== '') {
                $node->addChild($this->callInterceptor(new TextNode($text), InterceptorInterface::INTERCEPT_TEXT));
            }

            if ($countedEscapes > 0) {
                $countedEscapes = 0;
                continue;
            }

            switch ($symbol) {
                case self::BYTE_BACKSLASH:
                    ++$countedEscapes;
                    break;

                case self::BYTE_INLINE:
                    $countedEscapes = 0;
                    try {
                        $childNode = $this->sequenceInlineNodes(false);
                    } catch (Exception $exception) {
                        $childNode = new TextNode(
                            $this->renderingContext->getErrorHandler()->handleParserError(
                                $this->createErrorAtPosition(
                                    $exception->getMessage(),
                                    $exception->getCode()
                                )
                            )
                        );
                    }
                    $node->addChild($childNode);
                    $this->splitter->switch($this->contexts->root);
                    break;

                case self::BYTE_TAG:
                    $countedEscapes = 0;
                    try {
                        $childNode = $this->sequenceTagNode();
                    } catch (Exception $exception) {
                        $childNode = new TextNode(
                            $this->renderingContext->getErrorHandler()->handleParserError(
                                $this->createErrorAtPosition(
                                    $exception->getMessage(),
                                    $exception->getCode()
                                )
                            )
                        );
                    }
                    $this->splitter->switch($this->contexts->root);

                    if ($childNode) {
                        end($this->nodeStack)->addChild($childNode);
                    }
                    break;

                case self::BYTE_NULL:
                    break;
            }
        }

        // If there is more than a single node remaining in the stack this indicates an error. More precisely it
        // indicates that some function called in the above switch added a node to the stack but failed to remove it
        // before returning, which usually indicates that the template contains one or more incorrectly closed tags.
        // In order to report this as error we collect the classes of every remaining node in the stack. Unfortunately
        // we cannot report the position of where the closing tag was expected - this is simply not known to Fluid.
        if (count($this->nodeStack) !== 1) {
            $names = [];
            while (($unterminatedNode = array_pop($this->nodeStack))) {
                $names[] = get_class($unterminatedNode);
            }
            throw $this->createErrorAtPosition(
                'Unterminated node(s) detected: ' . implode(', ', array_reverse($names)),
                1562671632
            );
        }

        // Finishing sequencing means returning the single node that remains in the node stack, firing the onClose
        // method on it and assigning the rendering context to the ArgumentCollection carried by the root node.
        $node = array_pop($this->nodeStack)->onClose($this->renderingContext);
        $node->getArguments()->setRenderingContext($this->renderingContext);
        return $node;
    }

    public function sequenceUntilClosingTagAndIgnoreNested(ComponentInterface $parent, ?string $namespace, string $method): void
    {
        // Special method of sequencing which completely ignores any and all Fluid code inside a tag if said tag is
        // associated with a Component that implements SequencingComponentInterface and calls this method as a default
        // implementation of an "ignore everything until closed" type of behavior. Exists in Sequencer since this is
        // the most common expected use case which would otherwise 1) be likely to become duplicated, or 2) require the
        // use of a trait or base class for this single method alone. Since the Component which implements the signal
        // interface already receives the Sequencer instance it is readily available without composition concerns.
        $matchingTag = $namespace ? $namespace . ':' . $method : $method;
        $matchingTagLength = strlen($matchingTag);
        $ignoredNested = 0;
        $this->splitter->switch($this->contexts->inactive);
        $this->sequence->next();
        $text = '';
        foreach ($this->sequence as $symbol => $captured) {
            if ($symbol === self::BYTE_TAG_END && $captured !== null && strncmp($captured, $matchingTag, $matchingTagLength) === 0) {
                // An opening tag matching the parent tag - treat as text and add to ignored count.
                ++$ignoredNested;
            } elseif ($symbol === self::BYTE_TAG_END && $captured === '/' . $matchingTag) {
                // A closing version of the parent tag. Check counter; if zero, finish. If not, decrease ignored count.
                if ($ignoredNested === 0) {
                    $parent->addChild($this->callInterceptor(new TextNode((string) substr($text, 0, -1)), InterceptorInterface::INTERCEPT_TEXT));
                    return;
                }
                --$ignoredNested;
            }
            $text .= (string) $captured . chr($symbol);
        }

        throw $this->createErrorAtPosition(
            'Unterminated inactive tag: ' . $matchingTag,
            1564665730
        );
    }

    protected function sequenceCharacterData(string $text): ComponentInterface
    {
        $this->splitter->switch($this->contexts->data);
        $this->sequence->next();
        foreach ($this->sequence as $symbol => $captured) {
            $text .= $captured;
            if ($symbol === self::BYTE_TAG_END && substr($this->source->source, $this->splitter->index - 3, 2) === ']]') {
                $text .= '>';
                break;
            }
        }
        return $this->callInterceptor(new TextNode($text), InterceptorInterface::INTERCEPT_TEXT);
    }

    /**
     * Sequence a Fluid feature toggle node. Does not return
     * any node, only toggles various features of the Fluid
     * parser configuration or assigns context parameters
     * like namespaces.
     *
     * For backwards compatibility we allow the toggle name
     * to be passed, which is used in an explicit check when
     * sequencing inline nodes to detect if a {namespace ...}
     * node was encountered, in which case, this is not known
     * until the "toggle" has already been captured.
     *
     * @param string|null $toggle
     */
    protected function sequenceToggleInstruction(?string $toggle = null): void
    {
        $this->splitter->switch($this->contexts->toggle);
        $this->sequence->next();
        $flag = null;
        foreach ($this->sequence as $symbol => $captured) {
            switch ($symbol) {
                case self::BYTE_WHITESPACE_SPACE:
                    $toggle = $toggle ?? $captured;
                    break;
                case self::BYTE_INLINE_END:
                    if ($toggle === 'namespace') {
                        $parts = explode('=', (string) $captured);
                        $this->resolver->addNamespace($parts[0], $parts[1] ?? null);
                        return;
                    }

                    $this->configuration->setFeatureState($toggle, $captured ?? true);
                    // Re-read the parser configuration and react accordingly to any flags that may have changed.
                    $this->escapingEnabled = $this->configuration->isFeatureEnabled(Configuration::FEATURE_ESCAPING);
                    if (!$this->configuration->isFeatureEnabled(Configuration::FEATURE_PARSING)) {
                        throw (new PassthroughSourceException('Source must be represented as raw string', 1563379852))
                            ->setSource((string)$this->sequenceRemainderAsText());
                    }
                    return;
            }
        }
        throw $this->createErrorAtPosition('Unterminated feature toggle', 1563383038);
    }

    protected function sequenceTagNode(): ?ComponentInterface
    {
        $arguments = null;
        $definitions = null;
        $text = '<';
        $key = null;
        $namespace = null;
        $method = null;
        $bytes = &$this->source->bytes;
        $node = new RootNode();
        $closesNode = null;
        $selfClosing = false;
        $closing = false;
        $escapingEnabledBackup = $this->escapingEnabled;
        $viewHelperNode = null;

        $this->splitter->switch($this->contexts->tag);
        $this->sequence->next();
        foreach ($this->sequence as $symbol => $captured) {
            $text .= $captured;
            switch ($symbol) {
                case self::BYTE_ARRAY_START:
                    // Possible P/CDATA section. Check text explicitly for match, if matched, begin parsing-insensitive
                    // pass through sequenceCharacterDataNode()
                    $text .= '[';
                    if ($text === '<![CDATA[' || $text === '<![PCDATA[') {
                        return $this->sequenceCharacterData($text);
                    }
                    break;

                case self::BYTE_INLINE:
                    $contextBefore = $this->splitter->context;
                    $collected = $this->sequenceInlineNodes(isset($namespace, $method));
                    $node->addChild($this->callInterceptor(new TextNode($text), InterceptorInterface::INTERCEPT_TEXT));
                    $node->addChild($collected);
                    $text = '';
                    $this->splitter->switch($contextBefore);
                    break;

                case self::BYTE_SEPARATOR_EQUALS:
                    $key = $key . $captured;
                    $text .= '=';
                    if ($key === '') {
                        throw $this->createErrorAtPosition('Unexpected equals sign without preceding attribute/key name', 1561039838);
                    } elseif ($definitions !== null && !isset($definitions[$key]) && !$viewHelperNode->allowUndeclaredArgument($key)) {
                        $error = $this->createUnsupportedArgumentError($key, $definitions);
                        return $this->callInterceptor(new TextNode($this->renderingContext->getErrorHandler()->handleParserError($error)), InterceptorInterface::INTERCEPT_TEXT);
                    }
                    break;

                case self::BYTE_QUOTE_DOUBLE:
                case self::BYTE_QUOTE_SINGLE:
                    $text .= chr($symbol);
                    if ($key === null) {
                        throw $this->createErrorAtPosition('Quoted value without a key is not allowed in tags', 1558952412);
                    }
                    if ($arguments->isArgumentBoolean($key)) {
                        $arguments[$key] = $this->sequenceBooleanNode()->flatten(true);
                    } else {
                        $arguments[$key] = $this->sequenceQuotedNode()->flatten(true);
                    }
                    $key = null;
                    break;

                case self::BYTE_TAG_CLOSE:
                    $method = $method ?? $captured;
                    $text .= '/';
                    $closing = true;
                    $selfClosing = $bytes[$this->splitter->index - 1] !== self::BYTE_TAG;

                    if ($this->splitter->context->context === Context::CONTEXT_ATTRIBUTES) {
                        // Arguments may be pending: if $key is set we must create an ECMA literal style shorthand
                        // (attribute value is variable of same name as attribute). Two arguments may be created in
                        // this case, if both $key and $captured are non-null. The former contains a potentially
                        // pending argument and the latter contains a captured value-less attribute right before the
                        // tag closing character.
                        if ($key !== null) {
                            $arguments[$key] = $this->callInterceptor(new ObjectAccessorNode((string) $key), InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                            $key = null;
                        }
                        // (see comment above) Hence, the two conditions must not be compounded to else-if.
                        if ($captured !== null) {
                            $arguments[$captured] = $this->callInterceptor(new ObjectAccessorNode($captured), InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                        }
                    }
                    break;

                case self::BYTE_SEPARATOR_COLON:
                    $text .= ':';
                    if (!$method) {
                        // If we haven't encountered a method yet, then $method won't be set, and we can assign NS now
                        $namespace = $namespace ?? $captured;
                    } else {
                        // If we do have a method this means we encountered a colon as part of an attribute name
                        $key = $key ?? ($captured . ':');
                    }
                    break;

                case self::BYTE_TAG_END:
                    $text .= '>';
                    $method = $method ?? $captured;

                    $this->escapingEnabled = $escapingEnabledBackup;

                    if (($namespace === null && ($this->splitter->context->context === Context::CONTEXT_DEAD || !$this->resolver->isAliasRegistered((string) $method))) || $this->resolver->isNamespaceIgnored((string) $namespace)) {
                        return $node->addChild($this->callInterceptor(new TextNode($text), InterceptorInterface::INTERCEPT_TEXT))->flatten();
                    }

                    if (!$closing || $selfClosing) {
                        $viewHelperNode = $viewHelperNode ?? $this->resolver->createViewHelperInstance($namespace, (string) $method);
                        $viewHelperNode->onOpen($this->renderingContext)->getArguments()->validate();
                    } else {
                        // $closing will be true and $selfClosing false; add to stack, continue with children.
                        $viewHelperNode = array_pop($this->nodeStack);
                        $expectedClass = $this->resolver->resolveViewHelperClassName($namespace, (string) $method);
                        if ($expectedClass !== null && !$viewHelperNode instanceof $expectedClass) {
                            throw $this->createErrorAtPosition(
                                sprintf(
                                    'Mismatched closing tag. Expecting: %s:%s (%s). Found: (%s).',
                                    $namespace,
                                    $method,
                                    $expectedClass,
                                    get_class($viewHelperNode)
                                ),
                                1557700789
                            );
                        }
                    }

                    // Possibly pending argument still needs to be processed since $key is not null. Create an ECMA
                    // literal style associative array entry. Do the same for $captured.
                    if ($this->splitter->context->context === Context::CONTEXT_ATTRIBUTES) {
                        if ($key !== null) {
                            $value = $this->callInterceptor(new ObjectAccessorNode((string) $key), InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                            $arguments[$key] = $value;
                        }

                        if ($captured !== null) {
                            $value = $this->callInterceptor(new ObjectAccessorNode((string) $captured), InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                            $arguments[$captured] = $value;
                        }
                    }

                    if (!$closing) {
                        // The node is neither a closing or self-closing node (= an opening node expecting tag content).
                        // Add it to the stack and return null to return the Sequencer to "root" context and continue
                        // sequencing the tag's body - parsed nodes then get attached to this node as children.
                        $viewHelperNode = $this->callInterceptor($viewHelperNode, InterceptorInterface::INTERCEPT_OPENING_VIEWHELPER);
                        if ($viewHelperNode instanceof SequencingComponentInterface) {
                            // The Component will take over sequencing. It will return if encountering the right closing
                            // tag - so when it returns, we reached the end of the Component and must pop the stack.
                            $viewHelperNode->sequence($this, $namespace, (string) $method);
                            return $viewHelperNode;
                        }
                        $this->nodeStack[] = $viewHelperNode;
                        return null;
                    }

                    $viewHelperNode = $viewHelperNode->onClose($this->renderingContext);

                    $viewHelperNode = $this->callInterceptor(
                        $viewHelperNode,
                        $selfClosing ? InterceptorInterface::INTERCEPT_SELFCLOSING_VIEWHELPER : InterceptorInterface::INTERCEPT_CLOSING_VIEWHELPER
                    );

                    return $viewHelperNode;

                case self::BYTE_WHITESPACE_TAB:
                case self::BYTE_WHITESPACE_RETURN:
                case self::BYTE_WHITESPACE_EOL:
                case self::BYTE_WHITESPACE_SPACE:
                    $text .= chr($symbol);
                    if ($this->splitter->context->context === Context::CONTEXT_ATTRIBUTES) {
                        if ($captured !== null) {
                            // Encountering this case means we've collected a previous key and now collected a non-empty
                            // string value before encountering an equals sign. This is treated as ECMA literal short
                            // hand equivalent of having written `attr="{attr}"` in the Fluid template.
                            $key = $captured;
                        }
                    } elseif ($namespace !== null || (!isset($method) && $this->resolver->isAliasRegistered((string)$captured))) {
                        $method = $captured;
                        $viewHelperNode = $this->resolver->createViewHelperInstance($namespace, $method);
                        $arguments = $viewHelperNode->getArguments();
                        $definitions = (array) $arguments->getDefinitions();

                        // Forcibly disable escaping OFF as default decision for whether or not to escape an argument.
                        $this->escapingEnabled = false;
                        $this->splitter->switch($this->contexts->attributes);
                        break;
                    } else {
                        // A whitespace before a colon means the tag is not a namespaced tag. We will ignore everything
                        // inside this tag, except for inline syntax, until the tag ends. For this we use a special,
                        // limited variant of the root context where instead of scanning for "<" we scan for ">".
                        // We continue in this same loop because it still matches the potential symbols being yielded.
                        // Most importantly: this new reduced context will NOT match a colon which is the trigger symbol
                        // for a ViewHelper tag.
                        $this->splitter->switch($this->contexts->dead);
                    }
                    break;
            }
        }

        // This case on the surface of it, belongs as "default" case in the switch above. However, the only case that
        // would *actually* produce this error, is if the splitter reaches EOF (null byte) symbol before the tag was
        // closed. Literally every other possible error type will be thrown as more specific exceptions (e.g. invalid
        // argument, missing key, wrong quotes, bad inline and *everything* else with the exception of EOF). Even a
        // stray null byte would not be caught here as null byte is not part of the symbol collection for "tag" context.
        throw $this->createErrorAtPosition('Unexpected token in tag sequencing', 1557700786);
    }

    protected function sequenceInlineNodes(bool $allowArray = true): ComponentInterface
    {
        $text = '{';
        /** @var ComponentInterface|null $node */
        $node = null;
        $key = null;
        $namespace = null;
        $method = null;
        $definitions = null;
        $potentialAccessor = null;
        $callDetected = false;
        $hasPass = false;
        $hasColon = null;
        $hasWhitespace = false;
        $isArray = false;
        $arguments = new ArgumentCollection();
        $parts = [];
        $ignoredEndingBraces = 0;
        $countedEscapes = 0;
        $restore = $this->splitter->switch($this->contexts->inline);
        $this->sequence->next();
        foreach ($this->sequence as $symbol => $captured) {
            $text .= $captured;
            switch ($symbol) {
                case self::BYTE_AT:
                    $this->sequenceToggleInstruction();
                    $this->splitter->switch($restore);
                    return new TextNode('');

                case self::BYTE_BACKSLASH:
                    // Increase the number of counted escapes (is passed to sequenceNode() in the "QUOTE" cases and reset
                    // after the quoted string is extracted).
                    ++$countedEscapes;
                    if ($hasWhitespace) {
                        $node = $node ?? new RootNode();
                    } else {
                        $node = $node ?? new ObjectAccessorNode();
                    }
                    if ($captured !== null) {
                        $node->addChild($this->callInterceptor(new TextNode((string) $captured), InterceptorInterface::INTERCEPT_TEXT));
                    }
                    break;

                case self::BYTE_ARRAY_START:
                    $text .= chr($symbol);
                    $isArray = $allowArray;

                    // Sequence the node. Pass the "use numeric keys?" boolean based on the current byte. Only array
                    // start creates numeric keys. Inline start with keyless values creates ECMA style {foo:foo, bar:bar}
                    // from {foo, bar}.
                    $arguments[$key ?? $captured ?? 0] = $node = new ArrayNode();
                    $this->sequenceArrayNode($node, true);
                    $key = null;
                    break;

                case self::BYTE_INLINE:
                    // Encountering this case can mean different things: sub-syntax like {foo.{index}} or array, depending
                    // on presence of either a colon or comma before the inline. In protected mode it is simply added.
                    $text .= '{';
                    $node = $node ?? new ObjectAccessorNode();
                    if ($countedEscapes > 0) {
                        ++$ignoredEndingBraces;
                        $countedEscapes = 0;
                        if ($captured !== null) {
                            $node->addChild($this->callInterceptor(new TextNode((string)$captured), InterceptorInterface::INTERCEPT_TEXT));
                        }
                    } elseif ($this->splitter->context->context === Context::CONTEXT_PROTECTED) {
                        // Ignore one ending additional curly brace. Subtracted in the BYTE_INLINE_END case below.
                        // The expression in this case looks like {{inline}.....} and we capture the curlies.
                        $potentialAccessor .= $captured;
                    } elseif ($isArray) {
                        $isArray = true;
                        $captured = $key ?? $captured ?? $potentialAccessor;
                        // This is a sub-syntax following a colon - meaning it is an array.
                        if ($captured !== null) {
                            $arguments[$key ?? $captured ?? 0] = $node = new ArrayNode();
                            $this->sequenceArrayNode($node);
                        }
                    } else {
                        if ($captured !== null) {
                            $node->addChild(new TextNode((string) $captured));
                        }
                        $childNodeToAdd = $this->sequenceInlineNodes($allowArray);
                        $node->addChild($childNodeToAdd);
                    }
                    break;

                case self::BYTE_MINUS:
                    $text .= '-';
                    $potentialAccessor = $potentialAccessor ?? $captured;
                    break;

                // Backtick may be encountered in two different contexts: normal inline context, in which case it has
                // the same meaning as any quote and causes sequencing of a quoted string. Or protected context, in
                // which case it also sequences a quoted node but appends the result instead of assigning to array.
                // Note that backticks do not support escapes (they are a new feature that does not require escaping).
                case self::BYTE_BACKTICK:
                    if ($this->splitter->context->context === Context::CONTEXT_PROTECTED) {
                        $node->addChild($this->callInterceptor(new TextNode($text), InterceptorInterface::INTERCEPT_TEXT));
                        $node->addChild($this->sequenceQuotedNode()->flatten());
                        $text = '';
                        break;
                    }
                // Fallthrough is intentional: if not in protected context, consider the backtick a normal quote.

                // Case not normally countered in straight up "inline" context, but when encountered, means we have
                // explicitly found a quoted array key - and we extract it.
                case self::BYTE_QUOTE_SINGLE:
                case self::BYTE_QUOTE_DOUBLE:
                    if (!$allowArray) {
                        $text .= chr($symbol);
                        break;
                    }
                    if ($key !== null) {
                        $arguments[$key] = $this->sequenceQuotedNode($countedEscapes)->flatten(true);
                        $key = null;
                    } else {
                        $key = $this->sequenceQuotedNode($countedEscapes)->flatten(true);
                    }
                    $countedEscapes = 0;
                    $isArray = $allowArray;
                    break;

                case self::BYTE_SEPARATOR_COMMA:
                    if (!$allowArray) {
                        $text .= ',';
                        break;
                    }
                    if ($captured !== null) {
                        $arguments[$key ?? $captured] = is_numeric($captured) ? $captured + 0 : $this->callInterceptor(new ObjectAccessorNode($captured), InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                    }
                    $key = null;
                    $isArray = $allowArray;
                    break;

                case self::BYTE_SEPARATOR_EQUALS:
                    $text .= '=';
                    if (!$allowArray) {
                        $node = new RootNode();
                        $this->splitter->switch($this->contexts->protected);
                        break;
                    }
                    $key = $captured;
                    $isArray = $allowArray;
                    break;

                case self::BYTE_SEPARATOR_COLON:
                    $text .= ':';
                    $hasColon = true;
                    $namespace = $captured;
                    $key = $key ?? $captured;
                    $isArray = $isArray || ($allowArray && is_numeric($key));
                    if ($captured !== null) {
                        $parts[] = $captured;
                    }
                    $parts[] = ':';
                    break;

                case self::BYTE_WHITESPACE_SPACE:
                case self::BYTE_WHITESPACE_EOL:
                case self::BYTE_WHITESPACE_RETURN:
                case self::BYTE_WHITESPACE_TAB:
                    // If we already collected some whitespace we must enter protected context.
                    $text .= $this->source->source[$this->splitter->index - 1];

                    if ($captured !== null) {
                        // Store a captured part: a whitespace inside inline syntax will engage the expression matching
                        // that occurs when the node is closed. Allows matching the various parts to create the appropriate
                        // node type.
                        $parts[] = $captured;
                    }

                    if ($hasWhitespace && !$hasPass && !$allowArray) {
                        // Protection mode: this very limited context does not allow tags or inline syntax, and will
                        // protect things like CSS and JS - and will only enter a more reactive context if encountering
                        // the backtick character, meaning a quoted string will be sequenced. This backtick-quoted
                        // string can then contain inline syntax like variable accessors.
                        $node = $node ?? new RootNode();
                        $this->splitter->switch($this->contexts->protected);
                        break;
                    }

                    if ($captured === 'namespace') {
                        // Special case: we catch namespace definitions with {namespace xyz=foo} syntax here, although
                        // the proper way with current code is to use {@namespace xyz=foo}. We have this case here since
                        // it is relatively cheap (only happens when we see a space inside inline and a straight-up
                        // string comparison with strict types enabled). We then return an empty TextNode which is
                        // ignored by the parent node when attached so we don't create any output.
                        $this->sequenceToggleInstruction('namespace');
                        $this->splitter->switch($restore);
                        return new TextNode('');
                    }
                    $key = $key ?? $captured;
                    $hasWhitespace = true;
                    $isArray = $allowArray && ($hasColon ?? $isArray ?? is_numeric($captured));
                    $potentialAccessor = ($potentialAccessor ?? $captured);
                    break;

                case self::BYTE_TAG_END:
                case self::BYTE_PIPE:
                    // If there is an accessor on the left side of the pipe and $node is not defined, we create $node
                    // as an object accessor. If $node already exists we do nothing (and expect the VH trigger, the
                    // parenthesis start case below, to add $node as childnode and create a new $node).
                    $hasPass = true;
                    $isArray = $allowArray;
                    $callDetected = false;

                    $text .=  $this->source->source[$this->splitter->index - 1];
                    $node = $node ?? new ObjectAccessorNode();
                    if ($potentialAccessor ?? $captured) {
                        $node->addChild($this->callInterceptor(new TextNode($potentialAccessor . $captured), InterceptorInterface::INTERCEPT_TEXT));
                    }

                    $potentialAccessor = $namespace = $method = $key = null;
                    break;

                case self::BYTE_PARENTHESIS_START:
                    $isArray = false;
                    // Special case: if a parenthesis start was preceded by whitespace but had no pass operator we are
                    // not dealing with a ViewHelper call and will continue the sequencing, grabbing the parenthesis as
                    // part of the expression.
                    $text .= '(';
                    if (!$hasColon || ($hasWhitespace && !$hasPass)) {
                        $this->splitter->switch($this->contexts->protected);
                        $namespace = $method = null;
                        break;
                    }

                    $callDetected = true;
                    $method = $captured;
                    $childNodeToAdd = $node;
                    $node = $this->resolver->createViewHelperInstance($namespace, $method);
                    $arguments = $node->getArguments();
                    $node = $this->callInterceptor($node, InterceptorInterface::INTERCEPT_OPENING_VIEWHELPER);

                    $this->sequenceArrayNode($arguments);
                    $arguments->setRenderingContext($this->renderingContext)->validate();
                    $node = $node->onOpen($this->renderingContext);

                    if ($childNodeToAdd) {
                        if ($childNodeToAdd instanceof ObjectAccessorNode) {
                            $childNodeToAdd = $this->callInterceptor($childNodeToAdd, InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                        }
                        $node->addChild($childNodeToAdd);
                    }
                    $node = $node->onClose($this->renderingContext);
                    $text .= ')';
                    $potentialAccessor = null;
                    break;

                case self::BYTE_INLINE_END:
                    $text .= '}';

                    if (--$ignoredEndingBraces >= 0) {
                        if ($captured !== null) {
                            $node->addChild($this->callInterceptor(new TextNode('{' . $captured . '}'), InterceptorInterface::INTERCEPT_TEXT));
                        }
                        break;
                    }

                    if ($text === '{}') {
                        // Edge case handling of empty JS objects
                        return new TextNode('{}');
                    }

                    $isArray = $allowArray && ($isArray ?: ($hasColon && !$hasPass && !$callDetected));
                    $potentialAccessor .= $captured;
                    $interceptionPoint = InterceptorInterface::INTERCEPT_OBJECTACCESSOR;

                    // Decision: if we did not detect a ViewHelper we match the *entire* expression, from the cached
                    // starting index, to see if it matches a known type of expression. If it does, we must return the
                    // appropriate type of ExpressionNode.
                    if ($isArray) {
                        if ($captured !== null) {
                            $arguments[$key ?? $captured] = is_numeric($captured) ? $captured + 0 : $this->callInterceptor(
                                new ObjectAccessorNode($captured),
                                InterceptorInterface::INTERCEPT_OBJECTACCESSOR
                            );
                        }
                        $this->splitter->switch($restore);
                        return $arguments->toArrayNode();
                    } elseif ($callDetected) {
                        // The first-priority check is for a ViewHelper used right before the inline expression ends,
                        // in which case there is no further syntax to come.
                        $arguments->validate();
                        $node = $node->onOpen($this->renderingContext)->onClose($this->renderingContext);
                        $interceptionPoint = InterceptorInterface::INTERCEPT_SELFCLOSING_VIEWHELPER;
                    } elseif ($this->splitter->context->context === Context::CONTEXT_PROTECTED || ($hasWhitespace && $callDetected === false && $hasPass === false)) {
                        // In order to qualify for potentially being an expression, the entire inline node must contain
                        // whitespace, must not contain parenthesis, must not contain a colon and must not contain an
                        // inline pass operand. This significantly limits the number of times this (expensive) routine
                        // has to be executed.
                        $parts[] = $captured;
                        try {
                            foreach ($this->renderingContext->getExpressionNodeTypes() as $expressionNodeTypeClassName) {
                                if ($expressionNodeTypeClassName::matches($parts)) {
                                    $childNodeToAdd = new $expressionNodeTypeClassName($parts);
                                    $childNodeToAdd = $this->callInterceptor($childNodeToAdd, InterceptorInterface::INTERCEPT_EXPRESSION);
                                    break;
                                }
                            }
                        } catch (ExpressionException $exception) {
                            // ErrorHandler will either return a string or throw the exception anew, depending on the
                            // exact implementation of ErrorHandlerInterface. When it returns a string we use that as
                            // text content of a new TextNode so the message is output as part of the rendered result.
                            $childNodeToAdd = $this->callInterceptor(
                                new TextNode(
                                    $this->renderingContext->getErrorHandler()->handleExpressionError($exception)
                                ),
                                InterceptorInterface::INTERCEPT_TEXT
                            );
                        }
                        $node = $childNodeToAdd ?? ($node ?? new RootNode())->addChild($this->callInterceptor(new TextNode($text), InterceptorInterface::INTERCEPT_TEXT));
                        return $node;
                    } elseif ($hasPass === false && $callDetected === false) {
                        $node = $node ?? new ObjectAccessorNode();
                        if ($potentialAccessor !== '') {
                            $node->addChild($this->callInterceptor(new TextNode((string) $potentialAccessor), InterceptorInterface::INTERCEPT_TEXT));
                        }
                    } elseif ($hasPass && $this->resolver->isAliasRegistered((string) $potentialAccessor)) {
                        // Fourth priority check is for a pass to a ViewHelper alias, e.g. "{value | raw}" in which case
                        // we look for the alias used and create a ViewHelper with no arguments.
                        $childNodeToAdd = $node;
                        $node = $this->resolver->createViewHelperInstance(null, (string) $potentialAccessor);
                        $arguments = $node->getArguments()->validate()->setRenderingContext($this->renderingContext);
                        $node = $node->onOpen($this->renderingContext);
                        $node->addChild($childNodeToAdd);
                        $node->onClose(
                            $this->renderingContext
                        );
                        $interceptionPoint = InterceptorInterface::INTERCEPT_SELFCLOSING_VIEWHELPER;
                    } else {
                        # TODO: should this be an error case, or should it result in a TextNode?
                        throw $this->createErrorAtPosition(
                            'Invalid inline syntax - not accessor, not expression, not array, not ViewHelper, but ' .
                            'contains the tokens used by these in a sequence that is not valid Fluid syntax. You can ' .
                            'most likely avoid this by adding whitespace inside the curly braces before the first ' .
                            'Fluid-like symbol in the expression. Symbols recognized as Fluid are: "' .
                            addslashes(implode('","', array_map('chr', $this->contexts->inline->bytes))) . '"',
                            1558782228
                        );
                    }

                    $node = $this->callInterceptor($node, $interceptionPoint);
                    $this->splitter->switch($restore);
                    return $node;
            }
        }

        // See note in sequenceTagNode() end of method body. TL;DR: this is intentionally here instead of as "default"
        // case in the switch above for a very specific reason: the case is only encountered if seeing EOF before the
        // inline expression was closed.
        throw $this->createErrorAtPosition('Unterminated inline syntax', 1557838506);
    }

    protected function sequenceBooleanNode(int $leadingEscapes = 0): BooleanNode
    {
        $startingByte = $this->source->bytes[$this->splitter->index];
        $closingByte = $startingByte === self::BYTE_PARENTHESIS_START ? self::BYTE_PARENTHESIS_END : $startingByte;
        $countedEscapes = 0;
        $node = new BooleanNode();
        $restore = $this->splitter->switch($this->contexts->boolean);
        $this->sequence->next();
        foreach ($this->sequence as $symbol => $captured) {
            if ($captured !== null) {
                $node->addChild($this->callInterceptor(new TextNode($captured), InterceptorInterface::INTERCEPT_TEXT));
            }
            switch ($symbol) {
                case self::BYTE_INLINE:
                    $node->addChild($this->sequenceInlineNodes(true));
                    break;

                case self::BYTE_QUOTE_DOUBLE:
                case self::BYTE_QUOTE_SINGLE:
                    if ($symbol === $closingByte && $countedEscapes === $leadingEscapes) {
                        $this->splitter->switch($restore);
                        return $node;
                    }
                    // Sequence a quoted node and set the "quoted" flag on the resulting root node (which is not
                    // flattened even if it contains a single child). This allows the BooleanNode to enforce a string
                    // value whenever parts of the expression are quoted, indicating user explicitly wants string type.
                    $node->addChild($this->sequenceQuotedNode($countedEscapes)->setQuoted(true));
                    break;

                case self::BYTE_PARENTHESIS_START:
                    $node->addChild($this->sequenceBooleanNode());
                    break;

                case self::BYTE_PARENTHESIS_END:
                    $this->splitter->switch($restore);
                    return $node;

                case self::BYTE_WHITESPACE_SPACE:
                case self::BYTE_WHITESPACE_TAB:
                case self::BYTE_WHITESPACE_RETURN:
                case self::BYTE_WHITESPACE_EOL:
                    break;

                case self::BYTE_BACKSLASH:
                    ++$countedEscapes;
                    break;
            }
            if ($symbol !== self::BYTE_BACKSLASH) {
                $countedEscapes = 0;
            }
        }
        throw $this->createErrorAtPosition('Unterminated boolean expression', 1564159986);
    }

    protected function sequenceArrayNode(\ArrayAccess &$array, bool $numeric = false): void
    {
        $definitions = null;
        if ($array instanceof ArgumentCollection) {
            $definitions = (array) $array->getDefinitions();
        }

        $keyOrValue = null;
        $key = null;
        $value = null;
        $itemCount = -1;
        $countedEscapes = 0;
        $escapingEnabledBackup = $this->escapingEnabled;
        $this->escapingEnabled = false;

        $restore = $this->splitter->switch($this->contexts->array);
        $this->sequence->next();
        foreach ($this->sequence as $symbol => $captured) {
            switch ($symbol) {
                case self::BYTE_SEPARATOR_COLON:
                case self::BYTE_SEPARATOR_EQUALS:
                    // Colon or equals has same meaning (which allows tag syntax as argument syntax). Encountering this
                    // byte always means the preceding byte was a key. However, if nothing was captured before this,
                    // it means colon or equals was used without a key which is a syntax error.
                    $key = $key ?? $captured ?? ($keyOrValue !== null ? $keyOrValue->flatten(true) : null);
                    if ($key === null) {
                        throw $this->createErrorAtPosition('Unexpected colon or equals sign, no preceding key', 1559250839);
                    }
                    if ($definitions !== null && !$numeric && !isset($definitions[$key])) {
                        throw $this->createUnsupportedArgumentError((string)$key, $definitions);
                    }
                    break;

                case self::BYTE_ARRAY_START:
                case self::BYTE_INLINE:
                    // Minimal safeguards to improve error feedback. Theoretically such "garbage" could simply be ignored
                    // without causing problems to the parser, but it is probably best to report it as it could indicate
                    // the user expected X value but gets Y and doesn't notice why.
                    if ($captured !== null) {
                        throw $this->createErrorAtPosition('Unexpected content before array/inline start in associative array, ASCII: ' . ord($captured), 1559131849);
                    }
                    if ($key === null && !$numeric) {
                        throw $this->createErrorAtPosition('Unexpected array/inline start in associative array without preceding key', 1559131848);
                    }

                    // Encountering a curly brace or square bracket start byte will both cause a sub-array to be sequenced,
                    // the difference being that only the square bracket will cause third parameter ($numeric) passed to
                    // sequenceArrayNode() to be true, which in turn causes key-less items to be added with numeric indexes.
                    $key = $key ?? ++$itemCount;
                    $arrayNode = new ArrayNode();
                    $this->sequenceArrayNode($arrayNode, $symbol === self::BYTE_ARRAY_START);
                    $array[$key] = $arrayNode;
                    $keyOrValue = null;
                    $key = null;
                    break;

                case self::BYTE_QUOTE_SINGLE:
                case self::BYTE_QUOTE_DOUBLE:
                    // Safeguard: if anything is captured before a quote this indicates garbage leading content. As with
                    // the garbage safeguards above, this one could theoretically be ignored in favor of silently making
                    // the odd syntax "just work".
                    if ($captured !== null) {
                        throw $this->createErrorAtPosition('Unexpected content before quote start in associative array, ASCII: ' . ord($captured), 1559145560);
                    }

                    // Quotes will always cause sequencing of the quoted string, but differs in behavior based on whether
                    // or not the $key is set. If $key is set, we know for sure we can assign a value. If it is not set
                    // we instead leave $keyOrValue defined so this will be processed by one of the next iterations.
                    if (isset($key, $definitions[$key]) && $definitions[$key]->getType() === 'boolean') {
                        $keyOrValue = $this->sequenceBooleanNode($countedEscapes);
                    } else {
                        $keyOrValue = $this->sequenceQuotedNode($countedEscapes);
                    }
                    if ($key !== null) {
                        $array[$key] = $keyOrValue->flatten(true);
                        $keyOrValue = null;
                        $key = null;
                        $countedEscapes = 0;
                    }
                    break;

                case self::BYTE_SEPARATOR_COMMA:
                    // Comma separator: if we've collected a key or value, use it. Otherwise, use captured string.
                    // If neither key nor value nor captured string exists, ignore the comma (likely a tailing comma).
                    $value = null;
                    if ($keyOrValue !== null) {
                        // Key or value came as quoted string and exists in $keyOrValue
                        $potentialValue = $keyOrValue->flatten(true);
                        $key = $numeric ? ++$itemCount : $potentialValue;
                        $value = $numeric ? $potentialValue : (is_numeric($key) ? $key + 0 : new ObjectAccessorNode((string) $key));
                    } elseif ($captured !== null) {
                        $key = $key ?? ($numeric ? ++$itemCount : $captured);
                        if (!$numeric && $definitions !== null && !isset($definitions[$key])) {
                            throw $this->createUnsupportedArgumentError((string)$key, $definitions);
                        }
                        $value = is_numeric($captured) ? $captured + 0 : new ObjectAccessorNode($captured);
                    }
                    if ($value instanceof ObjectAccessorNode) {
                        $array[$key] = $this->callInterceptor($value, InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                    } elseif ($value !== null) {
                        $array[$key] = $value;
                    }
                    $keyOrValue = null;
                    $value = null;
                    $key = null;
                    break;

                case self::BYTE_WHITESPACE_TAB:
                case self::BYTE_WHITESPACE_RETURN:
                case self::BYTE_WHITESPACE_EOL:
                case self::BYTE_WHITESPACE_SPACE:
                    // Any whitespace attempts to set the key, if not already set. The captured string may be null as
                    // well, leaving the $key variable still null and able to be coalesced.
                    $key = $key ?? $captured;
                    break;

                case self::BYTE_BACKSLASH:
                    // Escapes are simply counted and passed to the sequenceQuotedNode() method, causing that method
                    // to ignore exactly this number of backslashes before a matching quote is seen as closing quote.
                    ++$countedEscapes;
                    break;

                case self::BYTE_INLINE_END:
                case self::BYTE_ARRAY_END:
                case self::BYTE_PARENTHESIS_END:
                    // Array end indication. Check if anything was collected previously or was captured currently,
                    // assign that to the array and return an ArrayNode with the full array inside.
                    $captured = $captured ?? ($keyOrValue !== null ? $keyOrValue->flatten(true) : null);
                    $key = $key ?? ($numeric ? ++$itemCount : $captured);
                    if (isset($captured, $key)) {
                        if (is_numeric($captured)) {
                            $nodeOrValue = $captured + 0;
                        } elseif ($keyOrValue !== null) {
                            $nodeOrValue = $keyOrValue->flatten();
                        } else {
                            $nodeOrValue = new ObjectAccessorNode((string) ($captured ?? $key));
                        }
                        if ($nodeOrValue instanceof ObjectAccessorNode) {
                            $array[$key] = $this->callInterceptor($nodeOrValue, InterceptorInterface::INTERCEPT_OBJECTACCESSOR);
                        } else {
                            $array[$key] = $nodeOrValue;
                        }
                    }
                    if (!$numeric && isset($key, $definitions) && !isset($definitions[$key])) {
                        throw $this->createUnsupportedArgumentError((string)$key, $definitions);
                    }
                    $this->escapingEnabled = $escapingEnabledBackup;
                    $this->splitter->switch($restore);
                    return;
            }
        }

        throw $this->createErrorAtPosition(
            'Unterminated array',
            1557748574
        );
    }

    /**
     * Sequence a quoted value
     *
     * The return can be either of:
     *
     * 1. A string value if source was for example "string"
     * 2. An integer if source was for example "1"
     * 3. A float if source was for example "1.25"
     * 4. A RootNode instance with multiple child nodes if source was for example "string {var}"
     *
     * The idea is to return the raw value if there is no reason for it to
     * be a node as such - which is only necessary if the quoted expression
     * contains other (dynamic) values like an inline syntax.
     *
     * @param int $leadingEscapes A backwards compatibility measure: when passed, this number of escapes must precede a closing quote for it to trigger node closing.
     * @return RootNode
     */
    protected function sequenceQuotedNode(int $leadingEscapes = 0): RootNode
    {
        $startingByte = $this->source->bytes[$this->splitter->index];
        $node = new RootNode();
        $countedEscapes = 0;

        $contextToRestore = $this->splitter->switch($this->contexts->quoted);
        $this->sequence->next();
        foreach ($this->sequence as $symbol => $captured) {
            switch ($symbol) {

                case self::BYTE_ARRAY_START:
                    $countedEscapes = 0; // Theoretically not required but done in case of stray escapes (gets ignored)
                    if ($captured === null) {
                        // Array start "[" only triggers array sequencing if it is the very first byte in the quoted
                        // string - otherwise, it is added as part of the text.
                        $child = new ArrayNode();
                        $this->sequenceArrayNode($child, true);
                        $node->addChild($child);
                    } else {
                        $node->addChild($this->callInterceptor(new TextNode($captured . '['), InterceptorInterface::INTERCEPT_TEXT));
                    }
                    break;

                case self::BYTE_INLINE:
                    $countedEscapes = 0; // Theoretically not required but done in case of stray escapes (gets ignored)
                    // The quoted string contains a sub-expression. We extract the captured content so far and if it
                    // is not an empty string, add it as a child of the RootNode we're building, then we add the inline
                    // expression as next sibling and continue the loop.
                    if ($captured !== null) {
                        $childNode = new TextNode($captured);
                        $childNode = $this->callInterceptor($childNode, InterceptorInterface::INTERCEPT_TEXT);
                        $node->addChild($childNode);
                    }

                    $node->addChild($this->sequenceInlineNodes());
                    break;

                case self::BYTE_BACKSLASH:
                    $next = $this->source->bytes[$this->splitter->index + 1] ?? null;
                    ++$countedEscapes;
                    if ($next === $startingByte || $next === self::BYTE_BACKSLASH) {
                        if ($captured !== null) {
                            $node->addChild($this->callInterceptor(new TextNode($captured), InterceptorInterface::INTERCEPT_TEXT));
                        }
                    } else {
                        $node->addChild($this->callInterceptor(new TextNode($captured . str_repeat('\\', $countedEscapes)), InterceptorInterface::INTERCEPT_TEXT));
                        $countedEscapes = 0;
                    }
                    break;

                // Note: although "case $startingByte:" could have been used here, it would not compile the switch
                // as a hash map and thus would not perform as well overall - when called frequently as it will be.
                // Backtick will only be encountered if the context is "protected" (insensitive inline sequencing)
                case self::BYTE_QUOTE_SINGLE:
                case self::BYTE_QUOTE_DOUBLE:
                case self::BYTE_BACKTICK:
                    if ($symbol !== $startingByte || $countedEscapes !== $leadingEscapes) {
                        $childNode = new TextNode($captured . chr($symbol));
                        $childNode = $this->callInterceptor($childNode, InterceptorInterface::INTERCEPT_TEXT);
                        $node->addChild($childNode);
                        $countedEscapes = 0; // If number of escapes do not match expected, reset the counter
                        break;
                    }
                    if ($captured !== null) {
                        $childNode = new TextNode($captured);
                        $childNode = $this->callInterceptor($childNode, InterceptorInterface::INTERCEPT_TEXT);
                        $node->addChild($childNode);
                    }
                    $this->splitter->switch($contextToRestore);
                    return $node;
            }
        }

        throw $this->createErrorAtPosition('Unterminated expression inside quotes', 1557700793);
    }

    /**
     * Dead-end sequencing; if parsing is switched off it cannot be switched on again,
     * and the remainder of the template source must be sequenced as dead text.
     *
     * @return string|null
     */
    protected function sequenceRemainderAsText(): ?string
    {
        $this->sequence->next();
        $this->splitter->switch($this->contexts->empty);
        $source = null;
        foreach ($this->sequence as $symbol => $captured) {
            $source .= $captured;
        }
        return $source;
    }

    /**
     * Call all interceptors registered for a given interception point.
     *
     * @param ComponentInterface $node The syntax tree node which can be modified by the interceptors.
     * @param integer $interceptorPosition the interception point. One of the \TYPO3Fluid\Fluid\Core\Parser\self::INTERCEPT_* constants.
     * @return ComponentInterface
     */
    protected function callInterceptor(ComponentInterface $node, int $interceptorPosition): ComponentInterface
    {
        foreach ($this->configuration->getInterceptors($interceptorPosition) ?? [] as $interceptor) {
            $node = $interceptor->process($node, $interceptorPosition, $this->getComponent());
        }

        if (!$this->escapingEnabled) {
            // Escaping is explicitly disabled, avoid calling the escaping logic below.
            return $node;
        }

        switch ($interceptorPosition) {
            case InterceptorInterface::INTERCEPT_OPENING_VIEWHELPER:
                if (!$node->isChildrenEscapingEnabled()) {
                    ++$this->viewHelperNodesWhichDisableTheInterceptor;
                }
                break;

            case InterceptorInterface::INTERCEPT_CLOSING_VIEWHELPER:
                if (!$node->isChildrenEscapingEnabled()) {
                    --$this->viewHelperNodesWhichDisableTheInterceptor;
                }
                if ($this->viewHelperNodesWhichDisableTheInterceptor === 0 && $node->isOutputEscapingEnabled()) {
                    $node = new EscapingNode($node);
                }
                break;

            case InterceptorInterface::INTERCEPT_SELFCLOSING_VIEWHELPER:
                if ($this->viewHelperNodesWhichDisableTheInterceptor === 0 && $node->isOutputEscapingEnabled()) {
                    $node = new EscapingNode($node);
                }
                break;

            case InterceptorInterface::INTERCEPT_OBJECTACCESSOR:
            case InterceptorInterface::INTERCEPT_EXPRESSION:
                if ($this->viewHelperNodesWhichDisableTheInterceptor === 0) {
                    $node = new EscapingNode($node);
                }
                break;
        }
        return $node;
    }

    /**
     * Creates a dump, starting from the first line break before $position,
     * to the next line break from $position, counting the lines and characters
     * and inserting a marker pointing to the exact offending character.
     *
     * Is not very efficient - but adds bug tracing information. Should only
     * be called when exceptions are raised during sequencing.
     *
     * @param int $index
     * @return string
     */
    protected function extractSourceDumpOfLineAtPosition(int $index): string
    {
        $lines = $this->countCharactersMatchingMask(self::MASK_LINEBREAKS, 1, $index) + 1;
        $offset = $this->findBytePositionBeforeOffset(self::MASK_LINEBREAKS, $index);
        $line = substr(
            $this->source->source,
            $offset,
            $this->findBytePositionAfterOffset(self::MASK_LINEBREAKS, $offset + 1) - $offset
        );
        $character = $index - $offset - 1;
        $string = 'Line ' . $lines . ' character ' . $character . PHP_EOL;
        $string .= PHP_EOL;
        $string .= str_repeat(' ', max($character, 0)) . 'v' . PHP_EOL;
        $string .= rtrim($line) . PHP_EOL;
        $string .= str_repeat(' ', max($character, 0)) . '^' . PHP_EOL;
        return $string;
    }

    protected function createErrorAtPosition(string $message, int $code): SequencingException
    {
        $error = new SequencingException($message, $code);
        $error->setExcerpt($this->extractSourceDumpOfLineAtPosition($this->splitter->index));
        $error->setByte($this->source->bytes[$this->splitter->index] ?? 0);
        $error->setLine($this->countCharactersMatchingMask(1 << self::BYTE_WHITESPACE_EOL, 0, $this->splitter->index) + 1);
        if ($this->source instanceof FileSource) {
            $error->setFile($this->source->filePathAndFilename);
        } else {
            $error->setFile('Source hash: ' . sha1($this->source->source));
        }
        return $error;
    }

    protected function createUnsupportedArgumentError(string $argument, array $definitions): SequencingException
    {
        return $this->createErrorAtPosition(
            sprintf(
                'Undeclared argument: %s. Valid arguments are: %s',
                $argument,
                implode(', ', array_keys($definitions))
            ),
            1558298976
        );
    }

    protected function countCharactersMatchingMask(int $primaryMask, int $offset, int $length): int
    {
        $bytes = &$this->source->bytes;
        $counted = 0;
        ++$offset; // We must start one byte after offset since source byte array index starts with 1. See unpack().
        for ($index = $offset; $index < $this->source->length && $index <= $length && isset($bytes[$index]); $index++) {
            $byte = $bytes[$index];
            if (($primaryMask & (1 << $byte)) && $byte < 64) {
                $counted++;
            }
        }
        return $counted;
    }

    protected function findBytePositionBeforeOffset(int $primaryMask, int $offset): int
    {
        $bytes = &$this->source->bytes;
        for ($index = min($offset, $this->source->length); $index > 0; $index--) {
            if (($primaryMask & (1 << $bytes[$index])) && $bytes[$index] < 64) {
                return $index;
            }
        }
        return 0;
    }

    protected function findBytePositionAfterOffset(int $primaryMask, int $offset): int
    {
        $bytes = &$this->source->bytes;
        for ($index = $offset; $index < $this->source->length; $index++) {
            if (($primaryMask & (1 << $bytes[$index])) && $bytes[$index] < 64) {
                return $index;
            }
        }
        return max($this->source->length, $offset);
    }
}