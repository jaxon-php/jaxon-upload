<?php

/**
 * File.php
 *
 * An uploaded file.
 *
 * @package jaxon-core
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
     * @param string $sUploadDir
     * @param UploadedFile $xHttpFile
     *
     * @return File
     */
    public static function fromHttpFile(Filesystem $xFilesystem, string $sUploadDir, UploadedFile $xHttpFile): File
    {
        $xFile = new File();
        $xFile->xFilesystem = $xFilesystem;
        $xFile->sType = $xHttpFile->getClientMediaType();
        $xFile->sFilename = $xHttpFile->getClientFilename();
        $xFile->sExtension = pathinfo($xFile->sFilename, PATHINFO_EXTENSION);
        $xFile->nSize = $xHttpFile->getSize();
        $xFile->sPath = $sUploadDir . $xFile->sName . '.' . $xFile->sExtension;
        return $xFile;
    }

    /**
     * Create an instance of this class using data from a temp file
     *
     * @param Filesystem $xFilesystem
     * @param array $aFile    The uploaded file data
     *
     * @return File
     */
    public static function fromTempFile(Filesystem $xFilesystem, array $aFile): File
    {
        $xFile = new File();
        $xFile->xFilesystem = $xFilesystem;
        $xFile->sType = $aFile['type'];
        $xFile->sName = $aFile['name'];
        $xFile->sFilename = $aFile['filename'];
        $xFile->sExtension = $aFile['extension'];
        $xFile->nSize = $aFile['size'];
        $xFile->sPath = $aFile['path'];
        return $xFile;
    }

    /**
     * Convert the File instance to array.
     *
     * @return array<string,string>
     */
    public function toTempData(): array
    {
        return [
            'type' => $this->sType,
            'name' => $this->sName,
            'filename' => $this->sFilename,
            'extension' => $this->sExtension,
            'size' => $this->nSize,
            'path' => $this->sPath,
        ];
    }

    /**
     * @param string $sName
     */
    public function setName(string $sName): void
    {
        $this->sName = $sName;
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
