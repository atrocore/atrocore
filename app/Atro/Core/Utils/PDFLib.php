<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils;

use Espo\Core\Utils\Config;

class PDFLib
{
    public static $MAX_RESOLUTION = 300;
    public static $IMAGE_FORMAT_PNG = "PNG";
    public static $IMAGE_FORMAT_JPEG = "JPEG";
    private $resolution;
    private $jpeg_quality;
    private $page_start;
    private $page_end;
    private $pdf_path;
    private $output_path;
    private $number_of_pages;
    private $imageDeviceCommand;
    private $imageExtention;
    private $pngDownScaleFactor;

    private $is_os_win = null;
    private $gs_command = null;
    private $gs_version = null;
    private $gs_is_64 = null;
    private $gs_path = null;

    private $config = null;

    public function __construct(Config $config = null)
    {
        $this->resolution = 0;
        $this->jpeg_quality = 100;
        $this->page_start = -1;
        $this->page_end = -1;
        $this->pdf_path = "";
        $this->output_path = "";
        $this->number_of_pages = -1;
        $this->imageDeviceCommand = "";
        $this->imageExtention = "";
        $this->pngDownScaleFactor = "";
        $this->file_prefix = "page-";

        $this->setDPI(self::$MAX_RESOLUTION);
        $this->setImageFormat(self::$IMAGE_FORMAT_JPEG);
        $this->initSystem();
        $gs_version = $this->getGSVersion();
        if ($gs_version == -1) {
            throw new \Exception("Unable to find GhostScript instalation", 404);
        } else {
            if ($gs_version < 9.16) {
                throw new \Exception("Your version of GhostScript $gs_version is not compatible with  the library", 403);
            }
        }

        if (!empty($config)) {
            $this->config = $config;
        }
    }

    /**
     * Set the path to the PDF file to process
     *
     * @param string $pdf_path
     *
     * @return self
     */
    public function setPdfPath($pdf_path)
    {
        $this->pdf_path = $pdf_path;
        $this->number_of_pages = -1;
        return $this;
    }

    /**
     * Set the output path to where to store the generated files
     *
     * @param string $output_path
     *
     * @return self
     */
    public function setOutputPath($output_path)
    {
        $this->output_path = $output_path;
        return $this;
    }

    /**
     * Change the generated JPG quality from the default of 100
     *
     * @param integer $jpeg_quality
     *
     * @return self
     */
    public function setImageQuality($jpeg_quality)
    {
        $this->jpeg_quality = $jpeg_quality;
        return $this;
    }

    /**
     * Set a start and end page to process.
     *
     * @param integer $start
     * @param integer $end
     *
     * @return self
     */
    public function setPageRange($start, $end)
    {
        $this->page_start = $start;
        $this->page_end = $end;
        return $this;
    }

    /**
     * Change the resolution of the output file from the default 300dpi
     *
     * @param integer $end
     *
     * @return self
     */
    public function setDPI($dpi)
    {
        $this->resolution = $dpi;
        return $this;
    }

    /**
     * Change the default file prefix from "page-" to something else
     *
     * @param string $fileprefix
     *
     * @return self
     */
    public function setFilePrefix($fileprefix)
    {
        $this->file_prefix = $fileprefix;
        return $this;
    }

    /**
     * Change the image format to PNG or JPEG
     *
     * @param string $imageformat
     * @param float  $pngScaleFactor
     *
     * @return self
     */
    public function setImageFormat($imageformat)
    {
        $pngScaleFactor = !empty($this->config) ? $this->config->get('gsDownScaleFactor') : null;

        if ($imageformat == self::$IMAGE_FORMAT_JPEG) {
            $this->imageDeviceCommand = "jpeg";
            $this->imageExtention = "jpg";
            $this->pngDownScaleFactor = !empty($pngScaleFactor) ? "-dDownScaleFactor=" . $pngScaleFactor : "";
        } else {
            if ($imageformat == self::$IMAGE_FORMAT_PNG) {
                $this->imageDeviceCommand = "png16m";
                $this->imageExtention = "png";
                $this->pngDownScaleFactor = !empty($pngScaleFactor) ? "-dDownScaleFactor=" . $pngScaleFactor : "";
            }
        }
        return $this;
    }

    public function getNumberOfPages()
    {
        if ($this->number_of_pages == -1) {
            if ($this->gs_command == "gswin32c.exe" || $this->gs_command == "gswin64c.exe") {
                $this->pdf_path = str_replace('\\', '/', $this->pdf_path);
            }
            $pages = $this->executeGS('-q -dNODISPLAY -dNOSAFER -c "(' . $this->pdf_path . ') (r) file runpdfbegin pdfpagecount = quit"', true);
            $this->number_of_pages = intval($pages);
        }
        return $this->number_of_pages;
    }


