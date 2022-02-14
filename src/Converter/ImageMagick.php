<?php

namespace tei187\QrImage2Svg\Converter;
use \tei187\QrImage2Svg\Converter as Converter;

use function PHPSTORM_META\type;

class ImageMagick extends Converter {
    private $withPrefix = true;
    private $pixelData = null;
    
    /**
     * Constructor.
     *
     * @param string|null $file Filename with extension that is going to be processed.
     * @param string|null $session Session directory.
     * @param integer|null $steps Steps equaling pixels width or height of one tile in QR code.
     * @param integer|null $threshold Threshold (of FF value) over which the tile is considered blank.
     * @param boolean $prefix Switch. On true places "magick" prefix in commands (enviroment-specific).
     */
    function __construct(string $path = null, string $outputDir = null, int $steps = null, int $threshold = null, bool $prefix = true) {
        if(!is_null($path)) $this->setPath($path);
        if(!is_null($outputDir)) $this->setDir($outputDir);
        if(!is_null($steps)) $this->setParamsStep($steps);
        if(!is_null($threshold)) $this->setParamsThreshold($threshold);
        $this->setPrefix($prefix);
    }

    /**
     * Sets prefix on true.
     *
     * @param boolean $prefix
     * @return self
     */
    public function setPrefix(bool $prefix = true) : self {
        $this->withPrefix = $prefix;
        return $this;
    }

    /**
     * Checks wether prefix is to be applied.
     *
     * @return string
     */
    private function checkPrefix() : string {
        $prefix = $this->withPrefix ? "magick " : "";
        return $prefix;
    }

    /**
     * Looks up color value of a pixel per coordinates.
     *
     * @param integer $x Position X.
     * @param integer $y Position Y.
     * @param null $img Leftover from different class.
     * @return array|string|int
     */
    protected function findColorAtIndex(int $x = null, int $y = null, $img = null) {
        $key = $x."x".$y;
        $c = count($this->pixelData[$key]); // count of channels/values found in array

        switch($c) {
            case 1: 
                $color = $this->pixelData[$key][0]; 
                break;
            case 3:
                $color = [
                      'red' => $this->pixelData[$key][0],
                    'green' => $this->pixelData[$key][1],
                     'blue' => $this->pixelData[$key][2],
                ];
                break;
            case 4:
                $color = [
                       'cyan' => $this->pixelData[$key][0],
                    'magenta' => $this->pixelData[$key][1],
                     'yellow' => $this->pixelData[$key][2],
                      'black' => $this->pixelData[$key][3],
                ];
                break;
            default: 
                $color = false;
        }
        return $color;
    }

    /**
     * Finds color data on a pixel of center of each tile in the QR code.
     * 
     * @return void
     */
    public function getPixelData() : void {
        $temp = [];
        
        foreach($this->calculated['blockMiddlePositions'] as $c) {
            $temp[] = "%[pixel:s.p{".$c[0].",".$c[1]."}]";
        }
        
        $temp_chunks = array_chunk($temp, 50);
        unset($temp);
        $output = "";

        foreach($temp_chunks as $chunk) {
            $str = implode("..", $chunk);
            $output .= shell_exec("magick identify -format \(".$str."\) ".$this->path)."..";
        }
        unset($temp_chunks, $chunk);
        
        $temp = array_map(
            function($v) {
                if($v !== null && strlen(trim($v)) > 0) {
                    preg_match_all("/\d+/", $v, $match); 
                    return $match[0];
                }
            }, explode("..", trim($output, "."))
        );
        unset($output, $match);

        foreach($temp as $k => $v) {
            $pos = [
                'x' => $this->calculated['blockMiddlePositions'][$k][0],
                'y' => $this->calculated['blockMiddlePositions'][$k][1]
            ];
            $key = $pos['x']."x".$pos['y'];
            $this->pixelData[$key] = $v;
        }
    }

    /**
     * @todo SECURE FILE PATH INSERTION
     */

    /**
     * Sets properties for image dimensions and returns as array.
     *
     * @return void
     */
    protected function getDimensions() : void {
        $cmd = $this->checkPrefix() . "identify -format \"%wx%h\" ". $this->getPath();
        list(
            $this->image['w'], 
            $this->image['h']
        ) = explode("x", shell_exec($cmd), 2);
    }

    /**
     * Dummy output method.
     *
     * @return string SVG formatted QR code.
     */
    public function output() : string {
        $this->getDimensions();
        $this->setMaxSteps();
        $this->setMiddlePositions();
        $this->getPixelData();
        $this->setFillByBlockMiddlePositions();
        return $this->generateSVG();
    }
}


?>