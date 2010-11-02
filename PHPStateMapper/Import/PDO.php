<?php
/**
 * PHPStateMapper_Import_PDO
 *
 * A class designed for loading raw usage-by-state data into the
 * PHPStateMapper class using a PDO compatible data source such
 * as MySQL.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */

require_once dirname(__FILE__) . '/../Import.php';

class PHPStateMapper_Import_PDO extends PHPStateMapper_Import
{
    private $_pdo;
    private $_rs;
    private $_tableName;
    private $_columnRegionIndex;
    private $_columnCountryIndex;
    private $_columnValueIndex;
    private $_country;
    private $_query;
    private $_queryBoundParams;

    /**
     * Creates a PHPStateMapper_Import_PDO object. Instantiated in the same
     * way as you would a PDO object, so for documentation see:
     *
     * http://www.php.net/manual/en/pdo.construct.php
     *
     * @param   string      DSN
     * @param   string      Username (optional)
     * @param   string      Password (optional)
     * @param   array       Driver options array (see PDO documentation)
     * @throws  PHPStateMapper_Exception_Import
     */
    public function __construct($dsn, $username = '', $password = '',
        array $options = array())
    {
        $this->_queryBoundParams    = array();
        $this->_columnNameIndex     = 0;
        $this->_columnValueIndex    = 1;
        $this->_country             = 'US';

        $this->_pdo = new PDO($dsn, $username, $password, $options);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Provide a custom SQL query to retrieve the column/value pairs used
     * to generate the map data. By default, the first and second column
     * represent the item and value respectively, but you can overide this
     * with the the setNameColumn and setValueColumn methods.
     *
     * @param   string      SQL query
     * @param   array       Bound parameters (if any)
     * @return  PHPStateMapper_Import_PDO
     * @throws  PDOException
     */
    public function setQuery($sql, $params = array())
    {
        $this->_query = $sql;
        $this->_queryBoundParams = $params;
        return $this;
    }

    /**
     * Sets the database table name in which the column/value pairs sit.
     *
     * @param   string      Table name
     * @return  PHPStateMapper_Import_PDO
     * @throws  PDOException
     */
    public function setTableName($table)
    {
        $this->_tableName = $table;
        return $this;
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
     * country name. If this isn't in the table, it can be
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
        $this->_columnCountryIndex = $name;
        return $this;
    }


    /**
     * Sets the table column name in which the region value (e.g.: state name)
     * resides.
     *
     * @param   string      Table name
     * @return  PHPStateMapper_Import_PDO
     * @throws  PDOException
     */
    public function setRegionColumn($column)
    {
        $this->_columnRegionIndex = $column;
        return $this;
    }

    /**
     * Sets the table column name in which the value of the item (the raw count)
     * resides.
     *
     * @param   string      Table name
     * @return  PHPStateMapper_Import_PDO
     * @throws  PDOException
     */
    public function setValueColumn($value)
    {
        $this->_columnValueIndex = $value;
        return $this;
    }

    /**
     * Queries the PDO object to collect the name/value pairs. The query is either
     * automatically generated or provided by the setQuery method.
     *
     * @return  PDOStatement
     * @throws  PDOException
     * @throws  PHPStateMapper_Exception_Import
     */
    private function _getQuery()
    {
        if ($this->_query !== null)
        {
            $rs = $this->_pdo->prepare($this->_query);
            $isZeroIndexed = 0;

            foreach ($this->_queryBoundParams as $name => $value)
            {
                if (is_string($name))
                {
                    // Allow specifying a name without the preceeding colon
                    if (left($name, 1) != ':')
                    {
                        $name = ':' . $name;
                    }

                    $this->_pdo->bindParam($name, $value);
                }
                else
                {
                    if (($name = (int)$name) == 0)
                    {
                        $isZeroIndexed = true;
                    }
                    $this->_pdo->bindParam($name + $isZeroIndexed, $value);
                }
            }

            $rs->execute();
        }
        else if ($this->_tableName !== null)
        {
            if ($this->_columnCountryIndex !== 0)
            {
                $columns[] = sprintf('%s AS countryValue', $this->_columnCountryIndex);
            }
            else
            {
                $columns[] = 'NULL AS countryValue';
            }

            if ($this->_columnRegionIndex === 0)
            {
                throw new PHPStateMapper_Exception_Import(
                    'Column region index appears to be invalid. Please specify the table '
                    . 'column for the region name with the setRegionColumn method.'
                );
            }
            else
            {
                $columns[] = sprintf('%s AS regionValue', $this->_columnRegionIndex);
            }

            if ($this->_columnValueIndex !== 0)
            {
                $columns[] = sprintf('%s AS valueValue', $this->_columnValueIndex);
            }
            else
            {
                $columns[] = '1 AS valueValue';
            }

            $sql = sprintf("SELECT %s FROM %s",
                implode(', ', $columns),
                $this->_tableName
            );

            $rs = $this->_pdo->query($sql);
        }
        else
        {
            throw new PHPStateMapper_Exception_Import(
                'You must provide either a table via setTableName or a query via '
                . 'setQuery in order to collect data from the PHPStateMapper_Import_PDO '
                . 'class.'
            );
        }

        return $rs;
    }

    /**
     * Returns a 2-item array for the data on a given line. The first item
     * is the region name and the second item is the value assigned to the
     * region.
     *
     * @return  array
     */
    public function getRowData()
    {
        if ($this->_rs === null)
        {
            $this->_rs = $this->_getQuery();
        }

        if (!$row = $this->_rs->fetch(PDO::FETCH_BOTH))
        {
            return false;
        }

        return array(
            !empty($row['countryValue']) ? $row['countryValue'] : $this->_country,
            $row['regionValue'],
            $row['valueValue']
        );
    }
}
