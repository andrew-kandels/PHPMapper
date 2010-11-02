<?php
/**
 * PHPStateMapper_Import_CSV
 *
 * A class designed for loading raw usage-by-state data into the
 * PHPStateMapper class from a comma separated values (CSV)
 * data source file.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

require_once dirname(__FILE__) . '/../Import.php';

class PHPStateMapper_Import_CSV extends PHPStateMapper_Import
{
    private $_file;
    private $_lineNumber;
    private $_length;
    private $_delimiter;
    private $_enclosure;
    private $_escape;
    private $_headers;
    private $_regionColumnIndex;
    private $_valueColumnIndex;
    private $_countryColumnIndex;
    private $_country;

    /**
     * Creates a PHPStateMapper_CSV object.
     *
     * @param   string      File name
     * @Param   boolean     First line = headers?
     * @param   integer     Max line length (default 1024)
     * @param   string      Delimiter (default comma)
     * @param   string      Enclosure (default ")
     * @param   string      Escape (default \)
     * @throws  PHPStateMapper_Exception_Import
     */
    public function __construct($file, $hasHeaders = false, $length = 1024,
        $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        if (!file_exists($file))
        {
            throw new PHPStateMapper_Exception_Import(
                "$file does not exist or is not readable."
            );
        }

        if (!$this->_file = fopen($file, 'rt'))
        {
            throw new PHPStateMapper_Exception_Import(
                "Failed to open $file for reading."
            );
        }

        $this->_length = $length;
        $this->_delimiter = $delimiter;
        $this->_enclosure = $enclosure;
        $this->_escape = $escape;
        $this->_hasHeaders = $hasHeaders;

        $firstRow = $this->_getRow();

        if ($hasHeaders)
        {
            $this->_headers = $firstRow;
        }
        else
        {
            $this->_headers = count($firstRow);
        }

        if (!$firstRow || empty($this->_headers))
        {
            throw new PHPStateMapper_Exception_Import(
                'After reading first line -- file does not contain any columns. '
                . 'Are you using a valid delimiter?'
            );
        }

        $this->_lineNumber  = 1;
        $this->_country     = 'US';
    }

    /**
     * Statically sets the 2-letter country name. By default, this
     * is 'US'.
     *
     * @param   string      2-letter country code
     * @return  PHPStateMapper_Import_CSV
     */
    public function setCountryCode($name)
    {
        if (strlen($name) != 2)
        {
            throw new PHPStateMapper_Exception_Import(
                'Country name should be a valid ISO 2-letter code.'
            );
        }
        $this->_country = strtoupper($name);

        return $this;
    }

    /**
     * Sets the column which should be used to load the 2-letter
     * country name. If this isn't in the file, it can be
     * statically set via the setCountryCode method. By default,
     * this is set to 'US'.
     *
     * @param   mixed       String value (if file has headers) or
     *                      integer offset (if not)
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception_Import
     */
    public function setCountryColumn($name)
    {
        $this->_countryColumnIndex = $this->_getColumn($name);

        return $this;
    }

    /**
     * Sets the column which should be used to load the region
     * portion. In the U.S., this would be the state name.
     *
     * @param   mixed       String value (if file has headers) or
     *                      integer offset (if not)
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception_Import
     */
    public function setRegionColumn($name)
    {
        $this->_regionColumnIndex = $this->_getColumn($name);

        return $this;
    }

    /**
     * Sets the column which should be used to load the value
     * portion. If not provided, each line adds 1 to a running
     * count.
     *
     * @param   mixed       String value (if file has headers) or
     *                      integer offset (if not)
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception_Import
     */
    public function setValueColumn($name)
    {
        $this->_valueColumnIndex = $this->_getColumn($name);

        return $this;
    }

    /**
     * Get the row column offset based on a name or index value by
     * comparing and validating it against the header (first line).
     *
     * @param   mixed       String value (if file has headers) or
     *                      integer offset (if not)
     * @return  integer
     * @throws  PHPStateValues_Exception_Import
     */
    private function _getColumn($name)
    {
        if (is_array($this->_headers))
        {
            foreach ($this->_headers as $index => $value)
            {
                if (!strcasecmp(trim($name), trim($value)))
                {
                    return $index;
                }
            }

            throw new PHPStateMapper_Exception_Import(
                "Column $name not found in file headers."
            );
        }
        else
        {
            $index = (integer)$name;
            if ($index < 0 || $index > $this->_headers)
            {
                throw new PHPStateMapper_Exception_Import(
                    "Column #$index not valid. Only " . $this->_headers
                    . ' found on first row.'
                );
            }

            return $index;
        }
    }

    /**
     * Reads a line from the data source file using fgetcsv().
     *
     * @return  array       Line data
     */
    private function _getRow()
    {
        $line = fgetcsv($this->_file, $this->_length, $this->_delimiter,
            $this->_enclosure, $this->_escape
        );

        // EOF
        if (false === $line && feof($this->_file))
        {
            return false;
        }

        // Empty line
        if (is_array($line) && empty($line))
        {
            return false;
        }

        // Other error
        if (!$line)
        {
            throw new PHPStateMapper_Exception_Import(
                'fgetcsv() failed when attempting to read line from file.'
            );
        }
        else
        {
            $this->_lineNumber++;
            return $line;
        }
    }

    /**
     * Returns a 3-item array for the data on a given line. The first item
     * is the 2-letter country code. The second is the region (in the U.S.
     * this would be the state) and the third is the value for that
     * region (if not provided, it's additive, one per match).
     *
     * @return  array
     */
    public function getRowData()
    {
        if ($this->_file === null || feof($this->_file))
        {
            if ($this->_file !== null)
            {
                fclose($this->_file);
                $this->_file = null;
            }

            return false;
        }

        if ($this->_regionColumnIndex === null)
        {
            throw new PHPStateMapper_Exception_Import(
                'No index has been assigned for the region column. Call '
                . 'setRegionColumn() first.'
            );
        }

        // EOF
        if (!$line = $this->_getRow())
        {
            return false;
        }

        if (!isset($line[$this->_regionColumnIndex]))
        {
            throw new PHPStateMapper_Exception_Import(
                'Invalid region column index ' . $this->_regionColumnIndex . ' for line '
                . $this->_lineNumber . '.'
            );
        }
        else
        {
            $region = $line[$this->_regionColumnIndex];
        }

        if ($this->_valueColumnIndex !== null)
            if (!isset($line[$this->_valueColumnIndex]))
            {
                throw new PHPStateMapper_Exception_Import(
                    'Invalid value column index ' . $this->_valueColumnIndex . ' for line '
                    . $this->_lineNumber . '.'
                );
            }

            $value = $line[$this->_valueColumnIndex];
        }
        else
        {
            $value = 1;
        }

        if ($this->_countryColumnIndex !== null)
            if (!isset($line[$this->_countryColumnIndex]))
            {
                throw new PHPStateMapper_Exception_Import(
                    'Invalid country column index ' . $this->_countryColumnIndex . ' for line '
                    . $this->_lineNumber . '.'
                );
            }

            $country = $line[$this->_countryColumnIndex];
        }
        else
        {
            $country = $this->_country;
        }

        return array(
            $country,
            $region,
            $value
        );
    }
}
