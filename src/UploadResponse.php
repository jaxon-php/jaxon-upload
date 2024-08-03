<?php

namespace Jaxon\Upload;

use Jaxon\Plugin\Manager\PluginManager;
use Jaxon\Plugin\Response\Psr\PsrPlugin;
use Jaxon\Response\AbstractResponse;
use Jaxon\Response\ResponseManager;
use Psr\Http\Message\ResponseInterface;

use function addslashes;
use function array_reduce;
use function json_encode;

class UploadResponse extends AbstractResponse
{
    /**
     * The path to the uploaded file
     *
     * @var string
     */
    private $sUploadedFile = '';

    /**
     * The constructor
     *
     * @param ResponseManager $xManager
     * @param PluginManager $xPluginManager
     * @param string $sUploadedFile
     */
    public function __construct(ResponseManager $xManager, PluginManager $xPluginManager,
        string $sUploadedFile = '')
    {
        parent::__construct($xManager, $xPluginManager);
        $this->sUploadedFile = $sUploadedFile;
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return 'text/html';
    }

    /**
     * Get the path to the uploaded file
     *
     * @return string
     */
    public function getUploadedFile(): string
    {
        return $this->sUploadedFile;
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): string
    {
        $aResult = ($this->sUploadedFile) ?
            ['code' => 'success', 'upl' => $this->sUploadedFile] :
            ['code' => 'error', 'msg' => $this->getErrorMessage()];
        $aMessages = $this->xManager->getDebugMessages();
        $sMessages = array_reduce($aMessages, function($sJsLog, $sMessage) {
            return "$sJsLog\n\t" . 'console.log("' . addslashes($sMessage) . '");';
        }, '');

        return '
<!DOCTYPE html>
<html>
<body>
<h1>HTTP Upload for Jaxon</h1>
</body>
<script>
    res = ' . json_encode($aResult) . ';
    // Debug messages ' . $sMessages . '
</script>
</html>';
    }

    /**
     * Convert this response to a PSR7 response object
     *
     * @return ResponseInterface
     */
    public function toPsr(): ResponseInterface
    {
        /** @var PsrPlugin */
        $xPlugin = $this->plugin('psr');
        return $xPlugin->upload(($this->sUploadedFile) ? 200 : 500);
    }
}
