<?php

namespace Jaxon\Upload\Tests\TestUpload;

use Jaxon\Jaxon;
use Jaxon\Exception\RequestException;
use Jaxon\Exception\SetupException;
use Jaxon\Upload\Manager\FileNameInterface;
use Jaxon\Upload\UploadHandler;
use Jaxon\Upload\UploadResponse;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;

use function jaxon;
use function filesize;
use function realpath;

class UploadFsLocalTest extends TestCase
{
    /**
     * @var string
     */
    protected $sNameWhite;

    /**
     * @var string
     */
    protected $sPathWhite;

    /**
     * @var int
     */
    protected $sSizeWhite;

    /**
     * @var string
     */
    protected $sNameBlue;

    /**
     * @var string
     */
    protected $sPathBlue;

    /**
     * @var int
     */
    protected $sSizeBlue;

    /**
     * @throws SetupException
     */
    public function setUp(): void
    {
        jaxon()->di()->getPluginManager()->registerPlugins();
        UploadHandler::register(jaxon()->di());

        jaxon()->setOption('core.response.send', false);
        $tmpDir = realpath(__DIR__ . '/../upload/tmp');
        @mkdir($tmpDir);

        $sSrcWhite = __DIR__ . '/../upload/src/white.png';
        $this->sNameWhite = 'white.png';
        $this->sPathWhite = "$tmpDir/{$this->sNameWhite}";
        $this->sSizeWhite = filesize($sSrcWhite);
        // Copy the file to the temp dir.
        @copy($sSrcWhite, $this->sPathWhite);

        $sSrcBlue = __DIR__ . '/../upload/src/blue.png';
        $this->sNameBlue = 'blue.png';
        $this->sPathBlue = "$tmpDir/{$this->sNameBlue}";
        $this->sSizeBlue = filesize($sSrcBlue);
        // Copy the file to the temp dir.
        @copy($sSrcBlue, $this->sPathBlue);

        jaxon()->register(Jaxon::CALLABLE_CLASS, 'Sample', __DIR__ . '/../src/sample.php');
    }

    /**
     * @throws SetupException
     */
    public function tearDown(): void
    {
        jaxon()->reset();
        parent::tearDown();
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadDirAccessError()
    {
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        jaxon()->processRequest();
        $xResponse = jaxon()->getResponse();
        $this->assertEquals(UploadResponse::class, get_class($xResponse));
        $this->assertEquals('', $xResponse->getUploadedFile());
        $this->assertNotEquals('', $xResponse->getErrorMessage());
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadErrorNoDir()
    {
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        jaxon()->processRequest();
        $xResponse = jaxon()->getResponse();
        $this->assertEquals(UploadResponse::class, get_class($xResponse));
        $this->assertEquals('', $xResponse->getUploadedFile());
        $this->assertNotEquals('', $xResponse->getErrorMessage());
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadErrorDirNotFound()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst/not-found');
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        jaxon()->processRequest();
        $xResponse = jaxon()->getResponse();
        $this->assertEquals(UploadResponse::class, get_class($xResponse));
        $this->assertEquals('', $xResponse->getUploadedFile());
        $this->assertNotEquals('', $xResponse->getErrorMessage());
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadErrorNoTmpDir()
    {
        jaxon()->setOption('upload.files.image.dir', __DIR__ . '/../upload/dst');
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        jaxon()->processRequest();
        $xResponse = jaxon()->getResponse();
        $this->assertEquals(UploadResponse::class, get_class($xResponse));
        $this->assertEquals('', $xResponse->getUploadedFile());
        $this->assertNotEquals('', $xResponse->getErrorMessage());
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadErrorDirCreation()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Upload file and dir name generator
        jaxon()->di()->set(FileNameInterface::class, function() {
            return new class implements FileNameInterface
            {
                public function random(int $nLength): string
                {
                    // A file or dir with this name cannot be created.
                    return "test/tos";
                }
            };
        });
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        jaxon()->processRequest();
        $xResponse = jaxon()->getResponse();
        $this->assertEquals(UploadResponse::class, get_class($xResponse));
        $this->assertEquals('', $xResponse->getUploadedFile());
        $this->assertNotEquals('', $xResponse->getErrorMessage());
    }

    /**
     * @throws RequestException
     */
    public function testHttpUploadDisabled()
    {
        jaxon()->setOption('core.upload.enabled', false);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])->withMethod('POST');
        });

        $this->assertFalse(jaxon()->di()->getRequestHandler()->canProcessRequest());
    }

    /**
     * @throws RequestException
     */
    public function testRequestWithNoPluginNoUpload()
    {
        jaxon()->setOption('core.upload.enabled', false);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withParsedBody([
                'jxnwho' => 'Nobody',
                'jxnargs' => [],
            ])->withMethod('POST');
        });

        $this->assertFalse(jaxon()->di()->getRequestHandler()->canProcessRequest());
    }

    /**
     * @throws RequestException
     */
    public function testAjaxRequestAfterHttpUploadIncorrectFile()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Ajax request following an HTTP upload
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withParsedBody([
                'jxncls' => 'Sample',
                'jxnmthd' => 'myMethod',
                'jxnargs' => [],
                'jxnupl' => 'not valid',
            ]);
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        $this->expectException(RequestException::class);
        jaxon()->processRequest();
    }

    /**
     * @throws RequestException
     */
    public function testAjaxRequestAfterHttpUploadUnknownFile()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Ajax request following an HTTP upload
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)->fromGlobals()->withParsedBody([
                'jxncls' => 'Sample',
                'jxnmthd' => 'myMethod',
                'jxnargs' => [],
                'jxnupl' => 'unknown',
            ]);
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        $this->expectException(RequestException::class);
        jaxon()->processRequest();
    }
}
