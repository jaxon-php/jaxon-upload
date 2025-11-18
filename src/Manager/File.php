<?php

/**
 * File.php
 *
 * An uploaded file.
 *
 * @package jaxon-upload
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2017 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload\Manager;

use Jaxon\Request\Upload\FileInterface;
use League\Flysystem\Filesystem;
use Nyholm\Psr7\UploadedFile;

use function pathinfo;

class File implements FileInterface
{
    /**
     * The uploaded file type
     *
     * @var string
     */
    protected $sType;

    /**
     * The uploaded file name, without the extension and sanitized
     *
     * @var string
     */
    protected $sName;

    /**
     * The uploaded file name, with the extension
     *
     * @var string
     */
    protected $sFilename;

    /**
     * The uploaded file path
     *
     * @var string
     */
    protected $sPath;

    /**
     * The uploaded file size
     *
     * @var int
     */
    protected $nSize;

    /**
     * The uploaded file extension
     *
     * @var string
     */
    protected $sExtension;

    /**
     * The filesystem where the file is stored
     *
     * @var Filesystem
     */
    protected $xFilesystem;

    /**
     * Create an instance of this class using data from an uploaded file.
     *
     * @param Filesystem $xFilesystem
     * @param UploadedFile $xHttpFile
     * @param string $sUploadDir
     * @param string $sName
     *
     * @return File
     */
    public static function fromHttpFile(Filesystem $xFilesystem,
        UploadedFile $xHttpFile, string $sUploadDir, string $sName): File
    {
        $xFile = new File();
        $xFile->xFilesystem = $xFilesystem;
        $xFile->sType = $xHttpFile->getClientMediaType();
        $xFile->sName = $sName;
        $xFile->sFilename = $xHttpFile->getClientFilename();
        $xFile->sExtension = pathinfo($xFile->sFilename, PATHINFO_EXTENSION);
        $xFile->nSize = $xHttpFile->getSize();
        $xFile->sPath = $sUploadDir . $xFile->sName . '.' . $xFile->sExtension;
        return $xFile;
    }

    /**
     * Get the filesystem where the file is stored
     *
     * @return Filesystem
     */
    public function filesystem(): Filesystem
    {
        return $this->xFilesystem;
    }

    /**
     * Get the uploaded file type
     *
     * @return string
     */
    public function type(): string
    {
        return $this->sType;
    }

    /**
     * Get the uploaded file name, without the extension and slugified
     *
     * @return string
     */
    public function name(): string
    {
        return $this->sName;
    }

    /**
     * Get the uploaded file name, with the extension
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->sFilename;
    }

    /**
     * Get the uploaded file path
     *
     * @return string
     */
    public function path(): string
    {
        return $this->sPath;
    }

    /**
     * Get the uploaded file size
     *
     * @return int
     */
    public function size(): int
    {
        return $this->nSize;
    }

    /**
     * Get the uploaded file extension
     *
     * @return string
     */
    public function extension(): string
    {
        return $this->sExtension;
    }
}
