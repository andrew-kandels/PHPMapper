<?php
class PHPMapper_Map_ImageTest extends PHPMapper_TestCase
{
    private $_obj;
    private $_image;

    public function setUp()
    {
        $this->_obj = new PHPMapper();
        $this->_image = $this->_obj->getImage();
    }

    public function tearDown()
    {
    }

    public function testSetNumAreasTooMany()
    {
        try
        {
            $this->_image->setNumAreas(255);
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testSetNumAreasNegative()
    {
        try
        {
            $this->_image->setNumAreas(-1);
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }

        $this->assertTrue(false);
    }

    public function testConvertHexToRgb()
    {
        $rgb = $this->_image->convertHexToRgb('f0c0d9');
        $this->assertEquals(array(240, 192, 217), $rgb);
    }

    public function testConvertRgbToHex()
    {
        $hex = $this->_image->convertRgbToHex(240, 192, 217);
        $this->assertEquals('f0c0d9', $hex);
    }

    public function testGetColorAlpha()
    {
        $alpha = $this->_image->getColorAlpha(array(240, 192, 217), 0.25);
        $this->assertEquals(array(251, 239, 245), $alpha);
    }

    public function testResizeTooBig()
    {
        try
        {
            $this->_image->resize(4000);
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }

        $this->assertFalse(true);
    }

    public function testResizeTooSmall()
    {
        try
        {
            $this->_image->resize(1);
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }

        $this->assertFalse(true);
    }

    public function testResizeIsSmaller()
    {
        $this->_obj->setWidth(1000);
        $this->_obj->draw($file1 = '/tmp/image1.png');

        $this->_obj = new PHPMapper();
        $this->_obj->setWidth(500);
        $this->_obj->draw($file2 = '/tmp/image2.png');

        $this->assertTrue(filesize($file2) < filesize($file1));
        @unlink($file1);
        @unlink($file2);
    }

    public function testGetRgbColorFromInputHex()
    {
        $rgb = $this->_image->getRgbColorFromInput('f0c0d9');
        $this->assertEquals(array(240, 192, 217), $rgb);
    }

    public function testGetRgbColorFromInputRgb()
    {
        $rgb = $this->_image->getRgbColorFromInput(array(240, 192, 217));
        $this->assertEquals(array(240, 192, 217), $rgb);
    }

    public function testGetRgbColorFromInputBadRgb()
    {
        try
        {
            $this->_image->getRgbColorFromInput(array(240, 192, 217, 99));
        }
        catch (PHPMapper_Exception_BadColorValue $e)
        {
            return;
        }
        $this->assertFalse(true);
    }

    public function testGetRgbColorFromInputBadHex()
    {
        try
        {
            $this->_image->getRgbColorFromInput('f0c0d9zzz');
        }
        catch (PHPMapper_Exception_BadColorValue $e)
        {
            return;
        }
        $this->assertFalse(true);
    }

    public function testSetShadingTooHigh()
    {
        try
        {
            $this->_image->setShading(255, 'f0c0d9');
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }
        $this->assertFalse(true);
    }

    public function testSetShadingTooLow()
    {
        try
        {
            $this->_image->setShading(-1, 'f0c0d9');
        }
        catch (PHPMapper_Exception_Image $e)
        {
            return;
        }
        $this->assertFalse(true);
    }

    public function testSetShadingAlphaTooHigh()
    {
        try
        {
            $this->_image->setShading(1, 'f0c0d9', 1.1);
        }
        catch (PHPMapper_Exception_BadColorValue $e)
        {
            return;
        }
        $this->assertFalse(true);
    }

    public function testSetShading()
    {
        $this->_image->setShading(1, 'f0c0d9', 1);
        // Testing for exception
        $this->assertTrue(true);
    }

    public function testDraw()
    {
        $this->_image->draw($file = '/tmp/image1.png');
        $this->assertTrue(filesize($file) > 0);
        @unlink($file);
    }
}
