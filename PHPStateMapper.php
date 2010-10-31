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
    // Default color for the darkest point on the map
    const DEFAULT_COLOR =   '155083';
    // Default map to draw and collect values for
    const DEFAULT_MAP =     'us_states';
    // Default width in pixels
    const DEFAULT_WIDTH =   500;
    // Lightest alpha to draw. 0.1 = 10% opacity of DEFAULT_COLOR
    const MIN_THRESHOLD =   0.10;

    protected $_items;
    protected $_color;
    protected $_targetValue;
    protected $_width;
    protected $_maxWidth;
    protected $_maxHeight;
    protected $_base;

    /**
     * Creates a StateMapper class object.
     *
     * @param   string      Map name (default is us_states)
     * @param   string      Base path (defaults to project path + /maps)
     * @return  void
     * @throws  PHPStateMapper_Exception
     */
    public function __construct($map = 'us_states', $base = null)
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefrompng'))
        {
            throw new PHPStateMapper_Exception(
                'The PHP GD extension is required to run PHPStateMapper.'
            );;
        }

        $this->_base = $base;

        $this->setMap($map)
             ->setColor(self::DEFAULT_COLOR)
             ->setWidth(self::DEFAULT_WIDTH)
             ->_loadItems();
    }

    /**
     * Sets the map to draw. It should have a data file (.csv),
     * and an image (.png) in the ./maps/ directory.
     *
     * @param   string      Map base file name (no extension)
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception
     */
    public function setMap($map)
    {
        if ($this->_base === null)
        {
            $this->_base = dirname(__FILE__) . '/';
        }

        if (!preg_match('/\/$/', $this->_base))
        {
            $this->_base .= '/';
        }

        $base = $this->_base . "maps";

        if (!file_exists($this->_image = "$base/$map.png"))
        {
            throw new PHPStateMapper_Exception(
                "Map image file $map not found at {$this->_image}."
            );
        }

        if (!file_exists($this->_data = "$base/$map.csv"))
        {
            throw new PHPStateMapper_Exception(
                "No map csv data file $map found in $base."
            );
        }

        list($this->_maxWidth, $this->_maxHeight) = getimagesize($this->_image);

        return $this;
    }

    /**
     * Sets the color for which the most popular item on the map should
     * be shaded to. Other areas will be shaded that color with a lower
     * alpha value, based on their relevance in comparison.
     *
     * @param   mixed       Hex color or RGB array
     * @return  PHPStateMapper
     */
    public function setColor($color)
    {
        $this->_color = $this->_getRgbColorFromInput($color);

        return $this;
    }

    /**
     * Sets the width, in pixels, to be exported by the draw()
     * method. The height will be automatically adjusted to
     * preserve the aspect ratio.
     *
     * @param   integer     Width in pixels
     * @return  PHPStateMapper
     */
    public function setWidth($width)
    {
        if ($width > $this->_maxWidth)
        {
            $this->_width = $this->_maxWidth;
        }
        else if ($width < 100)
        {
            $this->_width = 100;
        }
        else
        {
            $this->_width = $width;
        }

        return $this;
    }

    /**
     * Loads the item data from the CSV file which is used to locate the
     * data and assign them to areas on the map.
     *
     * @return  void
     */
    protected function _loadItems()
    {
        $this->_items = array();

        if (!$file = fopen($this->_data, 'rt'))
        {
            throw new PHPStateMapper_Exception("Failed to open {$this->_data} map data.");
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
     * Converts a hexidecimal representation of a color (think: XHTML/CSS) into
     * an RGB 3-item array.
     *
     * @param   string      Hexidecimal color representation
     * @return  array       (R,G,B)
     */
    protected function _convertHexToRgb($hex)
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
    protected function _convertRgbToHex($r, $g, $b)
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
     * Adds a value (or if not specified, 1) to the number of items belonging
     * to an item.
     *
     * @param   string      Item name (i.e.: MN or Minnesota)
     * @param   integer     Optional number to increase the count by (default 1)
     * @param   integer     Series, defaults to 1
     * @return  PHPStateMapper
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
        }

        return $this;
    }

    /**
     * Sets the number of items belonging to a state.
     *
     * @param   string      State name (2-digit U.S. Postal abbreviation)
     * @param   integer     Number of items belonging to the state
     * @param   integer     Series (default 1)
     * @return  PHPStateMapper
     */
    public function setItem($name, $value, $series = 1)
    {
        if ($id = $this->_getItemId($name))
        {
            $this->_items[$id]['series'][$series] = $value;
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

        return $this;
    }

    /**
     * Returns the value for the state with the most values, or
     * the target/forecast if set with setTargetValue().
     *
     * @param   integer     Series (default 1)
     * @return  integer
     */
    protected function _getMaxValue($series = 1)
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
    protected function _getItemId($name)
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
     * Returns the color setting with its intensity set to the item's
     * value.
     *
     * @param   float       Percentage of intensity
     * @param   array       RGB color or empty for setColor value
     * @return  array       (R,G,B)
     */
    protected function _getColorAlpha($pct, $color = null)
    {
        if ($pct > 1) $pct = 1;
        if ($color === null) $color = $this->_color;
        return array(
            ((1 - $pct) * 255) + ($pct * $color[0]),
            ((1 - $pct) * 255) + ($pct * $color[1]),
            ((1 - $pct) * 255) + ($pct * $color[2])
        );
    }

    /**
     * Returns the gd image object after removing all invalid colors.
     *
     * @return  object
     * @throws PHPStateMapper_Exception_Image
     */
    protected function _getCleanImage()
    {
        if (!$img = imagecreatefrompng($this->_image))
        {
            throw new PHPStateMapper_Exception_Image("Failed to load {$this->_image}.");
        }

        $num = count($this->_items);

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

        return $img;
    }

    /**
     * Resizes the source image to the width provided by setWidth() or
     * DEFAULT_WIDTH while maintaining aspect ratio of the height.
     *
     * @param   object      Raw GD image object
     * @return  object      Resized GD image object
     */
    protected function _getResizedImage($img)
    {
        // Resize the image while maintaining ratio
        $ratio  = $this->_maxHeight / $this->_maxWidth;
        $height = floor($this->_width * $ratio);
        $out    = imagecreate($this->_width, $height);

        imagealphablending($out, false);
        imagesavealpha($out, false);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $this->_width, $height,
            $this->_maxWidth, $this->_maxHeight
        );
        imagedestroy($img);

        return $out;
    }

    /**
     * Outputs a GD image object either to the browser or to a file.
     *
     * @param   object      GD image object
     * @param   string      File name or null for standard out
     * @return  void
     * @throws  PHPStateMapper_Exception_Image
     */
    protected function _outputImage($img, $file = null, $compression = 4)
    {
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
            imagedestroy($img);
        }

        if (!$file)
        {
            die();
        }
    }

    /**
     * Takes the input value of a color in either RGB or as a hex string
     * and converts it to an RGB array.
     *
     * @param   mixed       RGB color or hex string
     * @return  array       RGB
     * @throws  PHPStateMapper_Exception_BadColorValue
     */
    protected function _getRgbColorFromInput($color)
    {
        if (is_array($color))
        {
            if (count($color) != 3)
            {
                throw new PHPStateMapper_Exception_BadColorValue();
            }
            else
            {
                $color = $color;
            }
        }
        else
        {
            $color = $this->_convertHexToRgb($color);
        }

        return $color;
    }

    /**
     * Similar to imagepng(). If no file is given, it prints headers and
     * draws the image directly to the browser. If a file is given, it instead
     * fills it with the contents of the image.
     *
     * @param   string      File name (or null, for output)
     * @param   integer     Compression level (0-9)
     * @param   integer     Series (default 1)
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception
     */
    public function draw($file = null, $compression = 4, $series = 1)
    {
        $max    = $this->_getMaxValue();
        $img    = $this->_getCleanImage();

        // Shade in each item
        foreach ($this->_items as $id => $mp)
        {
            // Get the color assigned to the item
            $rs = $gs = $bs = $id;
            $index = imagecolorexact($img, $rs, $gs, $bs);

            // Get the alpha shading percentage
            $pct = !isset($mp['series'][$series]) || ($value = $mp['series'][$series]) <= 0
                ? 0
                : $value / $max;

            // Pull up to the minimum to avoid white-out
            if ($pct < self::MIN_THRESHOLD) $pct = self::MIN_THRESHOLD;

            // Get the color after applying the alpha
            list($rt, $gt, $bt) = $this->_getColorAlpha($pct);

            // Add the color to the fixed pallette
            imagecolorset($img, $index, $rt, $gt, $bt);
        }

        // Draw or write the image to disk
        $this->_outputImage($this->_getResizedImage($img), $file, $compression);

        return $this;
    }
}
