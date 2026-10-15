<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Portainer\Api\Response;

require_once __DIR__ . '/../libs/PortainerLib.php';

/**
 * Tests der Antwortauswertung der Portainer API (ohne Symcon-Abhängigkeit).
 */
class ApiResponseTest extends TestCase
{
    public function testIsSuccess(): void
    {
        $this->assertTrue(Response::IsSuccess(200));
        $this->assertTrue(Response::IsSuccess(204));
        $this->assertTrue(Response::IsSuccess(304));
        $this->assertFalse(Response::IsSuccess(0));
        $this->assertFalse(Response::IsSuccess(301));
        $this->assertFalse(Response::IsSuccess(400));
        $this->assertFalse(Response::IsSuccess(422));
        $this->assertFalse(Response::IsSuccess(500));
    }

    public function testIsGatewayError(): void
    {
        $this->assertTrue(Response::IsGatewayError(502));
        $this->assertTrue(Response::IsGatewayError(503));
        $this->assertTrue(Response::IsGatewayError(504));
        $this->assertFalse(Response::IsGatewayError(500));
        $this->assertFalse(Response::IsGatewayError(404));
    }

    public function testSplitSingleHeader(): void
    {
        $Header = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n";
        $Response = $Header . '{"a":1}';
        $this->assertSame("HTTP/1.1 200 OK\r\nContent-Type: application/json", Response::GetHeader($Response, strlen($Header)));
        $this->assertSame('{"a":1}', Response::GetBody($Response, strlen($Header)));
    }

    public function testSplitMultipleHeaderBlocks(): void
    {
        // 100 Continue bzw. Weiterleitung: nur der letzte Header-Block ist relevant
        $Header = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 204 No Content\r\nServer: x\r\n\r\n";
        $this->assertSame("HTTP/1.1 204 No Content\r\nServer: x", Response::GetHeader($Header, strlen($Header)));
        $this->assertSame('', Response::GetBody($Header, strlen($Header)));
    }

    public function testBodyContainingHeaderSeparator(): void
    {
        // Der Body darf eine Leerzeile enthalten, ohne die Trennung zu stören
        $Header = "HTTP/1.1 200 OK\r\n\r\n";
        $Body = "HTTP/1.1 text\r\n\r\nmore";
        $this->assertSame($Body, Response::GetBody($Header . $Body, strlen($Header)));
    }

    public function testDecodeBody(): void
    {
        $this->assertTrue(Response::DecodeBody(''));
        $this->assertTrue(Response::DecodeBody("  \r\n"));
        $this->assertSame([], Response::DecodeBody('[]'));
        $this->assertSame(['Id' => 1], Response::DecodeBody('{"Id":1}'));
        $this->assertFalse(Response::DecodeBody('<html>Bad Gateway</html>'));
        $this->assertFalse(Response::DecodeBody('"text"'));
    }

    public function testGetErrorMessage(): void
    {
        // Portainer: falsche Zugangsdaten (HTTP 422)
        $this->assertSame(
            'Invalid credentials: Unauthorized',
            Response::GetErrorMessage('{"message":"Invalid credentials","details":"Unauthorized"}')
        );
        // Docker API über Portainer
        $this->assertSame(
            'No such container: abc',
            Response::GetErrorMessage('{"message":"No such container: abc"}')
        );
        $this->assertSame('same', Response::GetErrorMessage('{"message":"same","details":"same"}'));
        $this->assertSame('only details', Response::GetErrorMessage('{"details":"only details"}'));
        $this->assertSame('', Response::GetErrorMessage('<html>Error</html>'));
        $this->assertSame('', Response::GetErrorMessage(''));
        $this->assertSame('', Response::GetErrorMessage('{"message":42}'));
    }

    public function testGetHttpStatusText(): void
    {
        $this->assertSame('Not Found', Response::GetHttpStatusText(404));
        $this->assertSame('Unprocessable Entity', Response::GetHttpStatusText(422));
        $this->assertSame('Unknown error', Response::GetHttpStatusText(418));
    }

    public function testMaskSecrets(): void
    {
        $this->assertSame(
            '{"username":"admin","password":"***"}',
            Response::MaskSecrets('{"username":"admin","password":"geh\\"eim"}')
        );
        $this->assertSame('{"jwt":"***"}', Response::MaskSecrets('{"jwt":"eyJhbGciOi.abc.def"}'));
        $this->assertSame(
            "GET /api/stacks HTTP/1.1\r\nAuthorization: Bearer ***\r\nX-API-Key: ***\r\n",
            Response::MaskSecrets("GET /api/stacks HTTP/1.1\r\nAuthorization: Bearer eyJ.abc.def\r\nX-API-Key: ptr_secret\r\n")
        );
        $this->assertSame('{"Name":"immich"}', Response::MaskSecrets('{"Name":"immich"}'));
        $this->assertSame(
            "HTTP/1.1 200 OK\r\nSet-Cookie: portainer_api_key=***; Path=/; HttpOnly\r\nContent-Length: 306",
            Response::MaskSecrets("HTTP/1.1 200 OK\r\nSet-Cookie: portainer_api_key=eyJ.abc.def; Path=/; HttpOnly\r\nContent-Length: 306")
        );
    }

    public function testMaskEnvironment(): void
    {
        // Portainer-Stack
        $this->assertSame(
            '{"Id":5,"Env":[{"name":"DB_PASSWORD","value":"***"},{"name":"DB_USERNAME","value":"***"}]}',
            Response::MaskSecrets('{"Id":5,"Env":[{"name":"DB_PASSWORD","value":"secret"},{"name":"DB_USERNAME","value":"postgres"}]}')
        );
        // Docker-Container (inspect)
        $this->assertSame(
            ['Config' => ['Hostname' => 'abc', 'Env' => ['TZ=***', 'API_KEY=***', 'NOVALUE']]],
            Response::MaskSecretsInData(['Config' => ['Hostname' => 'abc', 'Env' => ['TZ=Europe/Berlin', 'API_KEY=a=b', 'NOVALUE']]])
        );
        // Liste von Stacks
        $this->assertSame(
            [['Name' => 'a', 'Env' => [['name' => 'X', 'value' => '***']]], ['Name' => 'b', 'Env' => null]],
            Response::MaskSecretsInData([['Name' => 'a', 'Env' => [['name' => 'X', 'value' => 'y']]], ['Name' => 'b', 'Env' => null]])
        );
    }
}
