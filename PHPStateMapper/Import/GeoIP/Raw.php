<?php
/**
 * PHPStateMapper_Import_GeoIPRaw
 *
 * When fed a string or file, it will scan each line for a valid
 * IPv4 address and decode it to its physical location on a map
 * and then import that data to PHPStateMapper.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPStateMapper_Import_GeoIP_Raw extends PHPStateMapper_Import
{
    private $_geoip;
    private $_file;
    private $_lines;
    private $_maxLineLength;

    public function __construct(PHPStateMapper_Import_GeoIP $geoip, $maxLineLength = 1024)
    {
        $this->_maxLineLength = $maxLineLength;
        $this->_geoip = $geoip;
    }

    /**
     * Sets the file in which to scan for IPv4 network addresses (line by line).
     *
     * @param   string      File naem
     * @return  PHPStateMapper_Import_GeoIP_Raw
     * @throws  PHPStateMapper_Exception_Import
     */
    public function setFile($file)
    {
        if (!$this->_file = fopen($file, 'rt'))
        {
            throw new PHPStateMapper_Exception_Import(
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
     * @return  PHPStateMapper_Import_GeoIP_Raw
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
                $ip = trim($matches[0]);
                if ($row = $this->_geoip->lookup($ip))
                {
                    return array($row[0], $row[1], 1);
                }
            }
        }
    }
}
