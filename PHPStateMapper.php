<?php
/**
 * PHPStateMapper
 *
 * PHPStateMapper is a class for generating a map of the United States with states
 * shaded darker to represent data in a report. It can be thought of as a visual
 * interpretation of "usage by state" and would be an ideal candidate for use in
 * reports on sales, usage, adoption, traffic, and so forth.
 *
 * Copyright (c) 2010, Andrew Kandels <me@andrewkandels.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    Reports
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @copyright   2010 Andrew Kandels <me@andrewkandels.com>
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://andrewkandels.com/PHPStateMapper
 * @access      public
 */

require_once dirname(__FILE__) . '/PHPStateMapper/Exception.php';
require_once dirname(__FILE__) . '/PHPStateMapper/Exception/BadColorValue.php';
require_once dirname(__FILE__) . '/PHPStateMapper/Exception/Image.php';

class PHPStateMapper
{
    const DEFAULT_COLOR = 'BA6807';

    private $_states;
    private $_color;
    private $_targetValue;

    /**
     * Creates a StateMapper class object.
     *
     * @param   string      6-character hex value for color or RGB array
     * @param   array       States with values
     * @param   integer     Target value (see: setTargetValue() doc)
     * @return  void
     * @throws  PHPStateMapper_Exception
     */
    public function __construct($color = false, $states = array(), $targetValue = null)
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefrompng'))
        {
            throw new PHPStateMapper_Exception(
                'The PHP GD extension is required to run PHPStateMapper.'
            );;
        }

        $this->setTargetValue($targetValue);

        $this->_states = array();
        if (!empty($states)) foreach ($states as $name => $value)
        {
            $this->setState($name, $value);
        }

        if (empty($color))
        {
            $this->_color = $this->_convertHexToRgb(self::DEFAULT_COLOR);
        }
        else if (is_array($color))
        {
            if (count($color) != 3)
            {
                throw new PHPStateMapper_Exception_BadColorValue();
            }
            else
            {
                $this->_color = $color;
            }
        }
        else
        {
            $this->_color = $this->_convertHexToRgb($color);
        }
    }

    /**
     * Imports data from a class extending PHPStateMapper_Import.
     *
     * @param   PHPStateMapper_Import
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception_Import
     */
    public function import($obj)
    {
        while ($row = $obj->getRowData())
        {
            $this->addState($row[0], $row[1]);
        }

        return $this;
    }

    /**
     * Converts a hexidecimal representation of a color (think: XHTML/CSS) into
     * an RGB 3-item array.
     *
     * @param   string      Hexidecimal color representation
     * @return  array       (R,G,B)
     */
    private function _convertHexToRgb($hex)
    {
        list($r, $g, $b) = array(
            $hex[0] . $hex[1],
            $hex[2] . $hex[3],
            $hex[4] . $hex[5]
        );

        return array(hexdec($r), hexdec($g), hexdec($b));
    }

    /**
     * Converts a state name into a file location for the state's clipart. If the
     * file does not exist, it returns false.
     *
     * @param   string      State name (2-digit U.S. Postal abbreviation)
     * @return  string      File name or (boolean) false if state does not exist
     */
    private function _getFileFromState($name)
    {
        $file = sprintf('%s/images/%2s.png', dirname(__FILE__), strtolower($name));

        // Does the state exist?
        if (!file_exists($file))
        {
            return false;
        }
        else
        {
            return $file;
        }
    }

    /**
     * Adds a value (or if not specified, 1) to the number of items belonging
     * to a state.
     *
     * @param   string      State name (2-digit U.S. Postal abbreviation)
     * @param   integer     Optional number to increase the count by (default 1)
     * @return  StateMapper or false, if state is invalid
     */
    public function addState($name, $add = 1)
    {
        if (!$file = $this->_getFileFromState($name))
        {
            return false;
        }

        if (!isset($this->_states[$file]))
        {
            $this->_states[$file] = $add;
        }
        else
        {
            $this->_states[$file] += $add;
        }

        return $this;
    }

    /**
     * By default, the state with the highest value assigned to it
     * is used as a "grading curve" and is displayed as the
     * darkest value on the map. If you're prefer to use a
     * target/forecasted value instead, you can set it here.
     *
     * @param   integer     Target/Forecast value to compare against
     * @return  PHPStateMapper
     */
    public function setTargetValue($value)
    {
        $this->_targetValue = $value;
    }

    /**
     * Returns the value for the state with the most values, or
     * the target/forecast if set with setTargetValue().
     *
     * @return  integer
     */
    private function _getMaxValue()
    {
        if ($this->_targetValue !== null)
        {
            return $this->_targetValue;
        }
        else
        {
            $values = array_values($this->_states);
            sort($values);
            return array_pop($values);
        }
    }

    /**
     * Sets the number of items belonging to a state.
     *
     * @param   string      State name (2-digit U.S. Postal abbreviation)
     * @param   integer     Number of items belonging to the state
     * @return  StateMapper or false, if state is invalid
     */
    public function setState($name, $value)
    {
        if (!$file = $this->_getFileFromState($name))
        {
            return false;
        }

        $this->_states[$file] = $value;
    }

    /**
     * Similar to imagepng(). If no file is given, it prints headers and
     * draws the image directly to the browser. If a file is given, it instead
     * fills it with the contents of the image.
     *
     * @param   string      File name (or null, for output)
     * @param   integer     Compression level (0-9)
     * @return  boolean     Success?
     * @throws  PHPStateMapper_Exception
     */
    public function draw($file = null, $compression = 0)
    {
        if (!$img = imagecreatefrompng($tmp = dirname(__FILE__) . '/images/united_states.png'))
        {
            throw new PHPStateMapper_Exception_Image("Failed to load $tmp");
        }

        $width  = imagesx($img);
        $height = imagesy($img);
        $max    = $this->_getMaxValue();

        foreach ($this->_states as $name => $value)
        {
            if ($value <= 0) continue;

            if (!$subImg = imagecreatefrompng($name))
            {
                throw new PHPStateMapper_Exception_Image("Failed to open $name");
            }

            $index = imagecolorclosest($subImg, 236, 201, 79);
            list($r, $g, $b) = $this->_color;
            imagecolorset($subImg, $index, $r, $g, $b);

            // Make top/left pixel the transparent color (white)
            imagecolortransparent($subImg, imagecolorat($subImg, 0, 0));

            // Color in the state most popular being darker, using an alpha
            $pct = ceil($value / $max * 100);

            // Copy smaller image over the map
            imagecopymerge($img, $subImg, 0, 0, 0, 0, $width, $height, $pct);
        }

        if (!$file)
        {
            header('Content-type: image/png');
        }

        if (!imagepng($img, $file, $compression))
        {
            throw new PHPStateMapper_Exception_Image("Failed to create $file");
        }
        else
        {
            return true;
        }
    }
}
