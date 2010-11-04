<?php
/**
 * PHPMapper_Import_GeoIPRaw
 *
 * When fed a string or file, it will scan each line for a valid
 * IPv4 address and decode it to its physical location on a map
 * and then import that data to PHPMapper.
 *
 * Requires the PHP geoip PECL extension. See doc/geoip.html for
 * instructions on installation.
 *
 * @package     PHPMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPMapper_Import_GeoIP_Raw extends PHPMapper_Import
{
    private $_file;
    private $_lines;
    private $_maxLineLength;

    public function __construct($maxLineLength = 1024)
    {
        if (!function_exists('geoip_record_by_name'))
        {
            throw new PHPMapper_Exception_Import(
                'PECL geoip extension is required by PHPMapper GeoIP '
                . 'functionality. See ' . realpath(dirname(__FILE__) . '../../../doc')
                . '/geoip.html for installation instructions.'
            );
        }

        $this->_maxLineLength = $maxLineLength;
    }

    /**
     * Sets the file in which to scan for IPv4 network addresses (line by line).
     *
     * @param   string      File naem
     * @return  PHPMapper_Import_GeoIP_Raw
     * @throws  PHPMapper_Exception_Import
     */
    public function setFile($file)
    {
        if (!$this->_file = fopen($file, 'rt'))
        {
            throw new PHPMapper_Exception_Import(
                "Unable to fopen $file for reading."
            );
        }
        return $this;
    }

    /**
     * Sets the raw data (either string or an array of lines) for which
     * should be scanned for IP addresses.
     *
     * @param   mixed       Array of lines or string data
     * @return  PHPMapper_Import_GeoIP_Raw
     */
    public function setData($val)
    {
        $this->_lines = is_array($val)
            ? $val
            : preg_split('/[\r\n]+/', $str);

        return $this;
    }

    /**
     * Returns a 3-item array for the data on a given line. The first item
     * is the 2-letter country code and the second is the region within the
     * country. The third is a static value of 1, as each line is additive.
     *
     * @return  array
     */
    public function getRowData()
    {
        while(1)
        {
            if ($this->_file !== null)
            {
                if (feof($this->_file))
                {
                    return false;
                }

                if (!$line = fgets($this->_file, $this->_maxLineLength))
                {
                    return false;
                }
            }
            else
            {
                if (empty($this->_lines))
                {
                    return false;
                }

                $line = array_shift($this->_lines);
            }

            if (preg_match('/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'
                . '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]'
                . '|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/', $line, $matches))
            {
                $row = @geoip_record_by_name(trim($matches[0]));
                if ($row && !empty($row['country_code']))
                {
                    return array(
                        $row['country_code'],
                        !empty($row['region']) ? $row['region'] : null,
                        1
                    );
                }
            }
        }
    }
}
