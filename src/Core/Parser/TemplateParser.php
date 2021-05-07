<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Core\Parser;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Component\Argument\ArgumentCollection;
use TYPO3Fluid\Fluid\Component\ComponentInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Template parser building up an object syntax tree
 */
class TemplateParser
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var RenderingContextInterface
     */
    protected $renderingContext;

    /**
     * @var ComponentInterface[]
     */
    protected $stack = [];

    /**
     * @var Source[]
     */
    protected $sources = [];

    public function __construct(RenderingContextInterface $renderingContext)
    {
        $this->renderingContext = $renderingContext;
        $this->configuration = $renderingContext->getParserConfiguration();
    }

    public function getComponentBeingParsed(): ?ComponentInterface
    {
        return end($this->stack) ?: null;
    }

    public function parseFile(string $templatePathAndFilename, ?Configuration $configuration = null): ComponentInterface
    {
        $hash = sha1_file($templatePathAndFilename);
        $source = $this->sources[$hash] ?? ($this->sources[$hash] = new FileSource($templatePathAndFilename));
        if (!($configuration ?? $this->configuration)->isFeatureEnabled(Configuration::FEATURE_RUNTIME_CACHE)) {
            return ($this->stack[$hash] ?? $this->parse($source));
        }
        static $cache = [];
        if (isset($cache[$hash])) {
            return $cache[$hash];
        }
        return $cache[$hash] = ($this->stack[$hash] ?? $this->parse($source));
    }

    /**
     * Parses a given template string and returns a parsed template object.
     *
     * The resulting ParsedTemplate can then be rendered by calling evaluate() on it.
     *
     * Normally, you should use a subclass of AbstractTemplateView instead of calling the
     * TemplateParser directly.
     *
     * @param Source $source Template source instance
     * @param Configuration|null $configuration Template parsing configuration to use
     * @return ComponentInterface Parsed template
     * @throws Exception
     */
    public function parse(Source $source, ?Configuration $configuration = null): ComponentInterface
    {
        $hash = sha1($source->source);
        $contexts = new Contexts();
        $sequencer = new Sequencer(
            $this->renderingContext,
            $contexts,
            $source,
            $configuration ?? $this->configuration
        );
        // Recursion support: triggering parsing of the same source file from within the file returns a reference
        // to the still unfinished EntryNode created by the Sequencer. The returned instance still does not have all
        // child nodes until the Sequencer has finished. The second time the template is parsed the temporary EntryNode
        // is returned to prevent infinite recursion.
        // The first instance now contains a circular reference to itself, as a branch nested in children somewhere.
        // Any subsequent usages of the same source creates additional references to the original/root/parent.
        $this->stack[$hash] = $sequencer->getComponent();
        $component = $sequencer->sequence();
        array_pop($this->stack);
        return $component;
    }

    /**
     * @param string $templateIdentifier
     * @param \Closure $templateSourceClosure Closure which returns the template source if needed
     * @return ComponentInterface
     */
    public function getOrParseAndStoreTemplate(string $templateIdentifier, \Closure $templateSourceClosure): ComponentInterface
    {
        if (!$this->configuration->isFeatureEnabled(Configuration::FEATURE_RUNTIME_CACHE)) {
            return $this->parseTemplateSource($templateIdentifier, $templateSourceClosure);
        }
        static $cache = [];
        if (!isset($cache[$templateIdentifier])) {
            $cache[$templateIdentifier] = $this->parseTemplateSource($templateIdentifier, $templateSourceClosure);
        }
        return $cache[$templateIdentifier]->setArguments(new ArgumentCollection());
    }

    /**
     * @param string $templateIdentifier
     * @param \Closure $templateSourceClosure
     * @return ComponentInterface
     */
    protected function parseTemplateSource(string $templateIdentifier, \Closure $templateSourceClosure): ComponentInterface
    {
        $parsedTemplate = $this->parse(
            new Source($templateSourceClosure($this->renderingContext)),
            $this->renderingContext->getParserConfiguration()
        );
        //$parsedTemplate->setIdentifier($templateIdentifier);
        return $parsedTemplate;
    }
}
