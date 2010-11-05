<?php
/**
 * PHPMapper
 *
 * PHPMapper is a class for generating a map of the United States with states
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
 * @package     PHPMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @copyright   2010 Andrew Kandels <me@andrewkandels.com>
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://andrewkandels.com/PHPMapper
 * @access      public
 */

$base = dirname(__FILE__);

require_once "$base/PHPMapper/Exception.php";
require_once "$base/PHPMapper/Exception/Geo.php";
require_once "$base/PHPMapper/Exception/Image.php";
require_once "$base/PHPMapper/Exception/Import.php";
require_once "$base/PHPMapper/Exception/BadColorValue.php";
require_once "$base/PHPMapper/Election.php";
require_once "$base/PHPMapper/Map/CSV.php";
require_once "$base/PHPMapper/Map/Image.php";
require_once "$base/PHPMapper/Import.php";
require_once "$base/PHPMapper/Import/CSV.php";
require_once "$base/PHPMapper/Import/PDO.php";
require_once "$base/PHPMapper/Import/Array.php";
require_once "$base/PHPMapper/Import/GeoIP/Raw.php";

class PHPMapper
{
    const MIN_THRESHOLD     = 0.10;     // Lightest alpha to draw. 0.1 = 10% opacity
    const DEFAULT_COUNTRY   = 'US';     // Default 2-letter ISO code
    const MIN_WIDTH         = 50;       // Minimum pixel width

    // Constants used throughout the libraries
    const COUNTRY           = 1;        // 2-Letter ISO Country Code
    const REGION            = 2;        // 2-Letter Region (state in the U.S.)
    const VALUE             = 3;        // 2-Letter Region (state in the U.S.)
    const LOCATIONID        = 4;        // Unique ID column in which to join BLOCK to
    const LATITUDE          = 5;
    const LONGITUDE         = 6;
    const RANGEIPSTART      = 7;
    const RANGEIPEND        = 8;

    // Column is child of...
    const BLOCK             = 1;        // IP range segment that points to a location
    const LOCATION          = 2;        // Singular location (i.e.: a city)

    protected $_areas       = array();
    protected $_color       = '155083'; // Default color
    protected $_targetValue = null;
    protected $_width       = 1000;
    protected $_base        = null;
    protected $_image       = null;

    /**
     * Creates a StateMapper class object.
     *
     * @param   string      Map name (default is us_states)
     * @param   string      Base path (defaults to project path + /maps)
     * @return  void
     * @throws  PHPMapper_Exception
     */
    public function __construct($map = 'world', $base = null)
    {
        $this->setMap($map, $base);
    }

    /**
     * Sets the map in which to draw and loads the region information.
     *
     * @param   string      Map name
     * @param   string      Base directory (defaults to a guess)
     * @return  PHPMapper
     */
    public function setMap($map, $base = null)
    {
        if ($base === null)
        {
            $this->_base = dirname(__FILE__) . '/maps';
        }
        else
        {
            $this->_base = $base;
        }

        $this->_base    = preg_replace('/\/+$/', '', $this->_base) . '/' . $map . '.';
        $this->_image   = new PHPMapper_Map_Image($this->_base . 'png');

        $this->_loadAreas();

        return $this;
    }

    /**
     * Returns the base directory path for the map specified in the
     * constructor.
     *
     * @return  string
     */
    public function getBase()
    {
        return $this->_base;
    }

    /**
     * Returns the image object used to draw the PNG output.
     *
     * @return  PHPMapper_Map_Image
     */
    public function getImage()
    {
        return $this->_image;
    }

    /**
     * Loads the available areas from the map data source and zeroes
     * out the values for each area.
     *
     * @return  void
     */
    private function _loadAreas()
    {
        $csv = new PHPMapper_Map_CSV($this->_base . 'csv');
        $this->_areas = array();

        while ($area = $csv->get())
        {
            if ($area[2] !== null) foreach ($area[2] as $index => $name)
            {
                $area[2][$index] = strtolower(trim($name));
            }

            $this->_areas[$area[0]] = array(
                'country'   => $area[1],
                'names'     => $area[2],
                'series'    => array(1 => 0)
            );
        }
    }

    /**
     * Sets the color for which the most popular item on the map should
     * be shaded to. Other areas will be shaded that color with a lower
     * alpha value, based on their relevance in comparison.
     *
     * @param   mixed       Hex color or RGB array
     * @return  PHPMapper
     */
    public function setColor($color)
    {
        if (is_array($color) && count($color) != 3)
        {
            throw new PHPMapper_Exception(
                'Invalid array to setColor method: accepts a 3-member RGB array or a hex color.'
            );
        }
        else if (is_string($color) && !preg_match('/^[0-9a-f]{6}$/i', $color))
        {
            throw new PHPMapper_Exception(
                'Invalid hex color to setColor method. Should be 6 hexidecimal characters.'
            );
        }
        else
        {
            $this->_color = $color;
        }

        return $this;
    }

