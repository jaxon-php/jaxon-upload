<?php

/**
 * Validator.php
 *
 * Validate requests data before the are passed into the library.
 *
 * @package jaxon-upload
 * @author Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @copyright 2022 Thierry Feuzeu <thierry.feuzeu@gmail.com>
 * @license https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link https://github.com/jaxon-php/jaxon-core
 */

namespace Jaxon\Upload\Manager;

/*
 * See the following links to get explanations about the regexp.
 * http://php.net/manual/en/language.oop5.basic.php
 * http://stackoverflow.com/questions/3195614/validate-class-method-names-with-regex
 * http://www.w3schools.com/charsets/ref_html_utf8.asp
 * http://www.w3schools.com/charsets/ref_utf_latin1_supplement.asp
 */

use Jaxon\App\Config\ConfigManager;
use Jaxon\App\I18n\Translator;

use function in_array;
use function is_array;

class Validator
{
    /**
     * The config manager
     *
     * @var ConfigManager
     */
    protected $xConfigManager;

    /**
     * The translator
     *
     * @var Translator
     */
    protected $xTranslator;

    /**
     * The last error message
     *
     * @var string
     */
    protected $sErrorMessage;

    public function __construct(ConfigManager $xConfigManager, Translator $xTranslator)
    {
        // Set the config manager
        $this->xConfigManager = $xConfigManager;
        // Set the translator
        $this->xTranslator = $xTranslator;
    }

    /**
     * Get the last error message
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->sErrorMessage;
    }

    /**
     * Validate a property of an uploaded file
     *
     * @param string $sVarName    The uploaded file variable name
     * @param string $sValue    The value of the property
     * @param string $sProperty    The property name in config options
     * @param string $sField    The field name in file data
     *
     * @return bool
     */
    private function validateFileProperty(string $sVarName, string $sValue, string $sProperty, string $sField): bool
    {
        $xDefault = $this->xConfigManager->getOption('upload.default.' . $sProperty);
        $aAllowed = $this->xConfigManager->getOption('upload.files.' . $sVarName . '.' . $sProperty, $xDefault);
        if(is_array($aAllowed) && !in_array($sValue, $aAllowed))
        {
            $this->sErrorMessage = $this->xTranslator->trans('errors.upload.' . $sField, [$sField => $sValue]);
            return false;
        }
        return true;
    }

    /**
     * Validate the size of an uploaded file
     *
     * @param string $sVarName    The uploaded file variable name
     * @param int $nFileSize    The uploaded file size
     * @param string $sProperty    The property name in config options
     *
     * @return bool
     */
    private function validateFileSize(string $sVarName, int $nFileSize, string $sProperty): bool
    {
        $xDefault = $this->xConfigManager->getOption('upload.default.' . $sProperty, 0);
        $nSize = $this->xConfigManager->getOption('upload.files.' . $sVarName . '.' . $sProperty, $xDefault);
        if($nSize > 0 && (
            ($sProperty == 'max-size' && $nFileSize > $nSize) ||
            ($sProperty == 'min-size' && $nFileSize < $nSize)))
        {
            $this->sErrorMessage = $this->xTranslator->trans('errors.upload.' . $sProperty, ['size' => $nFileSize]);
            return false;
        }
        return true;
    }

    /**
     * Validate an uploaded file
     *
     * @param string $sVarName    The uploaded file variable name
     * @param File $xFile    The uploaded file
     *
     * @return bool
     */
    public function validateUploadedFile(string $sVarName, File $xFile): bool
    {
        $this->sErrorMessage = '';
        // Verify the file extension
        if(!$this->validateFileProperty($sVarName, $xFile->type(), 'types', 'type'))
        {
            return false;
        }

        // Verify the file extension
        if(!$this->validateFileProperty($sVarName, $xFile->extension(), 'extensions', 'extension'))
        {
            return false;
        }

        // Verify the max size
        if(!$this->validateFileSize($sVarName, $xFile->size(), 'max-size'))
        {
            return false;
        }

        // Verify the min size
        if(!$this->validateFileSize($sVarName, $xFile->size(), 'min-size'))
        {
            return false;
        }

        return true;
    }
}
