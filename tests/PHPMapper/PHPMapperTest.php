<?php
class PHPMapperTest extends PHPMapper_TestCase
{
    private $_obj;

    public function setUp()
    {
        $this->_obj = new PHPMapper();
    }

    public function tearDown()
    {
    }

    public function testIsWorldMap()
    {
        $this->assertContains('/PHPMapper/maps/world.', $this->_obj->getBase());
    }

    public function testHasImageObject()
    {
        $this->assertTrue($this->_obj->getImage() instanceof PHPMapper_Map_Image);
    }

    public function testSetGoodColor()
    {
        $this->_obj->setColor($color = 'ffffff');
        $this->assertEquals($color, $this->_obj->getColor());
    }

    public function testSetGoodColorArray()
    {
        $this->_obj->setColor($color = array(255, 255, 255));
        $this->assertEquals($color, $this->_obj->getColor());
    }

    public function testSetBadColorHex()
    {
        try
        {
            $this->_obj->setColor($color = 'zzzzzz');
        }
        catch (PHPMapper_Exception $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testSetBadColorLength()
    {
        try
        {
            $this->_obj->setColor($color = '#ffffff');
        }
        catch (PHPMapper_Exception $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testSetBadColorArray()
    {
        try
        {
            $this->_obj->setColor($color = array(255, 255, 255, 255));
        }
        catch (PHPMapper_Exception $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testSetSmallWidth()
    {
        try
        {
            $this->_obj->setWidth(1);
        }
        catch (PHPMapper_Exception $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testSetWidth()
    {
        $this->_obj->setWidth($width = 500);
        $this->assertEquals($width, $this->_obj->getWidth());
    }

    public function testImport()
    {
        $data = new PHPMapper_Import_Array();
        $data->addData(array(
            'US', 'MN', 5
        ));
        $this->_obj->import($data);
        $id = $this->_obj->lookup('US', 'MN');
        $this->assertEquals(1, $this->_obj->getAreaAlphaPct($id, 5, 1));
    }

    public function testLookup()
    {
        $id = $this->_obj->lookup('US');
        $this->assertEquals(230, $id);
    }

    public function testLookupFail()
    {
        $id = $this->_obj->lookup('Bad');
        $this->assertFalse($id);
    }

    public function testAdd()
    {
        $this->_obj->add('US', null, 5, 1);
        $id = $this->_obj->lookup('US');

        $this->assertEquals(5, $this->_obj->get($id));
        $this->_obj->add('US', null, 5, 1);
        $this->assertEquals(10, $this->_obj->get($id));
    }

    public function testSet()
    {
        $this->_obj->set('US', null, 5, 1);
        $id = $this->_obj->lookup('US');
        $this->assertEquals(5, $this->_obj->get($id));
        $this->_obj->set('US', null, 1, 1);
        $this->assertEquals(1, $this->_obj->get($id));
    }

    public function testGetMaxValue()
    {
        $this->_obj->set('US', null, 5);
        $this->_obj->set('CA', null, 4);
        $val = $this->_obj->getMaxValue();
        $this->assertEquals($val, 5);
    }

    public function testGetMaxValueWithSeries()
    {
        $this->_obj->set('US', null, 5, 2);
        $this->_obj->set('CA', null, 4, 1);
        $val = $this->_obj->getMaxValue(1);
        $this->assertEquals($val, 4);
    }

    public function testSetTargetValue()
    {
        $this->_obj->set('US', null, 5);
        $this->_obj->setTargetValue(10);
        $id = $this->_obj->lookup('US');
        $pct = $this->_obj->getAreaAlphaPct($id, $this->_obj->getMaxValue());
        $this->assertEquals(0.5, $pct);
    }

    public function testSetMap()
    {
        $this->_obj->setMap('us');
        $id = $this->_obj->lookup('US', 'MN');
        $this->assertEquals(23, $id);
    }

    public function testSetMapToBadMap()
    {
        try
        {
            $this->_obj->setMap('nobody');
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testGetMaxValueWithTarget()
    {
        $this->assertEquals(0, $this->_obj->getMaxValue());
        $this->_obj->setTargetValue(5);
        $this->assertEquals(5, $this->_obj->getMaxValue());
    }

    public function testDraw()
    {
        $this->_obj->set('US', null, 5);
        $this->_obj->draw($file = '/tmp/test.png');
        $this->assertTrue(filesize($file) > 0);
        @unlink($file);
    }
}

