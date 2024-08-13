![QR image-2-svg converter](https://repository-images.githubusercontent.com/457393906/2829ff00-7eb6-45f5-871f-be9eec7e0576)

# **QR image 2 SVG converter**

[DEMO APP](https://qrgen.xowergs.wirt16.bhlink.pl) *(not user friendly at the moment, looks messy, lots of reclicking)*

---

## **About**
Library meant to easily convert bitmap image-based QR codes to SVG equivalent form. Supports GD library, Imagick extension and ImageMagick command line prompts.

  ### **Mechanics**
  The concept here is to assign a grid over the QR image, find the center position of each generated tile and read the color value of the corresponding pixel. Then, basing on the assigned threshold value, resolve the SVG render with gathered data.

  ### **Story behind it**
  The main reason to create this solution was my day-to-day job, where I have to handle graphic files for prepress solutions. Sadly, many graphic designers still do not seem to understand that, often enough, bitmap images will offer a lower quality than their vector alternatives in print, not matter how far you will crank up the image resolution (it has to do with how images are processed by RIP systems to raster dots form). In the company I work for, we often introduce amendments to graphic files in order to receive the best possible quality in print. However, converting QR codes from bitmap to vector objects is not as easy or as fun as it should (no, contour tracing in many cases is not a viable option), so we were in need of yet another in-house solution to speed up yet another process. Came up with this package.

---

## **Classes overview**

| **Namespace**                                           | **Description**                                        |
|:--------------------------------------------------------|:-------------------------------------------------------|
| tei187\QrImage2Svg\\**Configuration**                   | Holds configuration for the process                    |
| tei187\QrImage2Svg\\**Processor**                       | Abstract processor class                               |
|                                                         |                                                        |
| tei187\QrImage2Svg\Processor\\**GD**                    | GD library processor                                 |
| tei187\QrImage2Svg\Processor\\**Imagick**               | Imagick extension processor                            |
| tei187\QrImage2Svg\Processor\\**ImageMagick**           | ImageMagick command line processor                     |
|                                                         |                                                        |
| tei187\QrImage2Svg\Processors\ImageMagick\\**Commands** | ImageMagick commands used in command prompt *(static)* |
|                                                         |                                                        |
| tei187\QrImage2Svg\Resources\\**MIME**                  | MIME types class handlers *(static)*                   |
| tei187\QrImage2Svg\Utilities\\**PathValidator**         | Path validator class *(static)*                        |


## **Usage**

* ### **Installation**

  * Composer
  ```shell
  composer require tei187/qr-image2svg
  ```
  * Manual (package download)
  ```php
    require_once( PATH_TO_EXTRACTED_PACKAGE . "/index.php" );
  ```

* ### **Configuration**

  Configuration class holds config on which the process will be based and is required for the process to run. It involves the following:
  | Parameter Name | Parameter Type | Description |
  |----------------|:--------------:|-------------|
  | inputDir | string | Path to the image file to be converted |
  | outputDir | string | Path to the directory where the converted file will be saved |
  | filename | string | Name of the input file |
  | steps | int \| null | Number of steps per axis (how many tiles/modules per axis are there in the QR image) |
  | threshold | int | Threshold value (0-255) |
  | channel | string | Color channel (red, green, blue) |
  | imUseMagickPrefix | bool | Prefix for ImageMagick commands (IM-specific) |
  | imUseConvertPrefix | bool | Prefix for ImageMagick commands (IM-specific) |  

  #### Example

  ```php
  <?php
    use tei187\QrImage2Svg\Configuration;

    $config = new Configuration(
      "/path/to/input",  // input path
      "/path/to/output", // output path
      "input.png",       // input file name
      null,              // no pre-assigned steps per axis
      200,               // threshold
      'red',             // red channel for threshold
      false,             // dont use 'magick' prefix in command line
      true               // use 'convert' prefix in command line
    );

    // (...)
  ```

* ### **Creating objects**

  ```php
    // (...)

    use tei187\QrImage2Svg\Processor\GD as GD;
    use tei187\QrImage2Svg\Processor\ImageMagick as ImageMagick;
    use tei187\QrImage2Svg\Processor\Imagick as Imagick;

    // GD-based library processor
    $gd = new GD($config);

    // ImageMagick-based command line prompt processor
    $im = new ImageMagick($config);

    // Imagick-based extension processor
    $i  = new Imagick($config);

    // (...)
  ```

* ### **Converting**

  ```php
    // (...)
    
    // GD
    $gd->output();

    // IM
    $im->output();

    // Imagick
    $i->output();
    
  ```
  These will output a generated SVG file in the output directory with the same name as the input file. Output also uses a bool flag to determine whether the module count search should be performed or not. If `->output(false)` is used, the module count search will not be performed and conversion will be based on steps count defined in Configuration class.
  
  ---

  | **Note:** |
  |---|
  | GD and Imagick extensions processors should be considered fastest. ImageMagick processor is slower, due to constantly referencing files on-disk, while the other two are in-memory only. As such, ImageMagick processor should be used as a fallback only when GD or Imagick are not available. Also, ImageMagick processor gives very varied results in performance as well as creates temporary files on-disk, which can be problematic in some cases. It is recommended to use GD or Imagick processors if possible. |
  
---

## **Parameters & input requirements**
* ### **File**

  Package supports JPG, GIF, PNG, BMP and WEBP image file types for GD subclass. In case of ImageMagick it becomes installation dependent - to check whether your installation supports previously listed image types, use `'magick identify -list format'` command.

  Input image files should be trimmed around the QR code, so the image does not have any additional margin for quiet zone. It can be handled by the package (`_trimImage` method), but relies heavily on how straightforward the image is - it may produce errors on blurred or skewed .
  
  Furthermore, images should be prepared in standard fashion: filled/black square tiles having average color values lower than threshold, blank/white tiles having higher.

  Other requirements:
  * images *should* be trimmed, as in "without quiet zone" (it can be done through the package, using `::trimImage` method, but requires a well polished image),
  * images *should* be prepared in standard QR fashion: filled/black square tiles having average color values lower than threshold, blank/white square tiles having higher,
  * images *should* not be blured (in practice they should not be excesively blured, package works with some slight bluring or antialiasing quite well though),
  * images **cannot** be skewed, irregularly transformed or otherwise malformed.

* ### **Steps**

  Steps describe the amount of matrix tiles per X or Y axis of the QR code, otherwise called *"modules"*. In this scope, a tile is considered a single position in QR code's matrix. It's length (in bitmap image scope) is best described as the pixels width of vertical border in the top left part of the code. The amount of steps translates roughly to `{image width} / {average tile length}`, rounded up to integer value. **However**, it does not have to be this exact value - it is largely dependent on the input image's parameters and rendering, like antialiasing of the edges, compression level, etc. As such, **this value may have to be adjusted manually**.

  <br>

  An additional mechanism is introduced within `suggestTilesQuantity()` method, where the processor looks up the width of the top left marker, using top border width as a designator. This length will always be equivalent to 7 QR tiles, so `{marker length} / 7` will return average length of a single tile expressed in pixels (float, for calculation). Next, marker length is being used to find the position of timing pattern lanes, after which it calculates the interruptions in this specific row. 
  This method returns more solid, automatic assignment of steps, and perhaps may replace manual assignment in the future. For now, it has not been thorougly tested - all I can say is that a testing sample pool passed with flying colors, where the same pool did fail in some samples while using previous methodology. It is however suggested for the image's tiles to be at least 4x4 pixels - lower values, especially with higher density of the QR, will give off-values, due to too short distance to sample.

  <br>

  Alternatively, one can also designate the steps parameter if the QR version is known. In this case, steps will equal the number of modules per axis, which equates to `({version} * 4) + 17`.

* ### **Threshold**

  The probing of tiles is done by averaging color channel's values (average being `(R + G + B) / 3`). It should not matter with black and white QR codes, since filled and blank tiles should be very distinctly different. However, it may be more tricky with different hues, saturation and lightness settings. Needs some manual input and control. For the time being, I suggest processing the images through by a CLUT or some such, until I expand a bit on the package.

* ### **Trimming**

  Trimming of the border is done through simulating a threshold of `127-255` and removing white margin. This may become an issue if your QR is inverted (bright fills with darker background) or both, tiles and background fills, have higher threshold than 127. In the second option, the method will return `FALSE` and not trim the image.

---

## **Future plans:**
* ~~suggest grid step length automatically~~ *(done, lookup `suggestTilesQuantity()` public method)*
* ~~expand grid suggestion algorithm to check for minimal average tile length~~
* allow specific channels thresholds
* redrawing into different shapes (e.g. circle)

## **Requirements**
* **PHP 7.4** or higher
* one of the following processors:
  * **GD** extension
  * **Imagick** extension
  * **ImageMagick** *(tested on **7.0.8-7 Q16 x64** and **7.1.0-2 Q16 x64**, did not seem to work properly on versions lower than 7.0 and any version of GraphicsMagick)*

## **Links**
* [QR codes article @ Wikipedia](https://en.wikipedia.org/wiki/QR_code)
* [ImageMagick Homepage](https://imagemagick.org/)
* [ImageMagick Command Line Interface](https://imagemagick.org/script/command-line-processing.php)
* [Imagick PHP extension](https://www.php.net/manual/en/book.imagick.php)
* [GD PHP library](https://www.php.net/manual/en/book.image.php)