    /**
     * Retrieves the color of the map.
     *
     * @return  string
     */
    public function getColor()
    {
        return $this->_color;
    }

    /**
     * Sets the width, in pixels, to be exported by the draw()
     * method. The height will be automatically adjusted to
     * preserve the aspect ratio.
     *
     * @param   integer     Width in pixels
     * @return  PHPMapper
     */
    public function setWidth($width)
    {
        if ($width < self::MIN_WIDTH)
        {
            throw new PHPMapper_Exception(
                'Minimum width must be at least ' . self::MIN_WIDTH . ' pixels.'
            );
        }

        $this->_width = $width;
        return $this;
    }

    /**
     * Retrieves the width (in pixels) the map will be rendered as.
     *
     * @return  integer
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * Imports data from a class extending PHPMapper_Import.
     *
     * @param   PHPMapper_Import
     * @return  PHPMapper
     * @throws  PHPMapper_Exception_Import
     */
    public function import($obj)
    {
        while ($row = $obj->getRowData())
        {
            $series = isset($row[3]) ? (int) $row[3] : 1;
            $this->add($row[0], $row[1], $row[2], $series);
        }

        return $this;
    }

    /**
     * Retrieves the ID for a country/region combination.
     *
     * @param   string      2-letter ISO country code
     * @param   string      Region name
     * @return  PHPMapper
     */
    public function lookup($country, $region = null)
    {
        foreach ($this->_areas as $id => $mp)
        {
            if (!strcasecmp($mp['country'], $country))
            {
                // No regions, country match only
                if ($mp['names'] === null)
                {
                    return $id;
                }

                // Has regions, must be a region match
                if (in_array(strtolower($region), $mp['names']))
                {
                    return $id;
                }
            }
        }

        return false;
    }

    /**
     * Adds to a region's score.
     *
     * @param   string      2-letter ISO country code
     * @param   string      Region name
     * @param   integer     Value to add
     * @param   integer     Series (optional) or defaults to 1
     * @return  PHPMapper
     */
    public function add($country, $region = null, $value = 1, $series = 1)
    {
        if ($id = $this->lookup($country, $region))
        {
            if (!isset($this->_areas[$id]['series'][$series]))
            {
                $this->_areas[$id]['series'][$series] = $value;
            }
            else
            {
                $this->_areas[$id]['series'][$series] += $value;
            }
        }

        return $this;
    }

    /**
     * Sets a region's score.
     *
     * @param   string      2-letter ISO country code
     * @param   string      Region name
     * @param   integer     Value to set
     * @param   integer     Series (optional) or defaults to 1
     * @return  PHPMapper
     */
    public function set($country, $region = null, $value = 1, $series = 1)
    {
        if ($id = $this->lookup($country, $region))
        {
            $this->_areas[$id]['series'][$series] = $value;
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
     * @return  PHPMapper
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
    public function getMaxValue($series = 1)
    {
        if ($this->_targetValue !== null)
        {
            return $this->_targetValue;
        }
        else
        {
            $max = 0;

            foreach ($this->_areas as $id => $mp)
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
     * Retrieves the value for a country/region combination.
     *
     * @param   integer     Area id
     * @param   integer     Series (default 1)
     * @return  integer     Value
     */
    public function get($id, $series = 1)
    {
        return isset($this->_areas[$id]['series'][$series])
            ? $this->_areas[$id]['series'][$series]
            : 0;
    }

    /**
     * Get the alpha shading color (0-1) of an area after
     * comparing it to the max/target value.
     *
     * @param   integer     Area ID
     * @param   integer     Max/target value
     * @param   integer     Series
     * @return  float       0-1 percentage value
     */
    public function getAreaAlphaPct($id, $max, $series = 1)
    {
        $mp = $this->_areas[$id];
        $value = $this->get($id);

        if ($max > 0)
        {
            if (($pct = $value / $max) > 1)
            {
                $pct = 1.0;
            }
        }
        else
        {
            $pct = self::MIN_THRESHOLD;
        }

        return $pct;
    }

    /**
     * Outputs a PNG image reflecting the values provided to add/set.
     *
     * @param   string      File name, or none to write to the browser
     * @param   integer     Compression level (1-10)
     * @param   integer     Series to draw (1 is default)
     * @return  PHPMapper
     */
    public function draw($file = null, $compression = 4, $series = 1)
    {
        $this->_image->setNumAreas(count($this->_areas));
        $max = $this->getMaxValue($series);

        foreach ($this->_areas as $id => $mp)
        {
            $pct = $this->getAreaAlphaPct($id, $max, $series);
            $this->_image->setShading($id, $this->_color, $pct);
        }

        $this->_image->resize($this->_width)
                     ->draw($file, $compression);

        return $this;
    }
}
