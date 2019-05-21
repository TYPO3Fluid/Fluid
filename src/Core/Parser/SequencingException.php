<?php
declare(strict_types=1);

namespace TYPO3Fluid\Fluid\Core\Parser;

class SequencingException extends Exception
{
    /** @var ?Position */
    private $position;

    public function setPosition(Position $position): void
    {
        $this->position = $position;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }
}