<?php
/**
 * PHPStateMapper_GeoIP_PDO
 *
 * Translates an IPv4 network address into its geographical location
 * by querying a PDO data connection.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */

require_once dirname(__FILE__) . '/../GeoIP.php';

class PHPStateMapper_GeoIP_PDO extends PHPStateMapper_GeoIP
{
    private $_pdo;

    /**
     * Creates a PHPStateMapper_GeoIP_PDO object. Instantiated in the same
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
        $this->_pdo = new PDO($dsn, $username, $password, $options);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * If the COLUMN_LOCATION is specified as a column, it allows the query to
     * be sent to two tables (common in the MaxMind data set). It must be mapped to
     * both tables in the query. This method returns the join statement to make this
     * happen (or empty if not set).
     *
     * @return  string      Join SQL statement
     * @throws  PHPStateMapper_Exception_Geo
     */
    private function _getJoinSQL()
    {
        $location = array();

        foreach ($this->_map as $table => $columns)
        {
            switch ($table)
            {
                case PHPStateMapper::BLOCK:
                    $tableName = $this->_blockName;
                    break;

                case PHPStateMapper::LOCATION:
                    $tableName = $this->_locationName;
                    break;

                default:
                    continue 2;
            }

            foreach ($columns as $map => $name)
            {
                switch ($map)
                {
                    case PHPStateMapper_GeoIp::COLUMN_LOCATIONID:
                        $location[] = sprintf('%s.%s', $tableName, $name);
                        break;
                }
            }
        }

        if (empty($location))
        {
            return '';
        }
        else if (count($location) != 2)
        {
            throw new PHPStateMapper_Exception_Geo(
                'If mapping COLUMN_LOCATIONID, a second column must be mapped for which '
                . 'it references in another table. Additionally, no more than 2 mappings '
                . 'can exist.'
            );
        }
        else
        {
            return sprintf(' INNER JOIN %s ON %s', $this->_blockName, implode(' = ', $location));
        }
    }

    /**
     * Returns an array consisting of the mapping codes pointing to the SQL
     * to retrieve the columns from the data source.
     *
     * @return  array
     */
    private function _getColumns()
    {
        $return = array();

        foreach ($this->_map as $table => $columns)
        {
            switch ($table)
            {
                case PHPStateMapper::BLOCK:
                    $tableName = $this->_blockName;
                    break;

                case PHPStateMapper::LOCATION:
                    $tableName = $this->_locationName;
                    break;

                default:
                    continue 2;
            }

            foreach ($columns as $map => $name)
            {
                $return[$map] = sprintf('%s.%s',
                    $tableName,
                    $name
                );
            }
        }

        if (!isset($return[PHPStateMapper::COLUMN_RANGEIPSTART]))
        {
            throw new PHPStateMapper_Exception_Geo(
                'No COLUMN_RANGEIPSTART column mapped. One must be mapped using the mapColumn '
                . 'method.'
            );
        }

        if (!isset($return[PHPStateMapper::COLUMN_RANGEIPEND]))
        {
            throw new PHPStateMapper_Exception_Geo(
                'No COLUMN_RANGEIPEND column mapped. One must be mapped using the mapColumn '
                . 'method.'
            );
        }

        return $return;
    }

    /**
     * Looks up the geographical metadata for an IPv4 network address.
     *
     * @param   mixed       IPv4 network address in numerical form
     * @return  array       Resultset
     * @throws  PHPStateMapper_Exception_Geo
     */
    protected function _lookup($ip)
    {
        $columns = $this->_getColumns();

        $sql = sprintf('SELECT %s FROM %s%s WHERE ? BETWEEN %s AND %s',
            implode(', ', array_values($columns)),
            $this->_locationName,
            $this->_getJoinSQL(),
            $columns[PHPStateMapper::COLUMN_RANGEIPSTART],
            $columns[PHPStateMapper::COLUMN_RANGEIPEND]
        );

        $rs = $this->_pdo->prepare($sql);
        $rs->bindParam(1, $ip);
        $rs->execute();

        if (!$row = $rs->fetch(PDO::FETCH_NUM))
        {
            return false;
        }

        $return = array();
        $i = 0;
        foreach ($columns as $map => $column)
        {
            $return[$map] = $row[$i++];
        }

        return $return;
    }
}
