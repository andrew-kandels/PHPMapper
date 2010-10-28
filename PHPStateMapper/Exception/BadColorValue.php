<?php
/**
 * PHPStateMapper_Exception_BadColorValue
 *
 * Thrown if PHPStateMapper is given an invalid color value.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */
class PHPStateMapper_Exception_BadColorValue extends PHPStateMapper_Exception
{
    public function __construct()
    {
        parent::__construct('Bad color value: accepts either a 6-character '
            . 'hexidecimal value or a 3-item RGB array.'
        );
    }
}
