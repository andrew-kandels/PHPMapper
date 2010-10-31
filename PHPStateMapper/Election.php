<?php
/**
 * PHPStateMapper_Election
 *
 * A class to generate a map for which states are shaded in one of
 * several colors by whichever has the higher of a series of values.
 *
 * Example:
 * In U.S. elections, a U.S. state map would be colored red for
 * republican states, blue for democratic and grey for undecided.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */

require_once dirname(__FILE__) . '/../PHPStateMapper.php';

class PHPStateMapper_Election extends PHPStateMapper
{
    // Too close to call if: If top 2 winners are within this
    const DEFAULT_TCTC_THRESHOLD = 1;
    // Too close to call if: The winning value is less than this
    const DEFAULT_TCTC_MIN_VALUE = 1;
    // Default too close to call color
    const DEFAULT_TCTC_COLOR = '666666';
    // Shading (see setShading() method)
    const DEFAULT_SHADING = false;
    // Default no-value color
    const DEFAULT_NO_VALUE_COLOR = 'c0c0c0';

    private $_parties;
    private $_tctcColor;
    private $_tctcThreshold;
    private $_tctcMinValue;
    private $_hasShading;

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
        parent::__construct($map, $base);

        $this->setTooCloseToCallThreshold(self::DEFAULT_TCTC_THRESHOLD)
             ->setTooCloseToCallMinValue(self::DEFAULT_TCTC_MIN_VALUE)
             ->setTooCloseToCallColor(self::DEFAULT_TCTC_COLOR)
             ->setShading(self::DEFAULT_SHADING)
             ->setColor(self::DEFAULT_NO_VALUE_COLOR);
    }

    /**
     * Sets shading (on/off). If enabled, the margin of the
     * states victory (if there is one) will be visualized with
     * an alpha. The darker the color, the bigger the win.
     *
     * @param   boolean     On/off
     * @return  PHPStateMapper_Election
     */
    public function setShading($value)
    {
        $this->_hasShading = (boolean) $value;

        return $this;
    }

    /**
     * Sets the criteria for too close to call threshold.
     * If the two winners are within this value, the state is
     * drawn as too close to call.
     *
     * @param   mixed       RGB array or hex string
     * @return  PHPStateMapper_Election
     */
    public function setTooCloseToCallThreshold($value)
    {
        $this->_tctcThreshold = $value;

        return $this;
    }

    /**
     * Sets the criteria for too close to call minimum value.
     * If the winning value is less than the value, the state
     * is drawn too close to call.
     *
     * @param   mixed       RGB array or hex string
     * @return  PHPStateMapper_Election
     */
    public function setTooCloseToCallMinValue($value)
    {
        $this->_tctcMinValue = $value;

        return $this;
    }

    /**
     * Sets the too close to call color to draw states which match
     * this criteria.
     *
     * @param   mixed       RGB array or hex string
     * @return  PHPStateMapper_Election
     * @throws  PHPStateMapper_Exception_BadColorValue
     */
    public function setTooCloseToCallColor($color)
    {
        $this->_tctcColor = $this->_getRgbColorFromInput($color);

        return $this;
    }

    /**
     * Adds a party which acts as a series. Each state should report
     * a value for each added party. Parties with the highest number
     * per state (barring too close to call) determine the color the
     * state is shaded.
     *
     * @param   string      Name of party
     * @param   mixed       Hex color or RGB array
     * @return  PHPStateMapper_Election
     * @throws  PHPStateMapper_Exception_BadColorValue
     */
    public function addParty($name, $color)
    {
        if ($this->_parties === null)
        {
            $this->_parties = array();
        }

        $this->_parties[count($this->_parties) + 1] = array(
            'name'  => $name,
            'color' => $this->_getRgbColorFromInput($color)
        );

        return $this;
    }

    /**
     * Adds parties which act as series. Each state should report
     * a value for each added party. Parties with the highest number
     * per state (barring too close to call) determine the color the
     * state is shaded.
     *
     * Values should be passed in an array of arrays such as:
     *
     * array(
     *     array('Party 1', 'ff0000')
     *     array('Party 2', '0000ff')
     * )
     *
     * @param   string      Name of party
     * @param   mixed       Hex color or RGB array
     * @return  PHPStateMapper_Election
     * @throws  PHPStateMapper_Exception_BadColorValue
     */
    public function setParties($parties)
    {
        foreach ($parties as $name => $color)
        {
            $this->addSeries($name, $color);
        }

        return $this;
    }

    /**
     * Searches for a party by id or name and returns its
     * id. Obviously for the former in search, this is merely
     * a validator.
     *
     * @param   mixed       ID or string name
     * @return  integer     Party index id # or (boolean) false on error
     */
    private function _getPartyId($search)
    {
        if (is_int($search) && isset($this->_parties[$search]))
        {
            return $search;
        }
        else
        {
            foreach ($this->_parties as $id => $mp)
            {
                if (!strcasecmp($mp['name'], $search))
                {
                    return $id;
                }
            }
        }

        return false;
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
     * @param   string      Party name or ID # (from addParty() method)
     * @param   string      Item name (i.e.: MN or Minnesota)
     * @param   integer     Optional number to increase the count by (default 1)
     * @return  PHPStateMapper
     */
    public function addItem($party, $name, $add = 1)
    {
        if (!$partyId = $this->_getPartyId($party))
        {
            throw new PHPStateMapper_Exception("Party $party does not exist. "
                . 'Call addParty() first.'
            );
        }

        parent::addItem($name, $add, $partyId);

        return $this;
    }

    /**
     * Sets the number of items belonging to a state.
     *
     * @param   string      Party name or ID # (from addParty() method)
     * @param   string      Item name (i.e.: MN or Minnesota)
     * @param   integer     Number of items belonging to the state
     * @param   integer     Series (default 1)
     * @return  PHPStateMapper
     */
    public function setItem($name, $value, $series = 1)
    {
        if (!$partyId = $this->_getPartyId($party))
        {
            throw new PHPStateMapper_Exception("Party $party does not exist. "
                . 'Call addParty() first.'
            );
        }

        parent::setItem($name, $value, $partyId);

        return $this;
    }

    /**
     * Similar to imagepng(). If no file is given, it prints headers and
     * draws the image directly to the browser. If a file is given, it instead
     * fills it with the contents of the image.
     *
     * @param   string      File name (or null, for output)
     * @param   integer     Compression level (0-9)
     * @return  PHPStateMapper_Election
     * @throws  PHPStateMapper_Exception
     */
    public function draw($file = null, $compression = 4)
    {
        $max = $this->_getMaxValue();
        $img = $this->_getCleanImage();

        // Shade in each item
        foreach ($this->_items as $id => $mp)
        {
            $series     = $mp['series'];
            arsort($series);

            $names      = array_keys($series);
            $values     = array_values($series);
            $num        = count($series);
            $threshold  = $this->_tctcThreshold;
            $minValue   = $this->_tctcMinValue;
            $tctcColor  = $this->_tctcColor;
            $hasShading = $this->_hasShading;

            // No data
            if (!$num || empty($values[0]))
            {
                $color = $this->_color;
            }
            // Too close to call: threshold
            else if ($num >= 2 && ($values[0] - $values[1]) <= $threshold)
            {
                $color = $tctcColor;
            }
            // Too close to call: min value
            else if ($values[0] <= $minValue)
            {
                $color = $tctcColor;
            }
            // There's a winner!
            else
            {
                $color = $this->_parties[$names[0]]['color'];
            }

            // Get the color assigned to the item
            $rs = $gs = $bs = $id;
            $index = imagecolorexact($img, $rs, $gs, $bs);

            // Get the alpha shading percentage
            $pct = $hasShading && $num >= 2
                ? (1 - $values[1] / ($values[0] + $values[1]))
                : 1;

            // Pull up to the minimum to avoid white-out
            if ($pct < self::MIN_THRESHOLD) $pct = self::MIN_THRESHOLD;

            // Get the color after applying the alpha
            list($rt, $gt, $bt) = $this->_getColorAlpha($pct, $color);

            // Add the color to the fixed pallette
            imagecolorset($img, $index, $rt, $gt, $bt);
        }

        // Draw or write the image to disk
        $this->_outputImage($this->_getResizedImage($img), $file, $compression);

        return $this;
    }
}
