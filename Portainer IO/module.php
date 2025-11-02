<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PortainerIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 *
 * @property string $Host
 */
class PortainerIO extends IPSModuleStrict
{
    use \PortainerIO\BufferHelper;
    use \PortainerIO\DebugHelper;

    public const IS_NotReachable = IS_EBASE + 1;
    public const IS_Unauthorized = IS_EBASE + 2;
    public const IS_ConnectionLost = IS_EBASE + 5;
    private static $CURL_error_codes = [
        0  => 'UNKNOWN ERROR',
        1  => 'CURLE_UNSUPPORTED_PROTOCOL',
        2  => 'CURLE_FAILED_INIT',
        3  => 'CURLE_URL_MALFORMAT',
        4  => 'CURLE_URL_MALFORMAT_USER',
        5  => 'CURLE_COULDNT_RESOLVE_PROXY',
        6  => 'CURLE_COULDNT_RESOLVE_HOST',
        7  => 'CURLE_COULDNT_CONNECT',
        8  => 'CURLE_FTP_WEIRD_SERVER_REPLY',
        9  => 'CURLE_REMOTE_ACCESS_DENIED',
        11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
        13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
        14 => 'CURLE_FTP_WEIRD_227_FORMAT',
        15 => 'CURLE_FTP_CANT_GET_HOST',
        17 => 'CURLE_FTP_COULDNT_SET_TYPE',
        18 => 'CURLE_PARTIAL_FILE',
        19 => 'CURLE_FTP_COULDNT_RETR_FILE',
        21 => 'CURLE_QUOTE_ERROR',
        22 => 'CURLE_HTTP_RETURNED_ERROR',
        23 => 'CURLE_WRITE_ERROR',
        25 => 'CURLE_UPLOAD_FAILED',
        26 => 'CURLE_READ_ERROR',
        27 => 'CURLE_OUT_OF_MEMORY',
        28 => 'CURLE_OPERATION_TIMEDOUT',
        30 => 'CURLE_FTP_PORT_FAILED',
        31 => 'CURLE_FTP_COULDNT_USE_REST',
        33 => 'CURLE_RANGE_ERROR',
        34 => 'CURLE_HTTP_POST_ERROR',
        35 => 'CURLE_SSL_CONNECT_ERROR',
        36 => 'CURLE_BAD_DOWNLOAD_RESUME',
        37 => 'CURLE_FILE_COULDNT_READ_FILE',
        38 => 'CURLE_LDAP_CANNOT_BIND',
        39 => 'CURLE_LDAP_SEARCH_FAILED',
        41 => 'CURLE_FUNCTION_NOT_FOUND',
        42 => 'CURLE_ABORTED_BY_CALLBACK',
        43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
        45 => 'CURLE_INTERFACE_FAILED',
        47 => 'CURLE_TOO_MANY_REDIRECTS',
        48 => 'CURLE_UNKNOWN_TELNET_OPTION',
        49 => 'CURLE_TELNET_OPTION_SYNTAX',
        51 => 'CURLE_PEER_FAILED_VERIFICATION',
        52 => 'CURLE_GOT_NOTHING',
        53 => 'CURLE_SSL_ENGINE_NOTFOUND',
        54 => 'CURLE_SSL_ENGINE_SETFAILED',
        55 => 'CURLE_SEND_ERROR',
        56 => 'CURLE_RECV_ERROR',
        58 => 'CURLE_SSL_CERTPROBLEM',
        59 => 'CURLE_SSL_CIPHER',
        60 => 'CURLE_SSL_CACERT',
        61 => 'CURLE_BAD_CONTENT_ENCODING',
        62 => 'CURLE_LDAP_INVALID_URL',
        63 => 'CURLE_FILESIZE_EXCEEDED',
        64 => 'CURLE_USE_SSL_FAILED',
        65 => 'CURLE_SEND_FAIL_REWIND',
        66 => 'CURLE_SSL_ENGINE_INITFAILED',
        67 => 'CURLE_LOGIN_DENIED',
        68 => 'CURLE_TFTP_NOTFOUND',
        69 => 'CURLE_TFTP_PERM',
        70 => 'CURLE_REMOTE_DISK_FULL',
        71 => 'CURLE_TFTP_ILLEGAL',
        72 => 'CURLE_TFTP_UNKNOWNID',
        73 => 'CURLE_REMOTE_FILE_EXISTS',
        74 => 'CURLE_TFTP_NOSUCHUSER',
        75 => 'CURLE_CONV_FAILED',
        76 => 'CURLE_CONV_REQD',
        77 => 'CURLE_SSL_CACERT_BADFILE',
        78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
        79 => 'CURLE_SSH',
        80 => 'CURLE_SSL_SHUTDOWN_FAILED',
        81 => 'CURLE_AGAIN',
        82 => 'CURLE_SSL_CRL_BADFILE',
        83 => 'CURLE_SSL_ISSUER_ERROR',
        84 => 'CURLE_FTP_PRET_FAILED',
        84 => 'CURLE_FTP_PRET_FAILED',
        85 => 'CURLE_RTSP_CSEQ_ERROR',
        86 => 'CURLE_RTSP_SESSION_ERROR',
        87 => 'CURLE_FTP_BAD_FILE_LIST',
        88 => 'CURLE_CHUNK_FAILED'
    ];
    private static $http_error =
        [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            413 => 'Request-size limit exceeded',
            417 => 'Request-size limit exceeded',
            429 => 'API Rate limit exceeded',
            500 => 'Server error'
        ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyBoolean(\Portainer\IO\Property::Open, false);
        $this->RegisterPropertyString(\Portainer\IO\Property::Host, '');
        $this->RegisterPropertyString(\Portainer\IO\Property::Username, '');
        $this->RegisterPropertyString(\Portainer\IO\Property::Password, '');
        $this->RegisterAttributeString(\Portainer\IO\Attribute::Token, '');
        $this->RegisterTimer(\Portainer\IO\Timer::UpdateToken, 0, 'PORTAINER_UpdateToken($_IPS[\'TARGET\']);');
        $this->Host = '';
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->Host = '';
        parent::ApplyChanges();
        if ($this->ReadPropertyBoolean(\Portainer\IO\Property::Open)) {
            $this->Host = $this->ReadPropertyString(\Portainer\IO\Property::Host);
            if ($this->Login()) {
                $this->SetStatus(IS_ACTIVE);
            } else {
                if ($this->GetStatus() == IS_ACTIVE) {
                    $this->SetStatus(self::IS_Unauthorized);
                }
            }
        }

    }
    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'UpdateToken':
                $this->Login();
                break;
        }
    }

    public function ForwardData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        $Result = $this->SendRequest($Data['URI'], $Data['Method'], $Data['Data'], ($Data['Timeout'] ?? 5000));
        return serialize($Result);
    }

    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        echo $errstr . PHP_EOL;
        return true;
    }
    private function Login(): bool
    {
        $Data = [
            'username' => $this->ReadPropertyString(\Portainer\IO\Property::Username),
            'password' => $this->ReadPropertyString(\Portainer\IO\Property::Password)
        ];
        $Result = $this->SendRequest(\Portainer\Api\Url::Auth, \Portainer\Api\HTTP::POST, $Data);
        $this->SendDebug('Login Result', $Result, 0);
        if ($Result) {
            $this->UpdateToken($Result);
        }
        return (bool) $Result;
    }
    private function UpdateToken(array $TokenData): void
    {
        $this->WriteAttributeString(\Portainer\IO\Attribute::Token, $TokenData[\Portainer\IO\Attribute::Token]);
        $this->SetTimerInterval(\Portainer\IO\Timer::UpdateToken, 7 * 60 * 60 * 1000);
    }
    private function SendRequest(string $RequestURL, string $RequestMethod = \Omada\HTTP::GET, array $PostData = [], int $Timeout = 5000, array $RequestHeader = []): bool|array
    {
        /** @var array $_IPS */
        if (!$this->Host) {
            return false;
        }
        $CurlURL = $this->Host . '/api' . $RequestURL;
        $this->SendDebug('RequestMethod:' . $_IPS['THREAD'], $RequestMethod, 0);
        $this->SendDebug('RequestURL:' . $_IPS['THREAD'], $CurlURL, 0);
        $this->SendDebug('RequestHeader:' . $_IPS['THREAD'], $RequestHeader, 0);
        $Headers = array_merge([
            'Method: ' . $RequestMethod,
            'Connection: keep-alive',
            'User-Agent: Symcon Portainer-Lib by Nall-chan',
            'Content-Type: application/json',
        ], $RequestHeader);
        if (strpos($RequestURL, \Portainer\Api\Url::Auth) !== 0) {
            $Headers[] = 'Authorization: Bearer ' . $this->ReadAttributeString(\Portainer\IO\Attribute::Token);
        }
        $ch = curl_init($CurlURL);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if (count($PostData)) {
            $Payload = json_encode($PostData);
            $this->SendDebug('RequestData:' . $_IPS['THREAD'], $Payload, 0);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $Payload);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $Headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $RequestMethod);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $Timeout);
        $response = curl_exec($ch);

        $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($HttpCode != 0) {
            $this->SendDebug('Header Send:' . $_IPS['THREAD'], curl_getinfo($ch)['request_header'], 0);
        }
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        $Header = '';
        $Result = '';
        if (!is_bool($response)) {
            do {
                $header_end = strpos($response, "\r\n\r\n");
                $Header = substr($response, 0, $header_end);
                $Result = substr($response, $header_end + 4);
                $response = $Result;
            } while (strpos($Result, 'HTTP/') === 0);
            if (strlen($Result) === 0) {
                $Result = true;
            }
        }
        $this->SendDebug('Header Receive:' . $_IPS['THREAD'], $Header, 0);
        $this->SendDebug('Body Receive:' . $_IPS['THREAD'], $Result, 0);
        set_error_handler([$this, 'ModulErrorHandler']);
        switch ($HttpCode) {
            case 0:
                if ($this->GetStatus() == IS_ACTIVE) {
                    $this->SetStatus(self::IS_ConnectionLost);
                } else {
                    $this->SetStatus(self::IS_NotReachable);
                }
                $this->SendDebug('CURL ERROR', self::$CURL_error_codes[$curl_errno], 0);
                trigger_error(self::$CURL_error_codes[$curl_errno], E_USER_WARNING);
                $Result = false;
                break;
            case 202:
            case 204:
                if (($RequestMethod == \Portainer\Api\HTTP::PUT) || ($RequestMethod == \Portainer\Api\HTTP::DELETE)) {
                    $Result = true;
                }
                break;
            case 409:
                $Data = json_decode($Result, true);
                if (isset($Data['message'])) {
                    trigger_error($Data['message'], E_USER_WARNING);
                } else {
                    trigger_error('unknown error', E_USER_WARNING);
                }
                $Result = false;
                break;
            case 400:
            case 404:
                $Result = false;
                break;
            case 403:
                $this->SetStatus(self::IS_Unauthorized);
                // No break. Add additional comment above this line if intentional
            case 401:
                if (strpos($RequestURL, \Portainer\Api\Url::Auth) !== 0) {
                    if ($this->Login()) {
                        return $this->SendRequest($RequestURL, $RequestMethod, $PostData, $Timeout, $RequestHeader);
                    }
                }
                // No break. Add additional comment above this line if intentional
            case 405:
            case 413:
            case 417:
            case 429:
            case 500:
                $this->SendDebug(self::$http_error[$HttpCode], $HttpCode, 0);
                trigger_error(self::$http_error[$HttpCode], E_USER_WARNING);
                $Result = false;
                break;
        }
        if (is_bool($Result)) {
            restore_error_handler();
            return $Result;
        }
        $Data = json_decode($Result, true);
        /*
        if ($Data[\Omada\Api\ErrorCode] != 0) {
            if ($Data[\Omada\Api\ErrorCode] == -44112) {
                if ($this->RefreshToken()) {
                    return $this->SendRequest($RequestURL, $RequestMethod, $PostData, $Timeout, $RequestHeader);
                }
            }
            $this->SendDebug('Error:' . $Data[\Omada\Api\ErrorCode], $Data[\Omada\Api\Message], 0);
            trigger_error($this->Translate(\Omada\Api\Protocol::$ErrorCodes[$Data[\Omada\Api\ErrorCode]]), E_USER_WARNING);
            if (!isset($Data['result'])) {
                $Data['result'] = false;
            }
            $this->SetStatus(self::IS_ConnectionLost);
        }
         */
        restore_error_handler();
        //return $Data['result'];
        if ($Data) {
            return $Data;
        }
        return false;

    }
}