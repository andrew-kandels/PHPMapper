<?php
/**
 * PHPStateMapper_Map_Image
 *
 * Model for loading the map image, shading in regions, and outputting
 * the result.
 *
 * @package     PHPStateMapper
 * @author      Andrew Kandels <me@andrewkandels.com>
 * @access      public
 */

class PHPStateMapper_Map_Image
{
    protected $_maxWidth    = null;
    protected $_maxHeight   = null;
    protected $_image       = null;

    /**
     * Creates a PHPStateMapper_Map class object.
     *
     * @param   string      Image file
     * @param   integer     Number of map regions
     * @return  void
     * @throws  PHPStateMapper_Exception
     */
    public function __construct($file, $numRegions)
    {
        // GD is required for image processing
        if (!extension_loaded('gd') || !function_exists('imagecreatefrompng'))
        {
            throw new PHPStateMapper_Exception(
                'The PHP GD extension is required.'
            );;
        }

        if (!$this->_image = imagecreatefrompng($file))
        {
            throw new PHPStateMapper_Exception_Image("Failed to load {$file}.");
        }

        // White-out pixels that aren't valid regions
        for ($i = 0; $i < imagecolorstotal($this->_image); $i++)
        {
            $raw = imagecolorsforindex($this->_image, $i);
            $hex = $this->convertRgbToHex($raw['red'], $raw['green'], $raw['blue']);
            if (($raw['red'] != $raw['green'] || $raw['green'] != $raw['blue'] ||
                $raw['red'] > $numRegions) && $hex != 'ffffff')
            {
                imagecolorset($this->_image, $i, 255, 255, 255);
            }
        }

        list($this->_maxWidth, $this->_maxHeight) = getimagesize($file);
    }

    /**
     * Converts a hexidecimal representation of a color (think: XHTML/CSS) into
     * an RGB 3-item array.
     *
     * @param   string      Hexidecimal color representation
     * @return  array       (R,G,B)
     */
    public function convertHexToRgb($hex)
    {
        list($r, $g, $b) = array(
            $hex[0] . $hex[1],
            $hex[2] . $hex[3],
            $hex[4] . $hex[5]
        );

        return array(hexdec($r), hexdec($g), hexdec($b));
    }

    /**
     * Converts a RGB representation of a color to a hexidecimal one.
     *
     * @param   integer     Red
     * @param   integer     Green
     * @param   integer     Blue
     * @return  string      Hex
     */
    public function convertRgbToHex($r, $g, $b)
    {
        $r = dechex($r<0?0:($r>255?255:$r));
        $g = dechex($g<0?0:($g>255?255:$g));
        $b = dechex($b<0?0:($b>255?255:$b));

        $color = (strlen($r) < 2?'0':'').$r;
        $color .= (strlen($g) < 2?'0':'').$g;
        $color .= (strlen($b) < 2?'0':'').$b;

        return $color;
    }

    /**
     * Returns the color setting with its intensity set to the item's
     * value.
     *
     * @param   array       RGB color
     * @param   float       Percentage of intensity
     * @return  array       (R,G,B)
     */
    public function getColorAlpha($color, $pct)
    {
        if ($pct > 1) $pct = 1;
        if ($color === null) $color = $this->_color;
        return array(
            ((1 - $pct) * 255) + ($pct * $color[0]),
            ((1 - $pct) * 255) + ($pct * $color[1]),
            ((1 - $pct) * 255) + ($pct * $color[2])
        );
    }

    /**
     * Resizes the image a new width while maintaining aspect ratio.
     *
     * @param   integer     Width in pixels
     * @return  PHPStateMapper_Image
     */
    public function resize($width)
    {
        // Resize the image while maintaining ratio
        $ratio  = $this->_maxHeight / $this->_maxWidth;
        $height = floor($width * $ratio);
        $out    = imagecreate($width, $height);

        imagealphablending($out, false);
        imagesavealpha($out, false);
        imagecopyresampled($out, $this->_image, 0, 0, 0, 0, $width, $height,
            $this->_maxWidth, $this->_maxHeight
        );

        imagedestroy($this->_image);
        $this->_image = $out;

        return $this;
    }

    /**
     * Outputs a GD image object either to the browser or to a file.
     *
     * @param   string      File name or null for standard out
     * @return  PHPStateMapper_Image
     * @throws  PHPStateMapper_Exception_Image
     */
    public function draw($file = null, $compression = 4)
    {
        if (!$file)
        {
            header('Content-type: image/png');
        }

        if (!imagepng($this->_image, $file, $compression))
        {
            throw new PHPStateMapper_Exception_Image("Failed to create $file");
        }

        imagedestroy($this->_image);

        if (!$file)
        {
            die();
        }

        return $this;
    }

    /**
     * Takes the input value of a color in either RGB or as a hex string
     * and converts it to an RGB array.
     *
     * @param   mixed       RGB color or hex string
     * @return  array       RGB
     * @throws  PHPStateMapper_Exception_BadColorValue
     */
    public function getRgbColorFromInput($color)
    {
        if (is_array($color))
        {
            if (count($color) != 3)
            {
                throw new PHPStateMapper_Exception_BadColorValue();
            }
            else
            {
                $color = $color;
            }
        }
        else
        {
            $color = $this->convertHexToRgb($color);
        }

        return $color;
    }

    /**
     * Shades in a region of the map to the percentage (alpha) of
     * a provided color.
     *
     * @param   integer     Region ID # (auto-increment)
     * @param   mixed       Color (RGB array or hex color)
     * @param   float       Alpha percentage (1 = fully visible, 0 = fully opaque)
     * @return  PHPStateMapper_Image
     */
    public function setRegion($regionId, $color, $pct = 1.0)
    {
        // Get the color assigned to the item
        $r = $g = $b = $regionId;
        $index = imagecolorexact($this->_image, $r, $g, $b);

        // Pull up to the minimum to avoid white-out
        if ($pct < PHPStateMapper::MIN_THRESHOLD)
        {
            $pct = PHPStateMapper::MIN_THRESHOLD;
        }

        // Detect craziness
        if ($pct > 1)
        {
            throw new PHPStateMapper_Exception_BadColorValue(
                "Alpha percentage should be 0 - 1, not $pct."
            );
        }

        // Get the color after applying the alpha
        list($r, $g, $b) = $this->getColorAlpha(
            $this->getRgbColorFromInput($color),
            $pct
        );

        // Replace the base color with the new alpha version
        imagecolorset($this->_image, $index, $r, $g, $b);

        return $this;
    }
}
