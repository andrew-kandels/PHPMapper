<?php
class PHPMapper_Import_CSVTest extends PHPMapper_TestCase
{
    private $_csv;

    public function setUp()
    {
        file_put_contents($file = '/tmp/data.csv',
            "country\tregion\tvalue\n"
            . "US\tMN\t15\n"
            . "Bad line\n"
        );

        $this->_csv = new PHPMapper_Import_CSV(
            $file,
            true,
            128,
            "\t",
            '"',
            '\\'
        );
    }

    public function tearDown()
    {
        @unlink('/tmp/data.csv');
    }

    public function testBadFile()
    {
        try
        {
            $this->_csv = new PHPMapper_Import_CSV('bad file');
        }
        catch (PHPMapper_Exception_Import $e)
        {
            return;
        }
        $this->assertTrue(false);
    }

    public function testEmptyFile()
    {
        $file = file_put_contents($file = '/tmp/empty.csv', '');
        try
        {
            $this->_csv = new PHPMapper_Import_CSV($file);
        }
        catch (PHPMapper_Exception_Import $e)
        {
            return;
        }
        $this->assertTrue(false);
    }

    public function testGetColumn()
    {
        $this->assertEquals(1, $this->_csv->getColumn('region'));
        $this->_csv = new PHPMapper_Import_CSV('/tmp/data.csv', false, 128, "\t");
        $this->assertEquals(1, $this->_csv->getColumn(1));
    }

    public function testGetHeaders()
    {
        $this->assertEquals(array('country', 'region', 'value'), $this->_csv->getHeaders());
    }

    public function testGetRowData()
    {
        $data = $this->_csv->getRowData();
        $this->assertEquals(array('US', null, 1), $data);
    }

    public function testGetBadRowData()
    {
        $data = $this->_csv->getRowData();

        try
        {
            $data = $this->_csv->getRowData();
        }
        catch (PHPMapper_Exception_Import $e)
        {
            return;
        }
        $this->assertTrue(false);
    }

    public function testGetRowDataRegionForCountry()
    {
        $this->_csv->map(PHPMapper::COUNTRY, 'region');
        $data = $this->_csv->getRowData();
        $this->assertEquals(array('MN', null, 1), $data);
    }

    public function testMap()
    {
        $this->_csv->map(PHPMapper::COUNTRY, 'country');
        $this->_csv->map(PHPMapper::REGION, 'region');
        $this->_csv->map(PHPMapper::VALUE, 'value');
        $data = $this->_csv->getRowData();
        $this->assertEquals(array('US', 'MN', 15), $data);
    }

    public function testInvalidCountry()
    {
        file_put_contents($file = '/tmp/data2.csv', "Longname\tMN\t1");
        $this->_csv = new PHPMapper_Import_CSV($file, false, 128, "\t");
        $this->_csv->map(PHPMapper::COUNTRY, 0);
        try
        {
            $this->_csv->getRowData();
        }
        catch (PHPMapper_Exception_Import $e)
        {
            @unlink($file);
            return;
        }
        $this->assertTrue(false);
        @unlink($file);
    }

    public function testForEOF()
    {
        file_put_contents($file = '/tmp/data2.csv', "US\tMN\t1");
        $this->_csv = new PHPMapper_Import_CSV($file, false, 128, "\t");
        for ($i = 0; $i < 2; $i++)
        {
            $value = $this->_csv->getRowData();
        }
        $this->assertFalse($value);
    }
}
