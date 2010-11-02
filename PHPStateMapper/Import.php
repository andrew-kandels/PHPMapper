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

require_once dirname(__FILE__) . '/Exception.php';
require_once dirname(__FILE__) . '/Exception/Import.php';

abstract class PHPStateMapper_Import
{
    public function getRowData()
    {
        return false;
    }
}
