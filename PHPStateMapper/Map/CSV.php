<?php
/**
 * PHPStateMapper_Map_CSV
 *
 * Model for loading the region data for a map from a CSV file.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPStateMapper_Map_CSV
{
    protected $_data        = null;
    private $_lineNumber    = 0;

    /**
     * Creates a PHPStateMapper_Map_CSV class object.
     *
     * @param   string      CSV file name
     * @return  void
     * @throws  PHPStateMapper_Exception
     */
    public function __construct($file)
    {
        if (!$this->_data = fopen($file, 'rt'))
        {
            throw new PHPStateMapper_Exception(
                "Map data file $file could not be opened for reading."
            );
        }
    }

    /**
     * Iteratively returns the next shadable area found in the
     * CSV data source file. Each area is returned as an array
     * formatted as such:
     *
     * Array(
     *     numerical area id #,
     *     2-letter ISO country code (e.g.: 'US'),
     *     array('List', 'Of', 'Region', 'Names')
     * ))
     *
     * @return  array
     * @throws  PHPStateMapper_Exception
     */
    public function get()
    {
        if ($this->_data === null)
        {
            return false;
        }

        if (feof($this->_data))
        {
            $this->_data = null;
            return false;
        }

        if (!$line = fgetcsv($this->_data, 1024, "\t"))
        {
            $this->_data = null;
            return false;
        }

        $this->_lineNumber++;

        if (count($line) < 2)
        {
            throw new PHPStateMapper_Exception(
                "Line number {$this->_lineNumber} of map data file does not contain "
                . "enough columns. Expecting at least 2 (id, country)."
            );
        }

        return array(
            $line[0],
            $line[1],
            isset($line[2])
                ? array_slice($line, 2)
                : null
        );
    }

    public function __destruct()
    {
        if ($this->_data)
        {
            fclose($this->_data);
            $this->_data = null;
        }
    }
}
