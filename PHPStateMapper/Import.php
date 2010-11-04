<?php
/**
 * PHPStateMapper_Import
 *
 * A abstract class designed for loading raw usage-by-state data from
 * different formats (like CSV).
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */

abstract class PHPStateMapper_Import
{
    protected $_map             = array();

    /**
     * Throws an exception if an importer class extending this one doesn't
     * return the necessary getRowData method.
     *
     * @return  void
     * @throws  PHPStateMapper_Exception_Import
     */
    public function getRowData()
    {
        throw new PHPStateMapper_Exception_Import(
            'Class ' . get_class($this) . ' does not implement a getRowData method.'
        );
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
        $this->_map[$id] = $name;
        return $this;
    }

    /**
     * Returns the default value for a column by constant.
     *
     * @param   integer     Type constant (PHPStateMapper::COUNTRY, etc.)
     * @return  mixed       Default value
     */
    protected function _getMapDefault($id)
    {
        switch ($id)
        {
            case PHPStateMapper::COUNTRY:
                return PHPStateMapper::DEFAULT_COUNTRY;

            case PHPStateMapper::VALUE:
                return 1; // additive

            default:
                return false;
        }
    }

    /**
     * Retrieves a previously mapped value.
     *
     * @param   integer     Type constant (PHPStateMapper::COUNTRY, etc.)
     * @return  mixed       Value
     */
    protected function _getMap($id)
    {
        return (isset($this->_map[$id]) ? $this->_map[$id] : false);
    }

    /**
     * Attempts to find a value by its mapping in an array. If found,
     * it returns the trimmed result. If not, it returns the default
     * value or false if there is no default value.
     *
     * @param   integer     Type constant (PHPStateMapper::COUNTRY, etc.)
     * @param   array       Data array
     * @param   string      Additional debugging information (e.g.: line number)
     * @return  string      Scalar value
     * @throws  PHPStateMapper_Exception_Import
     */
    protected function _getMapValueFromArray($id, array $arr, $extra = '')
    {
        if (false !== ($index = $this->_getMap($id)))
        {
            if (!isset($arr[$index]))
            {
                throw new PHPStateMapper_Exception_Import(
                    sprintf('Column index specified as %s not found%s.',
                        $index,
                        !empty($extra) ? ' ' . $extra : ''
                    )
                );
            }

            $value = trim($arr[$index]);
        }

        // Not set or empty, use default value
        if (empty($value))
        {
            $value = $this->_getMapDefault($id);
        }

        switch ($id)
        {
            case PHPStateMapper::COUNTRY:
                if (strlen($value) != 2)
                {
                    throw new PHPStateMapper_Exception_Import(
                        'Country code should be a valid 2-letter ISO value (e.g.: US). '
                        . 'Found "' . $value . '"' . $extra
                    );
                }
                break;
        }

        return $value;
    }
}