    public function convert()
    {
        if (!(($this->page_start > 0) && ($this->page_start <= $this->getNumberOfPages()))) {
            $this->page_start = 1;
        }

        if (!(($this->page_end <= $this->getNumberOfPages()) && ($this->page_end >= $this->page_start))) {
            $this->page_end = $this->getNumberOfPages();
        }

        if (!($this->resolution <= self::$MAX_RESOLUTION)) {
            $this->resolution = self::$MAX_RESOLUTION;
        }

        if (!($this->jpeg_quality >= 1 && $this->jpeg_quality <= 100)) {
            $this->jpeg_quality = 100;
        }
        $image_path = $this->output_path . "/" . $this->file_prefix . "%d." . $this->imageExtention;
        $output = $this->executeGS(
            "-dSAFER -dBATCH -dNOPAUSE -sDEVICE=" . $this->imageDeviceCommand . " " . $this->pngDownScaleFactor . " -r" . $this->resolution
            . " -dNumRenderingThreads=4 -dFirstPage=" . $this->page_start . " -dLastPage=" . $this->page_end . " -o\"" . $image_path . "\" -dJPEGQ=" . $this->jpeg_quality
            . " -q \"" . ($this->pdf_path) . "\" -c quit"
        );

        $fileArray = [];
        for ($i = 1; $i <= ($this->page_end - $this->page_start + 1); ++$i) {
            $fileArray[] = $this->file_prefix . "$i." . $this->imageExtention;
        }
        if (!$this->checkFilesExists($this->output_path, $fileArray)) {
            $errrorinfo = implode(",", $output);
            throw new \Exception('PDF_CONVERSION_ERROR ' . $errrorinfo);
        }
        return $fileArray;
    }

    public function makePDF($ouput_path_pdf_name, $imagePathArray)
    {
        $imagesources = "";
        foreach ($imagePathArray as $singleImage) {
            if ($this->gs_command == "gswin32c.exe" || $this->gs_command == "gswin64c.exe") {
                $singleImage = str_replace('\\', '/', $singleImage);
            }
            $imagesources .= '(' . $singleImage . ')  viewJPEG showpage ';
        }
        $psfile = $this->getGSLibFilePath("viewjpeg.ps");
        $command = '-dBATCH -dNOPAUSE -sDEVICE=pdfwrite -o"' . $ouput_path_pdf_name . '" "' . $psfile . '" -c "' . $imagesources . '"';
        $command_results = $this->executeGS($command);
        if (!$this->checkFilesExists("", [$ouput_path_pdf_name])) {
            throw new \Exception("Unable to make PDF : " . $command_results[2], 500);
        }
    }

    public function getGSVersion()
    {
        return $this->gs_version ? $this->gs_version : -1;
    }

    private function initSystem()
    {
        $this->is_os_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($this->gs_path == null || $this->gs_version == null || $this->gs_is_64 == null) {
            if ($this->is_os_win) {
                if (trim($gs_bin_path = $this->execute("where gswin64c.exe", true)) != "") {
                    $this->gs_is_64 = true;
                    $this->gs_command = "gswin64c.exe";
                    $this->gs_path = trim(str_replace("bin\\" . $this->gs_command, "", $gs_bin_path));
                } else {
                    if (trim($gs_bin_path = $this->execute("where gswin32c.exe", true)) != "") {
                        $this->gs_is_64 = false;
                        $this->gs_command = "gswin32c.exe";
                        $this->gs_path = trim(str_replace("bin\\" . $this->gs_command, "", $gs_bin_path));
                    } else {
                        $this->gs_is_64 = null;
                        $this->gs_path = null;
                        die($this->execute("where gswin64c.exe", true));
                    }
                }
                if ($this->gs_path && $this->gs_command) {
                    $output = $this->execute($this->gs_command . ' --version 2>&1');
                    $this->gs_version = doubleval($output[0]);
                }
            } else {
                $output = $this->execute('gs --version 2>&1');
                if (!((is_array($output) && (strpos($output[0], 'is not recognized as an internal or external command') !== false)) || !is_array($output) && trim($output) == "")) {
                    $this->gs_command = "gs";
                    $this->gs_version = doubleval($output[0]);
                    $this->gs_path = ""; // The ghostscript will find the path itself
                    $this->gs_is_64 = "NOT WIN";
                }
            }
        }
    }


    private function execute($command, $is_shell = false)
    {
        $output = null;
        if ($is_shell) {
            $output = shell_exec($command);
        } else {
            exec($command, $output);
        }
        return $output;
    }

    private function executeGS($command, $is_shell = false)
    {
        return $this->execute($this->gs_command . " " . $command, $is_shell);
    }

    private function checkFilesExists($source_path, $fileNameArray)
    {
        $source_path = trim($source_path) == "" ? $source_path : $source_path . "/";
        foreach ($fileNameArray as $file_name) {
            if (!file_exists($source_path . $file_name)) {
                return false;
            }
        }
        return true;
    }

    private function getGSLibFilePath($filename)
    {
        if (!$this->gs_path) {
            return $filename;
        }
        if ($this->is_os_win) {
            return $this->gs_path . "\\lib\\$filename";
        } else {
            return $this->gs_path . "/lib/$filename";
        }
    }
}
