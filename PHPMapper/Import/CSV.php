<?php
/**
 * PHPMapper_Import_CSV
 *
 * A class designed for loading raw usage-by-state data into the
 * PHPMapper class from a comma separated values (CSV)
 * data source file.
 *
 * @package     PHPMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPMapper_Import_CSV extends PHPMapper_Import
{
    private $_file;
    private $_lineNumber;
    private $_length;
    private $_delimiter;
    private $_enclosure;
    private $_escape;
    private $_headers;

    /**
     * Creates a PHPMapper_CSV object.
     *
     * @param   string      File name
     * @Param   boolean     First line = headers?
     * @param   integer     Max line length (default 1024)
     * @param   string      Delimiter (default comma)
     * @param   string      Enclosure (default ")
     * @param   string      Escape (default \)
     * @throws  PHPMapper_Exception_Import
     */
    public function __construct($file, $hasHeaders = false, $length = 1024,
        $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        if (!file_exists($file))
        {
            throw new PHPMapper_Exception_Import(
                "$file does not exist or is not readable."
            );
        }

        if (!$this->_file = @fopen($file, 'rt'))
        {
            throw new PHPMapper_Exception_Import(
                "Failed to open $file for reading."
            );
        }

        $this->_length = $length;
        $this->_delimiter = $delimiter;
        $this->_enclosure = $enclosure;
        $this->_escape = $escape;
        $this->_hasHeaders = $hasHeaders;

        $firstRow = $this->_getRow(true);

        if ($hasHeaders)
        {
            $this->_headers = $firstRow;
        }
        else
        {
            $this->_headers = count($firstRow);
            rewind($this->_file);
        }

        if (!$firstRow || empty($this->_headers))
        {
            throw new PHPMapper_Exception_Import(
                'After reading first line -- file does not contain any columns. '
                . 'Are you using a valid delimiter?'
            );
        }

        $this->_lineNumber  = 1;
        $this->_country     = 'US';
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
    public function getColumn($name)
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

            throw new PHPMapper_Exception_Import(
                "Column $name not found in file headers."
            );
        }
        else
        {
            $index = (integer) $name;
            if ($index < 0 || $index > $this->_headers)
            {
                throw new PHPMapper_Exception_Import(
                    "Column #$index not valid. Only " . $this->_headers
                    . ' found on first row.'
                );
            }

            return $index;
        }
    }

    /**
     * Returns either the file headers (array) or the number of
     * columns depending on whether the object was instantiated with
     * headers or not.
     *
     * @return  mixed
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * Reads a line from the data source file using fgetcsv().
     *
     * @param   boolean     Ignore column/header mismatch (useful for loading 1st line)
     * @return  array       Line data
     */
    private function _getRow($ignoreMismatch = false)
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

        $expectedHeaders = $this->_hasHeaders
            ? count($this->_headers)
            : $this->_headers;

        // Other error
        if (!$line)
        {
            throw new PHPMapper_Exception_Import(
                'fgetcsv() failed when attempting to read line from file.'
            );
        }
        else if (!$ignoreMismatch && count($line) != $expectedHeaders)
        {
            throw new PHPMapper_Exception_Import(
                'Line #' . $this->_lineNumber . ' has ' . count($line) . ' columns. Expecting '
                . $expectedHeaders . '.'
            );
        }
        else
        {
            $this->_lineNumber++;
            return $line;
        }
    }

    /**
     * Maps a column in the data source to a known value such
     * as country or region.
     *
     * @param   integer     Type constant (PHPMapper::COUNTRY, etc.)
     * @param   mixed       Value
     * @throws  PHPMapper_Exception_Import
     */
    public function map($id, $name)
    {
        $index = $this->getColumn($name);
        return parent::map($id, $index);
    }

    /**
     * Returns a 3-item array for the data on a given line:
     * 1) 2-Letter Country Code
     * 2) Region
     * 3) Value (if not provided, then the number 1)
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

        // EOF
        if (!$line = $this->_getRow())
        {
            return false;
        }

        // Extra debugging info
        $extra = sprintf('on line number %d', $this->_lineNumber);

        return array(
            $this->_getMapValueFromArray(PHPMapper::COUNTRY, $line, $extra),
            $this->_getMapValueFromArray(PHPMapper::REGION, $line, $extra),
            $this->_getMapValueFromArray(PHPMapper::VALUE, $line, $extra)
        );
    }
}
