<?php
namespace TYPO3Fluid\Fluid\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * A key/value store that can be used by ViewHelpers to communicate between each other.
 *
 * @api
 */
class ViewHelperVariableContainer
{

    /**
     * Two-dimensional object array storing the values. The first dimension is the fully qualified ViewHelper name,
     * and the second dimension is the identifier for the data the ViewHelper wants to store.
     *
     * @var array
     */
    protected $objects = [];

    /**
     * @var ViewInterface
     */
    protected $view;

    /**
     * @var VariableProviderInterface[]
     */
    protected $delegates = [];

    /**
     * Push a new delegate variable container to a stack.
     *
     * If a ViewHelper requires a storage to collect variables which, for
     * example, are filled by evaluating the child (tag content) closure,
     * this method can be used to add a special delegate variable container
     * stored in a stack. Once the variables you need to collect have been
     * collected, calling `popDelegateVariableProvider` removes the delegate
     * from the stack.
     *
     * The point of a stack is to avoid resetting a storage every time a
     * ViewHelper is rendered. In the case of `f:render` it means one storage
     * is created and filled for every one call to the ViewHelper.
     *
     * It is VITAL that you also "pop" any delegate you push to this stack!
     *
     * @param VariableProviderInterface $variableProvider
     */
    public function pushDelegateVariableProvider(VariableProviderInterface $variableProvider)
    {
        $this->delegates[] = $variableProvider;
    }

    /**
     * Get the topmost delegate variable container that was previously pushed
     * onto the stack by pushDelegateVariableContainer(). This method returns
     * a reference to the storage that was last added to the stack without
     * removing the variable provider from the stack.
     *
     * Is used in ViewHelpers that assign variables in variable providers in
     * the stack - as a means to get the variable storage used by the "closest
     * parent", e.g. when called in `f:argument` used inside `f:render`, will
     * read the delegate variable provider inserted by that parent `f:render`.
     *
     * @return VariableProviderInterface|null
     */
    public function getTopmostDelegateVariableProvider()
    {
        return end($this->delegates) ?: null;
    }

    /**
     * Return and REMOVE the topmost delegate variable provider. This method
     * must be called after you finish sub-rendering with a delegated variable
     * provider that was added with `pushDelegateVariableProvider`. Calling
     * the method removes the delegate and returns the stack to the previous
     * state it was in.
     *
     * To avoid removing from the stack, use `getTopmostDelegateVariableProvider`.
     *
     * @param string $viewHelperClassName
     * @return VariableProviderInterface|null
     */
    public function popDelegateVariableProvider()
    {
        return array_pop($this->delegates);
    }

    /**
     * Add a variable to the Variable Container. Make sure that $viewHelperName is ALWAYS set
     * to your fully qualified ViewHelper Class Name
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param string $key Key of the data
     * @param mixed $value The value to store
     * @return void
     * @api
     */
    public function add($viewHelperName, $key, $value)
    {
        $this->addOrUpdate($viewHelperName, $key, $value);
    }

    /**
     * Adds, or overrides recursively, all current variables defined in associative
     * array or Traversable (with string keys!).
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param array|\Traversable $variables An associative array of all variables to add
     * @return void
     * @api
     */
    public function addAll($viewHelperName, $variables)
    {
        if (!is_array($variables) && !$variables instanceof \Traversable) {
            throw new \InvalidArgumentException(
                'Invalid argument type for $variables in ViewHelperVariableContainer->addAll(). Expects array/Traversable ' .
                'but received ' . (is_object($variables) ? get_class($variables) : gettype($variables)),
                1501425195
            );
        }
        $this->objects[$viewHelperName] = array_replace_recursive(
            isset($this->objects[$viewHelperName]) ? $this->objects[$viewHelperName] : [],
            $variables instanceof \Traversable ? iterator_to_array($variables) : $variables
        );
    }

    /**
     * Add a variable to the Variable Container. Make sure that $viewHelperName is ALWAYS set
     * to your fully qualified ViewHelper Class Name.
     * In case the value is already inside, it is silently overridden.
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param string $key Key of the data
     * @param mixed $value The value to store
     * @return void
     */
    public function addOrUpdate($viewHelperName, $key, $value)
    {
        if (!isset($this->objects[$viewHelperName])) {
            $this->objects[$viewHelperName] = [];
        }
        $this->objects[$viewHelperName][$key] = $value;
    }

    /**
     * Gets a variable which is stored
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param string $key Key of the data
     * @param mixed $default Default value to use if no value is found.
     * @return mixed The object stored
     * @api
     */
    public function get($viewHelperName, $key, $default = null)
    {
        return $this->exists($viewHelperName, $key) ? $this->objects[$viewHelperName][$key] : $default;
    }

    /**
     * Gets all variables stored for a particular ViewHelper
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param mixed $default
     * @return array
     */
    public function getAll($viewHelperName, $default = null)
    {
        return array_key_exists($viewHelperName, $this->objects) ? $this->objects[$viewHelperName] : $default;
    }

    /**
     * Determine whether there is a variable stored for the given key
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param string $key Key of the data
     * @return boolean TRUE if a value for the given ViewHelperName / Key is stored, FALSE otherwise.
     * @api
     */
    public function exists($viewHelperName, $key)
    {
        return isset($this->objects[$viewHelperName]) && array_key_exists($key, $this->objects[$viewHelperName]);
    }

    /**
     * Remove a value from the variable container
     *
     * @param string $viewHelperName The ViewHelper Class name (Fully qualified, like "TYPO3Fluid\Fluid\ViewHelpers\ForViewHelper")
     * @param string $key Key of the data to remove
     * @return void
     * @api
     */
    public function remove($viewHelperName, $key)
    {
        unset($this->objects[$viewHelperName][$key]);
    }

    /**
     * Set the view to pass it to ViewHelpers.
     *
     * @param ViewInterface $view View to set
     * @return void
     */
    public function setView(ViewInterface $view)
    {
        $this->view = $view;
    }

    /**
     * Get the view.
     *
     * !!! This is NOT a public API and might still change!!!
     *
     * @return ViewInterface|null The View, or null if view was not set
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * Clean up for serializing.
     *
     * @return array
     */
    public function __sleep()
    {
        return ['objects'];
    }
}
