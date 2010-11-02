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
            $index = (integer) $name;
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
     * Maps a column in the data source to a known value such
     * as country or region.
     *
     * @param   integer     Type constant (PHPStateMapper::COUNTRY, etc.)
     * @param   mixed       Value
     * @throws  PHPStateMapper_Exception_Import
     */
    public function map($id, $name)
    {
        if ($this->_hasHeaders && is_string($name))
        {
            foreach ($this->_headers as $index => $hdr)
            {
                if (!strcasecmp($name, $hdr))
                {
                    return parent::map($id, $index);
                }
            }
        }

        return parent::map($id, $name);
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
            $this->_getMapValueFromArray(PHPStateMapper::COUNTRY, $line, $extra),
            $this->_getMapValueFromArray(PHPStateMapper::REGION, $line, $extra),
            $this->_getMapValueFromArray(PHPStateMapper::VALUE, $line, $extra)
        );
    }
}
