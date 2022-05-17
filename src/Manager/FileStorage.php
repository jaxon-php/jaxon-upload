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

use Closure;

use function call_user_func;
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
     * @var array
     */
    protected $aAdapters = [];

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
     * @param string $sStorage
     * @param Closure $cFactory
     *
     * @return void
     */
    public function registerAdapter(string $sStorage, Closure $cFactory)
    {
        $this->aAdapters[$sStorage] = $cFactory;
    }

    /**
     * Register the file storage adapters
     *
     * @return void
     */
    public function registerAdapters()
    {
        // Local file system adapter
        $this->registerAdapter('local', function(string $sRootDir, $xOptions) {
            return empty($xOptions) ? new LocalFilesystemAdapter($sRootDir) :
                new LocalFilesystemAdapter($sRootDir, $xOptions);
        });
    }

    /**
     * @param string $sFieldId
     *
     * @return Filesystem
     * @throws RequestException
     */
    public function filesystem(string $sFieldId = ''): Filesystem
    {
        $sFieldId = trim($sFieldId);
        // Default upload dir
        $sStorage = $this->xConfigManager->getOption('upload.default.storage', 'local');
        $sRootDir = $this->xConfigManager->getOption('upload.default.dir', '');
        $aOptions = $this->xConfigManager->getOption('upload.default.options');
        $sConfigKey = "upload.files.$sFieldId";
        if($sFieldId !== '' && $this->xConfigManager->hasOption($sConfigKey))
        {
            $sStorage = $this->xConfigManager->getOption("$sConfigKey.storage", $sStorage);
            $sRootDir = $this->xConfigManager->getOption("$sConfigKey.dir", $sRootDir);
            $aOptions = $this->xConfigManager->getOption("$sConfigKey.options", $aOptions);
        }
        if(!is_string($sRootDir))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.dir'));
        }
        if(!isset($this->aAdapters[$sStorage]))
        {
            throw new RequestException($this->xTranslator->trans('errors.upload.adapter'));
        }

        return new Filesystem(call_user_func($this->aAdapters[$sStorage], $sRootDir, $aOptions));
    }
}
