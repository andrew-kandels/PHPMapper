<?php
/**
 * PHPStateMapper_Import_GeoIP
 *
 * Class for importing a MaxMind free city GeoIP flat file database
 * into a PDO data source in order to translate IP addresses or
 * host names into geographical country and region.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 */

class PHPStateMapper_Import_GeoIP extends PHPStateMapper_Import
{
    const QUICK_COMMIT_SIZE     = 16384;
    private $_pdo               = null;
    private $_tablePrefix       = 'geoip_';
    private $_fileLocation      = null;
    private $_fileBlocks        = null;
    private $_quickMode         = false;

    /**
     * Creates a PHPStateMapper_Import_GeoIP object. Instantiated in the same
     * way as you would a PDO object, so for documentation see:
     *
     * http://www.php.net/manual/en/pdo.construct.php
     *
     * @param   string      DSN
     * @param   string      Username (optional)
     * @param   string      Password (optional)
     * @param   array       Driver options array (see PDO documentation)
     * @throws  PDOException
     */
    public function __construct($dsn, $username = '', $password = '',
        array $options = array())
    {
        $this->_pdo = new PDO($dsn, $username, $password, $options);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Enables quick mode which speeds up data population to the
     * new tables when calling the install() method. This is not supported
     * by all database engines.
     *
     * @param   boolean     Quick mode?
     * @return  PHPStateMapper_Import_GeoIP
     */
    public function setQuickMode($yn)
    {
        $this->_quickMode = $yn;
        return $this;
    }

    /**
     * Sets the prefix for the database tables names to avoid
     * naming collisions.
     *
     * WARNING: You should make sure your MaxMind import file
     * is from a trusted source as quick mode bypasses escaping
     * data from the CSV file and leaves you open to SQL
     * injection.
     *
     * @param   string      Table prefix (defaults to geoip_)
     * @throws  PDOException
     */
    public function setTablePrefix($table)
    {
        $this->_tableName = $table;
        return $this;
    }

    /**
     * The MaxMind free city database includes two files:
     *
     * 1) Location file (GeoLiteCity-Location.csv)
     * 2) Blocks file (GeoLiteCity-Blocks.csv)
     *
     * The parameters are the respective locations of these files.
     *
     * @param   string      Location file
     * @param   string      Blocks file
     * @return  PHPStateMapper_Import_GeoIP
     * @throws  PHPStateMapper_Exception_Import
     */
    public function setSourceFiles($location, $blocks)
    {
        if (!$this->_fileLocation = fopen($location, 'rt'))
        {
            throw new PHPStateMapper_Exception_Import(
                "Failed to open $location for reading."
            );
        }

        if (!$this->_fileBlocks = fopen($blocks, 'rt'))
        {
            throw new PHPStateMapper_Exception_Import(
                "Failed to open $blocks for reading."
            );
        }

        return $this;
    }

    /**
     * Creates the internal blocks table to store the IP
     * ranges and a pointer to their location row.
     *
     * @return  PHPStateMapper_Import_GeoIP
     * @throws  PDOException
     */
    private function _createBlocksTable()
    {
        $sql = sprintf(
            'CREATE TABLE %sblocks ('
            . 'range_ip_start INT UNSIGNED NOT NULL, '
            . 'range_ip_end   INT UNSIGNED NOT NULL, '
            . 'location_id    INT UNSIGNED NOT NULL, '
            . 'PRIMARY KEY (range_ip_start, range_ip_end), '
            . 'INDEX(range_ip_end)'
            . ')',
            $this->_tablePrefix
        );

        $this->_pdo->exec($sql);
    }

    /**
     * Loads the data from the MaxMind city database into the new
     * blocks table.
     *
     * @return  PHPStateMapper_Import_GeoIP
     * @throws  PDOException
     */
    private function _loadBlocksTable()
    {
        // Unused at this time
        $copyright = fgets($this->_fileBlocks);
        $headers = fgets($this->_fileBlocks);

        if ($this->_quickMode)
        {
            $inserts = array();
        }

        while (!feof($this->_fileBlocks))
        {
            if (!$line = fgetcsv($this->_fileBlocks, 2048, ',', '"', '\\'))
            {
                break;
            }

            if ($this->_quickMode)
            {
                $inserts[] = '(' . implode(',', $line) . ')';

                if (count($inserts) === self::QUICK_COMMIT_SIZE)
                {
                    $this->_pdo->exec(sprintf(
                        'INSERT INTO %sblocks VALUES %s',
                        $this->_tablePrefix,
                        implode(',', $inserts)
                    ));
                    $inserts = array();
                }
            }
            else
            {
                $sql = sprintf('INSERT INTO %sblocks VALUES ('
                    . ':location_id, :range_ip_start, :range_ip_end)',
                    $this->_tablePrefix
                );

                $rs = $this->_pdo->prepare($sql);

                $rs->execute(array(
                    ':location_id'      => (int) $line[0],
                    ':range_ip_start'   => (double) $line[1],
                    ':range_ip_end'     => (double) $line[2]
                ));
            }
        }

        fclose($this->_fileBlocks);
        $this->_fileBlocks = null;

        if ($this->_quickMode && !empty($inserts))
        {
            $this->_pdo->exec(sprintf(
                'INSERT INTO %sblocks VALUES %s',
                $this->_tablePrefix,
                implode(',', $inserts)
            ));
            $inserts = array();
        }
    }

    /**
     * Creates the internal location table which
     * stores metadata for every map location.
     *
     * @return  PHPStateMapper_Import_GeoIP
     * @throws  PDOException
     */
    private function _createLocationTable()
    {
        $sql = sprintf(
            'CREATE TABLE %slocation ('
            . 'location_id    INT UNSIGNED NOT NULL, '
            . 'country_code   CHAR(2) NOT NULL, '
            . 'region         CHAR(2), '
            . 'city           VARCHAR(35), '
            . 'postal_code    VARCHAR(10), '
            . 'latitude       DECIMAL(19, 15) NOT NULL, '
            . 'longitude      DECIMAL(19, 15) NOT NULL, '
            . 'metro_code     INT, '
            . 'area_code      INT, '
            . 'PRIMARY KEY (location_id)'
            . ')',
            $this->_tablePrefix
        );

        $this->_pdo->exec($sql);
    }

    /**
     * Loads the data from the MaxMind city database into the new
     * location table.
     *
     * @return  PHPStateMapper_Import_GeoIP
     * @throws  PDOException
     */
    private function _loadLocationTable()
    {
        // Unused at this time
        $copyright = fgets($this->_fileLocation);
        $headers = fgets($this->_fileLocation);

        if ($this->_quickMode)
        {
            $inserts = array();
        }

        while (!feof($this->_fileLocation))
        {
            if (!$line = fgetcsv($this->_fileLocation, 2048, ',', '"', '\\'))
            {
                break;
            }

            if ($this->_quickMode)
            {
                $inserts[] = '("' . implode('","', $line) . '")';

                if (count($inserts) === self::QUICK_COMMIT_SIZE)
                {
                    $this->_pdo->exec(sprintf(
                        'INSERT INTO %slocation VALUES %s',
                        $this->_tablePrefix,
                        implode(',', $inserts)
                    ));
                    $inserts = array();
                }
            }
            else
            {
                $sql = sprintf('INSERT INTO %slocation VALUES ('
                    . ':location_id, :country, :region, :city, :postal_code, '
                    . ':latitude, :longitude, :metro_code, :area_code)',
                    $this->_tablePrefix
                );

                $rs = $this->_pdo->prepare($sql);

                $rs->execute(array(
                    ':location_id'      => (int) $line[0],
                    ':country'          => strtoupper(substr($line[1], 0, 2)),
                    ':region'           => strtoupper(substr($line[2], 0, 2)),
                    ':city'             => substr($line[3], 0, 35),
                    ':postal_code'      => substr($line[4], 0, 10),
                    ':latitude'         => (double) $line[5],
                    ':longitude'        => (double) $line[6],
                    ':metro_code'       => (int) $line[7],
                    ':area_code'        => (int) $line[8]
                ));
            }
        }

        fclose($this->_fileLocation);
        $this->_fileLocation = null;

        if ($this->_quickMode && !empty($inserts))
        {
            $this->_pdo->exec(sprintf(
                'INSERT INTO %slocation VALUES %s',
                $this->_tablePrefix,
                implode(',', $inserts)
            ));
            $inserts = array();
        }
    }

    /**
     * Installs the database tables and loads them with the GeoIP
     * data from the MaxMind source files.
     *
     * @return  PHPStateMapper_Import_GeoIP
     * @throws  PHPStateMapper_Exception_Import
     */
    public function install()
    {
        // Need a little juice for this one
        set_time_limit(180);
        ini_set('memory_limit', '32M');

        if ($this->_fileLocation === null || $this->_fileBlocks === null)
        {
            throw new PHPStateMapper_Exception_Import(
                'Source data files not defined. Call the setSourceFiles method.'
            );
        }

        $this->_createLocationTable();
        $this->_loadLocationTable();

        $this->_createBlocksTable();
        $this->_loadBlocksTable();

        return $this;
    }

    /**
     * Drops the MaxMind tables from the PDO data source.
     *
     * @param   boolean     Add IF EXISTS to DROP statement (not universally supported)
     * @return  PHPStateMapper_Import_GeoIP
     */
    public function delete($ifExists = false)
    {
        $sql = sprintf('DROP TABLE %s%sblocks',
            $ifExists ? 'IF EXISTS ' : '',
            $this->_tablePrefix
        );
        $this->_pdo->exec($sql);

        $sql = sprintf('DROP TABLE %s%slocation',
            $ifExists ? 'IF EXISTS ' : '',
            $this->_tablePrefix
        );
        $this->_pdo->exec($sql);

        return $this;
    }

    /**
     * Looks up an IPv4 network address and translates it to a
     * 2-item array containing a 2-letter ISO country code and a
     * 2-letter region code (in the U.S., this would be state).
     *
     * You must create() and install() the MaxMind tables before
     * calling this method or you will get a PDOException.
     *
     * @param   mixed       IPv4 network address in written or numerical form
     * @return  array       ('US', 'CA') country/region
     */
    public function lookup($ip)
    {
        if (is_string($ip))
        {
            $ip = ip2long($ip);
        }

        $sql = sprintf(
            'SELECT country_code, region '
            . 'FROM %sblocks AS blocks '
            . 'INNER JOIN %1$slocation AS location '
            . 'ON location.location_id = blocks.location_id '
            . 'WHERE ? BETWEEN blocks.range_ip_start AND blocks.range_ip_end',
            $this->_tablePrefix
        );

        $rs = $this->_pdo->prepare($sql);
        $rs->bindParam(1, $ip);
        $rs->execute();

        return $rs->fetch(PDO::FETCH_NUM);
    }
}
