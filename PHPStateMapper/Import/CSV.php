<?php
/**
 * PHPStateMapper_Import_CSV
 *
 * A class designed for loading raw usage-by-state data into the
 * PHPStateMapper class from a comma separated values (CSV)
 * data source file.
 *
 * Copyright (c) 2010, Andrew Kandels <me@andrewkandels.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    Reports
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @copyright   2010 Andrew Kandels <me@andrewkandels.com>
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://andrewkandels.com/PHPStateMapper
 * @access      public
 */

require_once 'PHPStateMapper/Import.php';

class PHPStateMapper_Import_CSV extends PHPStateMapper_Import
{
    private $_file;
    private $_lineNumber;
    private $_length;
    private $_delimiter;
    private $_enclosure;
    private $_escape;
    private $_headers;
    private $_nameColumnIndex;
    private $_valueColumnIndex;

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

        $this->_lineNumber = 1;
    }

    /**
     * Sets the column which should be used to load the name
     * portion. This would be the assigned the value from the
     * value column.
     *
     * @param   mixed       String value (if file has headers) or
     *                      integer offset (if not)
     * @return  PHPStateMapper
     * @throws  PHPStateMapper_Exception_Import
     */
    public function setNameColumn($name)
    {
        $this->_nameColumnIndex = $this->_getColumn($name);

        return $this;
    }

    /**
     * Sets the column which should be used to load the value
     * portion. This would be the assigned to the state in the
     * name column.
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
     * Returns a 2-item array for the data on a given line. The first item
     * is the state name and the second item is the value assigned to the
     * state.
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

        if ($this->_nameColumnIndex === null)
        {
            throw new PHPStateMapper_Exception_Import(
                'No index has been assigned for the name column. Call '
                . 'setNameColumn() first.'
            );
        }

        if ($this->_valueColumnIndex === null)
        {
            throw new PHPStateMapper_Exception_Import(
                'No index has been assigned for the value column. Call '
                . 'setValueColumn() first.'
            );
        }

        // EOF
        if (!$line = $this->_getRow())
        {
            return false;
        }

        if (!isset($line[$this->_nameColumnIndex]))
        {
            throw new PHPStateMapper_Exception_Import(
                'Invalid name column index ' . $this->_nameColumnIndex . ' for line '
                . $this->_lineNumber . '.'
            );
        }

        if (!isset($line[$this->_valueColumnIndex]))
        {
            throw new PHPStateMapper_Exception_Import(
                'Invalid value column index ' . $this->_valueColumnIndex . ' for line '
                . $this->_lineNumber . '.'
            );
        }

        return array(
            $line[$this->_nameColumnIndex],
            (integer) $line[$this->_valueColumnIndex]
        );
    }
}
