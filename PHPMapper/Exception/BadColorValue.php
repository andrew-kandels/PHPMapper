<?php
/**
 * PHPMapper_Exception_BadColorValue
 *
 * Thrown if PHPMapper is given an invalid color value.
 *
 * @package     PHPMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */
class PHPMapper_Exception_BadColorValue extends PHPMapper_Exception
{
    public function __construct()
    {
        parent::__construct('Bad color value: accepts either a 6-character '
            . 'hexidecimal value or a 3-item RGB array.'
        );
    }
}
