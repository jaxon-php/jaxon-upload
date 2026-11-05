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
use Psr\Http\Message\UploadedFileInterface;
use Closure;

use function pathinfo;

class File implements FileInterface
{
    /**
     * The uploaded file name, without the extension and sanitized
     *
     * @var string
     */
    private string $sName;

    /**
     * The uploaded file path
     *
     * @var string
     */
    private string $sPath;

    /**
     * The filesystem where the file is stored
     *
     * @var Filesystem
     */
    private Filesystem $xFilesystem;

    /**
     * True if the file is moved to the upload storage
     *
     * @var bool
     */
    private bool $bMoved;

    /**
     * The uploaded temp file
     *
     * @var UploadedFileInterface
     */
    private UploadedFileInterface $xTempFile;

    /**
     * A resource to read from the uploaded temp file
     *
     * @var resource|null
     */
    private $xResource;

    /**
     * Create an instance of this class using data from an uploaded file.
     *
     * @param Filesystem $xFilesystem
     * @param string $sUploadDir
     * @param string $sName
     * @param UploadedFileInterface $xTempFile
     * @param Closure $fValidate
     * @param Closure $fMove
     *
     * @return File
     */
    public static function fromHttpFile(Filesystem $xFilesystem,
        string $sUploadDir, string $sName, UploadedFileInterface $xTempFile,
        Closure $fValidate, Closure $fMove): File
    {
        $xFile = new File();
        $xFile->xFilesystem = $xFilesystem;
        $xFile->xTempFile = $xTempFile;
        $xFile->sName = $sName;
        $xFile->sPath = $sUploadDir . $sName . '.' . $xFile->extension();
        $fValidate($xFile);
        $xFile->bMoved = $fMove($xFile);
        $xFile->xResource = $xFile->bMoved ? null : $xTempFile->getStream()->detach();
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
        return $this->xTempFile->getClientMediaType();
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
        return $this->xTempFile->getClientFilename();
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
        return $this->xTempFile->getSize();
    }

    /**
     * Get the uploaded file extension
     *
     * @return string
     */
    public function extension(): string
    {
        return pathinfo($this->filename(), PATHINFO_EXTENSION);
    }

    /**
     * True if the file is moved to the upload storage
     *
     * @return bool
     */
    public function moved(): bool
    {
        return $this->bMoved;
    }

    /**
     * The uploaded temp file.
     *
     * @return UploadedFileInterface
     */
    public function temp(): UploadedFileInterface
    {
        return $this->xTempFile;
    }

    /**
     * A resource to read from the uploaded temp file.
     *
     * @return resource|null
     */
    public function res(): mixed
    {
        return $this->xResource;
    }
}
