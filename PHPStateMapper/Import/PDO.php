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

class PHPStateMapper_Import_PDO extends PHPStateMapper_Import
{
    private $_pdo;
    private $_rs;
    private $_tableName;
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

        $this->_pdo = new PDO($dsn, $username, $password, $options);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Provide a custom SQL query to retrieve the column/value pairs used
     * to generate the map data. The columns returned by the query must be
     * in this order:
     *
     * 1) 2-Letter ISO country code (use NULL for default country)
     * 2) Region name (required)
     * 3) Value (or NULL for default, which is 1 for additive)
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
            if (!$country = $this->_getMap(PHPStateMapper::COUNTRY))
            {
                $country = '""';
            }

            if (!$region = $this->_getMap(PHPStateMapper::REGION))
            {
                $region = '""';
            }

            if (!$value = $this->_getMap(PHPStateMapper::VALUE))
            {
                $value = 1;
            }

            $sql = sprintf("SELECT %s, %s, %s FROM %s",
                $country,
                $region,
                $value,
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

        if (!$row = $this->_rs->fetch(PDO::FETCH_NUM))
        {
            return false;
        }

        // All queries map first 3 values of array
        $this->map(PHPStateMapper::COUNTRY, 0);
        $this->map(PHPStateMapper::REGION, 1);
        $this->map(PHPStateMapper::VALUE, 2);

        return array(
            $this->_getMapValueFromArray(PHPStateMapper::COUNTRY, $row),
            $this->_getMapValueFromArray(PHPStateMapper::REGION, $row),
            $this->_getMapValueFromArray(PHPStateMapper::VALUE, $row)
        );
    }
}
