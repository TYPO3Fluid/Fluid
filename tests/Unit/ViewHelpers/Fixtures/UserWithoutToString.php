<?php
namespace TYPO3Fluid\Fluid\Tests\Unit\ViewHelpers\Fixtures;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

/**
 * Dummy object to test Viewhelper behavior on objects without a __toString method
 */
class UserWithoutToString
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return boolean
     */
    public function isNamed()
    {
        return !empty($this->name);
    }

    /**
     * @return boolean
     */
    public function hasHasAccessor()
    {
        return !empty($this->name);
    }

    /**
     * @return boolean
     */
    public function isIsAccessor()
    {
        return !empty($this->name);
    }
}
