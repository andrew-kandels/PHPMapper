<?php
/**
 * PHPStateMapper_GeoIP
 *
 * Class to load the maps for translating IPv4 network addresses into
 * geographical regions.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */

require_once dirname(__FILE__) . '/Exception.php';
require_once dirname(__FILE__) . '/Exception/Geo.php';

abstract class PHPStateMapper_GeoIP
{
    protected $_map             = array();
    protected $_locationName    = null;
    protected $_blockName       = null;

    /**
     * Set the name of the location source, which depending on which
     * class is extending this class, may be a table name, file name,
     * and so forth.
     *
     * @param   string      Location source name
     * @return  PHPStateMapper_GeoIP
     */
    public function setLocationName($name)
    {
        $this->_locationName = $name;
        return $this;
    }

    /**
     * Set the name of the block source, which depending on which
     * class is extending this class, may be a table name, file name,
     * and so forth.
     *
     * @param   string      Location source name
     * @return  PHPStateMapper_GeoIP
     */
    public function setBlockName($name)
    {
        $this->_blockName = $name;
        return $this;
    }

    /**
     * Maps a known value (i.e.: city name or COLUMN_CITY) to a value,
     * which may be a database or CSV file column.
     *
     * @param   integer     COLUMN_* (see header)
     * @param   mixed       Map value
     * @param   integer     BLOCK or LOCATION (see header) defaults to LOCATION
     * @return  PHPStateMapper_GeoIP
     */
    public function mapColumn($id, $name, $source = 2)
    {
        if (!isset($this->_map[$source]))
        {
            $this->_map[$source] = array();
        }

        $this->_map[$source][$id] = $name;
        return $this;
    }

    /**
     * Looks up the geographical metadata for an IPv4 network address. This is
     * queried from the PDO data source.
     *
     * @param   mixed       IPv4 network address in written or numerical form
     * @return  array       Resultset
     * @throws  PHPStateMapper_Exception_Geo
     */
    public function lookup($ip)
    {
        if ($this->_locationName === null)
        {
            throw new PHPStateMapper_Exception_Geo(
                'Unknown table for IPv4 lookup. Call the setLocationName method.'
            );
        }

        if (empty($this->_map[PHPStateMapper::LOCATION]))
        {
            throw new PHPStateMapper_Exception_Geo(
                'No columns have been mapped for the location table. See the '
                . 'mapColumn method documentation.'
            );
        }

        if (is_string($ip))
        {
            $ip = ip2long($ip);
        }

        return $this->_lookup($ip);
    }
}
