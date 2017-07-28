<?php
namespace TYPO3Fluid\Fluid\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

/**
 * Argument definition of each view helper argument
 */
class ArgumentDefinition
{

    /**
     * Name of argument
     *
     * @var string
     */
    protected $name;

    /**
     * Type of argument
     *
     * @var string
     */
    protected $type;

    /**
     * Description of argument
     *
     * @var string
     */
    protected $description;

    /**
     * Is argument required?
     *
     * @var boolean
     */
    protected $required = false;

    /**
     * Default value for argument
     *
     * @var mixed
     */
    protected $defaultValue = null;

    /**
     * Defines, if the ObjectAccessNodes inside this argument gets escaped
     *
     * @var boolean
     */
    protected $escaping = false;

    /**
     * Constructor for this argument definition.
     *
     * @param string $name Name of argument
     * @param string $type Type of argument
     * @param string $description Description of argument
     * @param boolean $required TRUE if argument is required
     * @param mixed $defaultValue Default value
     */
    public function __construct($name, $type, $description = null, $required = false, $defaultValue = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->required = $required;
        $this->defaultValue = $defaultValue;
    }

    /**
     * Get the name of the argument
     *
     * @return string Name of argument
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the type of the argument
     *
     * @return string Type of argument
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get the description of the argument
     *
     * @return string Description of argument
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return ArgumentDefinition
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the optionality of the argument
     *
     * @return boolean TRUE if argument is optional
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Make this argument required
     *
     * @param bool $required
     * @return ArgumentDefinition
     */
    public function setRequired($required)
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Get the default value, if set
     *
     * @return mixed Default value
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Sets a default value for this argument
     *
     * @param mixed $defaultValue
     * @return ArgumentDefinition
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEscaped()
    {
        return $this->escaping;
    }

    /**
     * @param bool $escaping
     * @return ArgumentDefinition
     */
    public function setEscaping($escaping)
    {
        $this->escaping = $escaping;
        return $this;
    }

}
