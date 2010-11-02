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

require_once dirname(__FILE__) . '/../../Import.php';

class PHPStateMapper_Import_GeoIP_Raw extends PHPStateMapper_Import
{
    private $_file;
    private $_lines;
    private $_maxLineLength;

    public function __construct($maxLineLength = 1024)
    {
        parent::__construct();
        $this->_maxLineLength = $maxLineLength;
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

                $line = trim(fgets($this->_file, $this->_maxLineLength));
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
                if ($row = $this->_geo->lookup($ip) &&
                    isset($row[PHPStateMapper_GeoIP::COLUMN_COUNTRY]))
                {
                    return array(
                        $row[PHPStateMapper_GeoIP::COLUMN_COUNTRY],
                        isset($row[PHPStateMapper_GeoIP::COLUMN_REGION])
                            ? $row[PHPStateMapper_GeoIP::COLUMN_REGION]
                            : null,
                        1
                    );
                }
            }
        }
    }
}
