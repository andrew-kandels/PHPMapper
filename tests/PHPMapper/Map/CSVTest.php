<?php
class PHPMapper_Map_CSVTest extends PHPMapper_TestCase
{
    private $_obj;
    private $_csv;

    public function setUp()
    {
        $this->_obj = new PHPMapper();
        $this->_csv = new PHPMapper_Map_CSV($this->_obj->getBase() . 'csv');
    }

    public function tearDown()
    {
    }

    public function testBadFile()
    {
        try
        {
            $csv = new PHPMapper_Map_CSV('bad file');
        }
        catch (PHPMapper_Exception $e)
        {
            return;
        }
        $this->assertTrue(false);
    }

    public function testGet()
    {
        $data = $this->_csv->get();
        $this->assertEquals(array(1, 'AD', null), $data);
    }

    public function testGetFail()
    {
        file_put_contents($file = '/tmp/data.csv', "US");
        $this->_csv->setFile($file);
        try
        {
            $this->_csv->get();
        }
        catch (PHPMapper_Exception_Import $e)
        {
            @unlink($file);
            return;
        }
        @unlink($file);
        $this->assertFalse(true);
    }
}
