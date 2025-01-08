<?php

namespace Jaxon\Upload\Tests\TestUpload;

use Jaxon\Jaxon;
use Jaxon\Exception\RequestException;
use Jaxon\Exception\SetupException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;

use function Jaxon\jaxon;
use function Jaxon\Upload\_register;
use function copy;
use function filesize;
use function mkdir;

class UploadHandlerTest extends TestCase
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
        _register();
        jaxon()->setOption('core.upload.enabled', true);
        jaxon()->setOption('core.response.send', false);

        $tmpDir = __DIR__ . '/../upload/tmp';
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
    public function testAjaxUpload()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Uploaded files
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
    }

    /**
     * @throws RequestException
     */
    public function testAjaxUploadMultipleFiles()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => [
                        new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                            UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                        new UploadedFile($this->sPathBlue, $this->sSizeBlue,
                            UPLOAD_ERR_OK, $this->sNameBlue, 'png'),
                    ],
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Uploaded files
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(2, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
        $xFile = $aFiles['image'][1];
        $this->assertEquals('blue', $xFile->name());
        $this->assertEquals($this->sNameBlue, $xFile->filename());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
    }

    /**
     * @throws RequestException
     */
    public function testAjaxUploadMultipleNames()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'white' => [
                        new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                            UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                    ],
                    'blue' => [
                        new UploadedFile($this->sPathBlue, $this->sSizeBlue,
                            UPLOAD_ERR_OK, $this->sNameBlue, 'png'),
                    ],
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Uploaded files
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(2, $aFiles);
        $this->assertCount(1, $aFiles['white']);
        $this->assertCount(1, $aFiles['blue']);
        $xFile = $aFiles['white'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
        $xFile = $aFiles['blue'][0];
        $this->assertEquals('blue', $xFile->name());
        $this->assertEquals($this->sNameBlue, $xFile->filename());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
    }

    /**
     * @throws RequestException
     */
    public function testAjaxUploadNameSanitizer()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->upload()->sanitizer(function($sFilename, $sVarName) {
            return $sVarName === 'image' ? 'img_' . $sFilename : $sFilename;
        });
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Uploaded files
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('img_white', $xFile->name());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileTypeValidationOk()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.types', ['png']);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Return the file name for the next test
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileTypeValidationError()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.types', ['jpg']);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        $this->expectException(RequestException::class);
        jaxon()->processRequest();
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileExtensionValidationOk()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.extensions', ['png']);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Return the file name for the next test
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileExtensionValidationError()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.extensions', ['jpg']);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        $this->expectException(RequestException::class);
        jaxon()->processRequest();
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileMaxSizeValidationOk()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.max-size', 30000);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Return the file name for the next test
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileMaxSizeValidationError()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.max-size', 25000);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        $this->expectException(RequestException::class);
        jaxon()->processRequest();
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileMinSizeValidationOk()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.min-size', 25000);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        jaxon()->processRequest();

        // Return the file name for the next test
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
    }

    /**
     * @throws RequestException
     */
    public function testUploadFileMinSizeValidationError()
    {
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        jaxon()->setOption('upload.default.min-size', 30000);
        // Send a request to the registered class
        jaxon()->di()->set(ServerRequestInterface::class, function($c) {
            return $c->g(ServerRequestCreator::class)
                ->fromGlobals()
                ->withParsedBody([
                    'jxncall' => json_encode([
                        'type' => 'class',
                        'name' => 'Sample',
                        'method' => 'myMethod',
                        'args' => [],
                    ]),
                ])
                ->withUploadedFiles([
                    'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                        UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
                ])
                ->withMethod('POST');
        });

        $this->assertTrue(jaxon()->di()->getRequestHandler()->canProcessRequest());
        $this->assertTrue(jaxon()->di()->getUploadHandler()->canProcessRequest(jaxon()->di()->getRequest()));
        $this->expectException(RequestException::class);
        jaxon()->processRequest();
    }

    /**
     * @throws RequestException
     * @throws SetupException
     */
    public function testPsrAjaxUpload()
    {
        _register();
        jaxon()->setOption('core.upload.enabled', true);
        jaxon()->setOption('upload.default.dir', __DIR__ . '/../upload/dst');
        // Send a request to the registered class
        $xRequest = jaxon()->di()->g(ServerRequestCreator::class)
            ->fromGlobals()
            ->withParsedBody([
                'jxncall' => json_encode([
                    'type' => 'class',
                    'name' => 'Sample',
                    'method' => 'myMethod',
                    'args' => [],
                ]),
            ])
            ->withUploadedFiles([
                'image' => new UploadedFile($this->sPathWhite, $this->sSizeWhite,
                    UPLOAD_ERR_OK, $this->sNameWhite, 'png'),
            ])
            ->withMethod('POST');

        $xPsrConfigMiddleware = jaxon()->psr()->config(__DIR__ . '/../config/psr.php');
        $xPsrAjaxMiddleware = jaxon()->psr()->ajax();
        // PSR request handler that does nothing, useful to call the config middleware.
        $xEmptyRequestHandler = new class implements RequestHandlerInterface
        {
            private $xPsr17Factory;

            public function __construct()
            {
                $this->xPsr17Factory = new Psr17Factory();
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->xPsr17Factory->createResponse();
            }
        };
        // Call the config middleware
        $xPsrConfigMiddleware->process($xRequest, $xEmptyRequestHandler);
        // Call the ajax middleware
        $xPsrResponse = $xPsrAjaxMiddleware->process($xRequest, $xEmptyRequestHandler);

        // Both responses must have the same content and content type
        $xJaxonResponse = jaxon()->getResponse();
        $this->assertEquals($xPsrResponse->getBody()->__toString(), $xJaxonResponse->getOutput());
        $this->assertEquals($xPsrResponse->getHeader('content-type')[0], $xJaxonResponse->getContentType());

        // Uploaded files
        $aFiles = jaxon()->upload()->files();
        $this->assertCount(1, $aFiles);
        $this->assertCount(1, $aFiles['image']);
        $xFile = $aFiles['image'][0];
        $this->assertEquals('white', $xFile->name());
        $this->assertEquals($this->sNameWhite, $xFile->filename());
        $this->assertEquals('png', $xFile->type());
        $this->assertEquals('png', $xFile->extension());
    }
}
