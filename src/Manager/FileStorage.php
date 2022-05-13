<?php

/**
 * FileStorage.php
 *
 * Manage storage systems for uploaded files.
 *
 * @package jaxon-core
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2022 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload\Manager;

use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;
use Jaxon\Exception\RequestException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

use function is_string;
use function trim;

class FileStorage
{
    /**
     * @var ConfigManager
     */
    protected $xConfigManager;

    /**
     * @var Translator
     */
    protected $xTranslator;

    /**
     * The constructor
     *
     * @param ConfigManager $xConfigManager
     * @param Translator $xTranslator
     */
    public function __construct(ConfigManager $xConfigManager, Translator $xTranslator)
    {
        $this->xConfigManager = $xConfigManager;
        $this->xTranslator = $xTranslator;
    }

    /**
     * @param string $sFieldId
     *
     * @return Filesystem
     * @throws RequestException
     */
    public function filesystem(string $sFieldId = ''): Filesystem
    {
        // Default upload dir
        $sRootDir = $this->xConfigManager->getOption('upload.default.dir');
        if(($sFieldId = trim($sFieldId)) !== '')
        {
            $sRootDir = $this->xConfigManager->getOption('upload.files.' . $sFieldId . '.dir', $sRootDir);
        }
        if(!is_string($sRootDir))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.access'));
        }

        // The internal adapter
        $adapter = new LocalFilesystemAdapter($sRootDir);
        return new Filesystem($adapter);
    }
}
