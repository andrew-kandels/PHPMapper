<?php
/**
 * PHPMapper_Import_Array
 *
 * Simple class for loading a basic PHP array as
 * map data. Used primarily for testing.
 *
 * @package     PHPMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPMapper_Import_Array extends PHPMapper_Import
{
    private $_data = array();

    /**
     * Creates a PHPMapper_Array object.
     *
     * @param   array       Array of 3-item arrays (2-letter country code,
     *                                              region (optional),
     *                                              value (optional)
     *                                             )
     */
    public function __construct(array $data = array())
    {
        $this->setData($data);
    }

    /**
     * Sets the input data.
     *
     * @param   array       Array of 3-item arrays (2-letter country code,
     *                                              region (optional),
     *                                              value (optional)
     *                                             )
     * @return  PHPMapper_Import_Array
     */
    public function setData(array $data)
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * Adds additional data to the set.
     *
     * @param   array       3-item array (2-letter country code, region, value)
     * @return  PHPMapper_Import_Array
     */
    public function addData(array $data)
    {
        $this->_data[] = $data;
        return $this;
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
        if (empty($this->_data))
        {
            return false;
        }

        $mp = array_shift($this->_data);

        if (!isset($mp[0]) || strlen($mp[0]) != 2)
        {
            throw new PHPMapper_Exception_Import(
                'Data should have a valid, 2-letter ISO country code in its first index.'
            );
        }

        if (!isset($mp[2]))
        {
            $mp[2] = 1;
        }

        return $mp;
    }
}
