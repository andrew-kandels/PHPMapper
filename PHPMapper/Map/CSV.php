<?php
/**
 * PHPMapper_Map_CSV
 *
 * Model for loading the region data for a map from a CSV file.
 *
 * @package     PHPMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPMapper_Map_CSV
{
    protected $_data        = null;
    private $_lineNumber    = 0;

    /**
     * Creates a PHPMapper_Map_CSV class object.
     *
     * @param   string      CSV file name
     * @return  void
     * @throws  PHPMapper_Exception
     */
    public function __construct($file)
    {
        $this->setFile($file);
    }

    /**
     * Sets the CSV to serve as the data source.
     *
     * @param   string      CSV file name
     * @return  PHPMapper_Map_CSV
     */
    public function setFile($file)
    {
        if ($this->_data !== null)
        {
            fclose($this->_data);
            $this->_data = null;
        }

        if (!$this->_data = @fopen($file, 'rt'))
        {
            throw new PHPMapper_Exception(
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
     * @throws  PHPMapper_Exception
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
            throw new PHPMapper_Exception_Import(
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
