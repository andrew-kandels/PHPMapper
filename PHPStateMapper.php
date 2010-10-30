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
    const DEFAULT_COLOR = '155083';
    const DEFAULT_MAP = 'us_states';
    const MIN_THRESHOLD = 0.10;

    private $_items;
    private $_color;
    private $_targetValue;
    private $_width;

    /**
     * Creates a StateMapper class object.
     *
     * @param   integer     Width in Pixels
     * @param   string      6-character hex value for color or RGB array
     * @param   array       Items with values
     * @param   integer     Target value (see: setTargetValue() doc)
     * @return  void
     * @throws  PHPStateMapper_Exception
     */
    public function __construct($width = 500, $color = false,
        $data = array(), $targetValue = null)
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefrompng'))
        {
            throw new PHPStateMapper_Exception(
                'The PHP GD extension is required to run PHPStateMapper.'
            );;
        }

        $this->_width = $width;
        if ($this->_width > 2000 || $this->_width < 100)
        {
            $this->_width = 500;
        }

        $this->_loadItems();
        $this->setTargetValue($targetValue);

        if (!empty($data)) foreach ($data as $name => $value)
        {
            $this->setItem($name, $value);
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
     * Loads the item data from the CSV file which is used to locate the
     * data and assign them to areas on the map.
     *
     * @return  void
     */
    private function _loadItems()
    {
        $this->_items = array();

        $csv = dirname(__FILE__) . '/maps/' . self::DEFAULT_MAP . '.csv';
        if (!$file = fopen($csv, 'rt'))
        {
            throw new PHPStateMapper_Exception("Failed to open $csv map data.");
        }

        while (!feof($file) && $line = fgetcsv($file, 1024, "\t"))
        {
            $this->_items[(int) $line[0]] = array(
                'names'     => array(),
                'series'    => array(1 => 0)
            );

            // Additional names for lookup
            for ($i = 1; $i < count($line); $i++)
            {
                $this->_items[(int) $line[0]]['names'][] = strtolower($line[$i]);
            }
        }

        fclose($file);
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
            $this->addItem($row[0], $row[1]);
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
     * Converts a RGB representation of a color to a hexidecimal one.
     *
     * @param   integer     Red
     * @param   integer     Green
     * @param   integer     Blue
     * @return  string      Hex
     */
    private function _convertRgbToHex($r, $g, $b)
    {
        $r = dechex($r<0?0:($r>255?255:$r));
        $g = dechex($g<0?0:($g>255?255:$g));
        $b = dechex($b<0?0:($b>255?255:$b));

        $color = (strlen($r) < 2?'0':'').$r;
        $color .= (strlen($g) < 2?'0':'').$g;
        $color .= (strlen($b) < 2?'0':'').$b;

        return $color;
    }

    /**
     * Adds a value (or if not specified, 1) to the number of items belonging
     * to an item.
     *
     * @param   string      Item name (i.e.: MN or Minnesota)
     * @param   integer     Optional number to increase the count by (default 1)
     * @param   integer     Series, defaults to 1
     * @return  StateMapper or false, if state is invalid
     */
    public function addItem($name, $add = 1, $series = 1)
    {
        if ($id = $this->_getItemId($name))
        {
            if (!isset($this->_items['series'][$series]))
            {
                $this->_items[$id]['series'][$series] = $add;
            }
            else
            {
                $this->_items[$id]['series'][$series] += $add;
            }

            return $this;
        }
        else
        {
            return false;
        }
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
     * @param   integer     Series (default 1)
     * @return  integer
     */
    private function _getMaxValue($series = 1)
    {
        if ($this->_targetValue !== null)
        {
            return $this->_targetValue;
        }
        else
        {
            $max = 0;

            foreach ($this->_items as $id => $mp)
            {
                if (isset($mp['series'][$series]) && $mp['series'][$series] > $max)
                {
                    $max = $mp['series'][$series];
                }
            }

            return $max;
        }
    }

    /**
     * Looks up an item by its name key in the map's data file.
     * Returns its ID which is used to locate it on the map.
     *
     * @param   string      Name (i.e.: Minnesota)
     * @return  integer     Id
     */
    private function _getItemId($name)
    {
        foreach ($this->_items as $id => $mp)
        {
            if (in_array(strtolower($name), $mp['names']))
            {
                return $id;
            }
        }

        return false;
    }

    /**
     * Sets the number of items belonging to a state.
     *
     * @param   string      State name (2-digit U.S. Postal abbreviation)
     * @param   integer     Number of items belonging to the state
     * @param   integer     Series (default 1)
     * @return  StateMapper or false, if state is invalid
     */
    public function setItem($name, $value, $series = 1)
    {
        if ($id = $this->_getItemId($name))
        {
            $this->_items[$id]['series'][$series] = $value;

            return $this;
        }
        else
        {
            return false;
        }
    }

    /**
     * Returns the color setting with its intensity set to the item's
     * value.
     *
     * @param   float       Percentage of intensity
     * @return  array       (R,G,B)
     */
    private function _getColorAlpha($pct)
    {
        return array(
            ((1 - $pct) * 255) + ($pct * $this->_color[0]),
            ((1 - $pct) * 255) + ($pct * $this->_color[1]),
            ((1 - $pct) * 255) + ($pct * $this->_color[2])
        );
    }

    /**
     * Similar to imagepng(). If no file is given, it prints headers and
     * draws the image directly to the browser. If a file is given, it instead
     * fills it with the contents of the image.
     *
     * @param   string      File name (or null, for output)
     * @param   integer     Compression level (0-9)
     * @param   integer     Series (default 1)
     * @return  boolean     Success?
     * @throws  PHPStateMapper_Exception
     */
    public function draw($file = null, $compression = 0, $series = 1)
    {
        $tmp = dirname(__FILE__) . '/maps/' . self::DEFAULT_MAP . '.png';
        if (!$img = imagecreatefrompng($tmp))
        {
            throw new PHPStateMapper_Exception_Image("Failed to load $tmp");
        }

        $size   = getimagesize($tmp);
        $max    = $this->_getMaxValue();
        $num    = count($this->_items) - 1;

        // Clean up our source image to remove all but actual item data
        for ($i = 0; $i < imagecolorstotal($img); $i++)
        {
            $raw = imagecolorsforindex($img, $i);
            $hex = $this->_convertRgbToHex($raw['red'], $raw['green'], $raw['blue']);
            if (($raw['red'] != $raw['green'] || $raw['green'] != $raw['blue'] ||
                $raw['red'] > $num) && $hex != 'ffffff')
            {
                imagecolorset($img, $i, 255, 255, 255);
            }
        }

        foreach ($this->_items as $id => $mp)
        {
            // Get the color assigned to the item
            $rs = $gs = $bs = $id - 1;
            $index = imagecolorexact($img, $rs, $gs, $bs);

            if (!isset($mp['series'][$series]) ||
                ($value = $mp['series'][$series]) <= 0)
            {
                $pct = 0;
            }
            else
            {
                $pct = $value / $max;
            }

            if ($pct < self::MIN_THRESHOLD)
            {
                $pct = self::MIN_THRESHOLD;
            }

            list($rt, $gt, $bt) = $this->_getColorAlpha($pct);

            imagecolorset($img, $index, $rt, $gt, $bt);
        }

        $ratio  = $size[1] / $size[0];
        $height = floor($this->_width * $ratio);
        $out    = imagecreate($this->_width, $height);

        imagealphablending($out, false);
        imagesavealpha($out, false);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $this->_width, $height, $size[0], $size[1]);

        if (!$file)
        {
            header('Content-type: image/png');
        }

        if (!imagepng($out, $file, $compression))
        {
            throw new PHPStateMapper_Exception_Image("Failed to create $file");
        }
        else
        {
            imagedestroy($img);
            imagedestroy($out);

            return true;
        }
    }
}
