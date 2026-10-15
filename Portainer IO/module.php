<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PortainerIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerIO {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * PortainerIO
 * IO-Instanz für die Kommunikation mit der Portainer API.
 * Meldet sich per Benutzername/Passwort an und leitet die Anfragen der Kind-Instanzen weiter.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 *
 * @property string $Host Basis-URL von Portainer ohne abschließenden Slash, leer wenn die Instanz geschlossen ist
 */
class PortainerIO extends IPSModuleStrict
{
    use \PortainerIO\BufferHelper;
    use \PortainerIO\DebugHelper;

    public const IS_NotReachable = IS_EBASE + 1;
    public const IS_Unauthorized = IS_EBASE + 2;
    public const IS_InvalidHost = IS_EBASE + 3;
    public const IS_ConnectionLost = IS_EBASE + 5;

    /** Erneuerung des Tokens, Portainer-Sitzungen laufen standardmäßig nach 8 Stunden ab */
    private const TokenRenewInterval = 7 * 60 * 60 * 1000;

    /** HTTP-Fehler per trigger_error melden (aus, wenn die Kind-Instanz entscheidet) */
    private bool $ReportHttpErrors = true;
    /** HTTP-Statuscode der letzten Anfrage, 0 bei Verbindungs- oder Anmeldefehler */
    private int $LastHttpCode = 0;
    /** Fehlermeldung der letzten Anfrage bei einem HTTP-Fehler */
    private string $LastError = '';
    /** Verbindungsaufbau aus ApplyChanges: ein Fehler gilt als „nicht erreichbar“, nicht als „Verbindung verloren“ */
    private bool $Connecting = false;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyBoolean(\Portainer\IO\Property::Open, false);
        $this->RegisterPropertyString(\Portainer\IO\Property::Host, '');
        $this->RegisterPropertyString(\Portainer\IO\Property::Username, '');
        $this->RegisterPropertyString(\Portainer\IO\Property::Password, '');
        $this->RegisterPropertyBoolean(\Portainer\IO\Property::VerifyCertificate, false);
        $this->RegisterPropertyString(\Portainer\IO\Property::ApiKey, '');
        $this->RegisterAttributeString(\Portainer\IO\Attribute::Token, '');
        $this->RegisterTimer(\Portainer\IO\Timer::UpdateToken, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'' . \Portainer\IO\Action::UpdateToken . '\', 0);');
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
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetTimerInterval(\Portainer\IO\Timer::UpdateToken, 0);
        if (!$this->ReadPropertyBoolean(\Portainer\IO\Property::Open)) {
            $this->WriteAttributeString(\Portainer\IO\Attribute::Token, '');
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        $Host = rtrim(trim($this->ReadPropertyString(\Portainer\IO\Property::Host)), '/');
        if ($Host === '') {
            $this->WriteAttributeString(\Portainer\IO\Attribute::Token, '');
            $this->SetStatus(self::IS_InvalidHost);
            return;
        }
        $this->Host = $Host;
        // Status setzt SendRequest anhand der Antwort
        $this->Connecting = true;
        if ($this->UsesApiKey()) {
            // Mit API-Token gibt es keine Anmeldung und kein Sitzungs-Token
            $this->WriteAttributeString(\Portainer\IO\Attribute::Token, '');
            $this->CheckApiKey();
        } else {
            // Das bisherige Token bleibt bis zur erfolgreichen Anmeldung gültig, damit parallele Anfragen
            // der Kind-Instanzen keine zweite Anmeldung auslösen. Login verwirft es bei einem Fehler.
            $this->Login();
        }
        $this->Connecting = false;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case \Portainer\IO\Action::UpdateToken:
                if (!$this->UsesApiKey()) {
                    $this->Login();
                }
                break;
            case \Portainer\IO\Action::ApiKeyChanged:
                $this->SetCredentialFieldsEnabled((string) $Value === '');
                break;
        }
    }

    /**
     * Formular mit dem passenden Zustand der Anmeldefelder.
     * Ist ein API-Token gespeichert, sind Benutzername und Passwort deaktiviert.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode((string) file_get_contents(__DIR__ . '/form.json'), true);
        $Enabled = !$this->UsesApiKey();
        foreach ($Form['elements'] as &$Element) {
            if (!isset($Element['items'])) {
                continue;
            }
            foreach ($Element['items'] as &$Item) {
                if (in_array($Item['name'] ?? '', [\Portainer\IO\Property::Username, \Portainer\IO\Property::Password], true)) {
                    $Item['enabled'] = $Enabled;
                }
            }
            unset($Item);
        }
        unset($Element);
        return (string) json_encode($Form);
    }

    /**
     * Nimmt die Anfragen der Kind-Instanzen entgegen.
     *
     * HTTP-Fehler der Anfrage (z.B. 404) werden nicht gemeldet, sondern an die Kind-Instanz
     * zurückgegeben, damit diese anhand ihres Status entscheidet, ob sie den Fehler meldet.
     * Fehler der Verbindung oder Anmeldung meldet der IO selbst, sie ändern seinen Status.
     *
     * @param string $JSONString JSON mit den Feldern aus \Portainer\ForwardData
     *
     * @return string Serialisiertes Array mit den Feldern aus \Portainer\IOResponse
     */
    public function ForwardData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        if (!is_array($Data) || !is_string($Data[\Portainer\ForwardData::Uri] ?? null) || !is_string($Data[\Portainer\ForwardData::Method] ?? null)) {
            trigger_error($this->Translate('Invalid request data.'), E_USER_NOTICE);
            return serialize(false);
        }
        $PostData = is_array($Data[\Portainer\ForwardData::Data] ?? null) ? $Data[\Portainer\ForwardData::Data] : [];
        $Timeout = is_int($Data[\Portainer\ForwardData::Timeout] ?? null) ? $Data[\Portainer\ForwardData::Timeout] : 5000;
        $this->ReportHttpErrors = false;
        $Result = $this->SendRequest($Data[\Portainer\ForwardData::Uri], $Data[\Portainer\ForwardData::Method], $PostData, $Timeout);
        return serialize([
            \Portainer\IOResponse::HttpCode => $this->LastHttpCode,
            \Portainer\IOResponse::Result   => $Result,
            \Portainer\IOResponse::Error    => $this->LastError
        ]);
    }

    /**
     * Aktiviert oder deaktiviert die Felder für Benutzername und Passwort im geöffneten Formular.
     *
     * @param bool $Enabled true wenn kein API-Token eingetragen ist
     */
    private function SetCredentialFieldsEnabled(bool $Enabled): void
    {
        $this->UpdateFormField(\Portainer\IO\Property::Username, 'enabled', $Enabled);
        $this->UpdateFormField(\Portainer\IO\Property::Password, 'enabled', $Enabled);
    }

    /**
     * Prüft ob ein API-Token statt Benutzername und Passwort verwendet wird.
     */
    private function UsesApiKey(): bool
    {
        return $this->ReadPropertyString(\Portainer\IO\Property::ApiKey) !== '';
    }

    /**
     * Prüft das API-Token mit einer kleinen Anfrage. Status und Fehler setzt SendRequest.
     *
     * @return bool true wenn Portainer das Token akzeptiert
     */
    private function CheckApiKey(): bool
    {
        $Result = $this->SendRequest(\Portainer\Api\Url::AuthCheck);
        // z.B. HTTP 200 ohne gültiges JSON (Anmeldeseite eines Proxys): Status wäre sonst noch aktiv
        if (!is_array($Result) && ($this->GetStatus() < IS_EBASE)) {
            $this->SetStatus(self::IS_Unauthorized);
        }
        return is_array($Result);
    }

    /**
     * Meldet sich an Portainer an und speichert das Token.
     * Fehler meldet bereits SendRequest.
     *
     * @return bool true bei erfolgreicher Anmeldung
     */
    private function Login(): bool
    {
        $Data = [
            'username' => $this->ReadPropertyString(\Portainer\IO\Property::Username),
            'password' => $this->ReadPropertyString(\Portainer\IO\Property::Password)
        ];
        $Result = $this->SendRequest(\Portainer\Api\Url::Auth, \Portainer\Api\HTTP::POST, $Data);
        if (!is_array($Result)) {
            $this->LoginFailed();
            return false;
        }
        if (!is_string($Result[\Portainer\IO\Attribute::Token] ?? null) || ($Result[\Portainer\IO\Attribute::Token] === '')) {
            $this->SetStatus(self::IS_Unauthorized);
            $this->LoginFailed();
            trigger_error($this->Translate('Login response contains no token.'), E_USER_NOTICE);
            return false;
        }
        $this->SendDebug('Login', 'Token received, length: ' . strlen($Result[\Portainer\IO\Attribute::Token]), 0);
        $this->WriteAttributeString(\Portainer\IO\Attribute::Token, $Result[\Portainer\IO\Attribute::Token]);
        $this->SetTimerInterval(\Portainer\IO\Timer::UpdateToken, self::TokenRenewInterval);
        return true;
    }

    /**
     * Verwirft das Token nach einer fehlgeschlagenen Anmeldung.
     * Ohne Token werden keine Anfragen der Kind-Instanzen an Portainer gesendet,
     * damit falsche Zugangsdaten nicht bei jedem Abruf erneut probiert werden.
     * Den neuen Versuch übernimmt Symcon: IO-Instanzen im Fehlerzustand werden vom Kernel
     * automatisch per ApplyChanges repariert. Dafür muss der Status hier ein Fehler sein.
     */
    private function LoginFailed(): void
    {
        $this->WriteAttributeString(\Portainer\IO\Attribute::Token, '');
        $this->SetTimerInterval(\Portainer\IO\Timer::UpdateToken, 0);
        // z.B. HTTP 200 ohne gültiges JSON (Anmeldeseite eines Proxys): Status wäre sonst noch aktiv
        if ($this->GetStatus() < IS_EBASE) {
            $this->SetStatus(self::IS_Unauthorized);
        }
    }

    /**
     * Setzt den Status auf aktiv, wenn Portainer erreichbar ist und die Anmeldung gilt.
     */
    private function SetStatusActive(): void
    {
        if ($this->GetStatus() != IS_ACTIVE) {
            $this->SetStatus(IS_ACTIVE);
        }
    }

    /**
     * Setzt den Status für eine fehlgeschlagene Verbindung.
     * War die Instanz im laufenden Betrieb aktiv, gilt die Verbindung als verloren, sonst als nicht erreichbar.
     */
    private function SetStatusConnectionError(): void
    {
        if (($this->GetStatus() == IS_ACTIVE) && !$this->Connecting) {
            $this->SetStatus(self::IS_ConnectionLost);
        } elseif ($this->GetStatus() != self::IS_ConnectionLost) {
            $this->SetStatus(self::IS_NotReachable);
        }
    }

    /**
     * Liefert eine übersetzte Fehlermeldung zu einer HTTP-Fehlerantwort.
     *
     * @param int    $HttpCode HTTP-Statuscode
     * @param string $Body     Body der Antwort
     *
     * @return string Fehlermeldung
     */
    private function GetHttpErrorMessage(int $HttpCode, string $Body): string
    {
        $Message = \Portainer\Api\Response::GetErrorMessage($Body);
        if ($Message === '') {
            $Message = $this->Translate(\Portainer\Api\Response::GetHttpStatusText($HttpCode));
        }
        return sprintf($this->Translate('HTTP error %d: %s'), $HttpCode, $Message);
    }

    /**
     * Sendet eine Anfrage an die Portainer API.
     * Fehler werden per E_USER_NOTICE an den Aufrufer gemeldet.
     *
     * @param string $RequestURL    Pfad unterhalb von `/api`
     * @param string $RequestMethod HTTP-Methode
     * @param array  $PostData      Daten, die als JSON gesendet werden
     * @param int    $Timeout       Zeitüberschreitung in Millisekunden
     * @param array  $RequestHeader Zusätzliche Header
     * @param bool   $AllowRelogin  Bei 401 einmal neu anmelden und die Anfrage wiederholen
     *
     * @return bool|array Dekodierte Antwort, true bei Erfolg ohne Inhalt, false bei Fehler
     */
    private function SendRequest(string $RequestURL, string $RequestMethod = \Portainer\Api\HTTP::GET, array $PostData = [], int $Timeout = 5000, array $RequestHeader = [], bool $AllowRelogin = true): bool|array
    {
        /** @var array $_IPS */
        $this->LastHttpCode = 0;
        $this->LastError = '';
        if ($this->Host === '') {
            return false;
        }
        $IsAuthRequest = (strpos($RequestURL, \Portainer\Api\Url::Auth . '/') === 0) || ($RequestURL === \Portainer\Api\Url::Auth);
        $ApiKey = $this->ReadPropertyString(\Portainer\IO\Property::ApiKey);
        // Anmeldung ist fehlgeschlagen, neuer Versuch erfolgt per ApplyChanges (Nutzer oder Reparatur durch Symcon)
        if (($ApiKey === '') && !$IsAuthRequest && ($this->ReadAttributeString(\Portainer\IO\Attribute::Token) === '')) {
            trigger_error($this->Translate('Not logged in to Portainer.'), E_USER_NOTICE);
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
        if ($ApiKey !== '') {
            $Headers[] = 'X-API-Key: ' . $ApiKey;
        } elseif (!$IsAuthRequest) {
            $Headers[] = 'Authorization: Bearer ' . $this->ReadAttributeString(\Portainer\IO\Attribute::Token);
        }
        $ch = curl_init($CurlURL);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        // Portainer nutzt standardmäßig ein selbst signiertes Zertifikat, die Prüfung ist daher optional
        $VerifyCertificate = $this->ReadPropertyBoolean(\Portainer\IO\Property::VerifyCertificate);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $VerifyCertificate);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $VerifyCertificate ? 2 : 0);
        if (count($PostData)) {
            $Payload = json_encode($PostData);
            $this->SendDebug('RequestData:' . $_IPS['THREAD'], \Portainer\Api\Response::MaskSecrets($Payload), 0);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $Payload);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $Headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $RequestMethod);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $Timeout);
        $Response = curl_exec($ch);

        $HttpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($HttpCode != 0) {
            $this->SendDebug('Header Send:' . $_IPS['THREAD'], \Portainer\Api\Response::MaskSecrets((string) curl_getinfo($ch, CURLINFO_HEADER_OUT)), 0);
        }
        $Header = '';
        $Body = '';
        if (is_string($Response)) {
            $HeaderSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $Header = \Portainer\Api\Response::GetHeader($Response, $HeaderSize);
            $Body = \Portainer\Api\Response::GetBody($Response, $HeaderSize);
        }
        $this->SendDebug('Header Receive:' . $_IPS['THREAD'], \Portainer\Api\Response::MaskSecrets($Header), 0);
        $this->SendDebug('Body Receive:' . $_IPS['THREAD'], \Portainer\Api\Response::MaskSecrets($Body), 0);

        // Keine Verbindung oder Reverse-Proxy erreicht Portainer nicht
        if ($HttpCode == 0) {
            $Error = curl_error($ch);
            if ($Error === '') {
                $Error = (string) curl_strerror(curl_errno($ch));
            }
            $this->SendDebug('CURL ERROR', $Error, 0);
            $this->SetStatusConnectionError();
            trigger_error(sprintf($this->Translate('Portainer not reachable: %s'), $Error), E_USER_NOTICE);
            return false;
        }
        if (\Portainer\Api\Response::IsGatewayError($HttpCode)) {
            $this->SetStatusConnectionError();
            trigger_error($this->GetHttpErrorMessage($HttpCode, $Body), E_USER_NOTICE);
            return false;
        }

        // Token abgelaufen oder ungültig: einmal neu anmelden und Anfrage wiederholen
        // Mit API-Token gibt es keine Anmeldung, 401 bedeutet dann ein ungültiges oder widerrufenes Token
        if (($ApiKey === '') && !$IsAuthRequest && ($HttpCode == 401) && $AllowRelogin) {
            if ($this->Login()) {
                return $this->SendRequest($RequestURL, $RequestMethod, $PostData, $Timeout, $RequestHeader, false);
            }
            // Login hat den Fehler bereits gemeldet
            return false;
        }

        // Anmeldung abgelehnt oder Token trotz neuer Anmeldung ungültig
        if (($IsAuthRequest && !\Portainer\Api\Response::IsSuccess($HttpCode)) || ($HttpCode == 401)) {
            $this->SetStatus(self::IS_Unauthorized);
            trigger_error($this->GetHttpErrorMessage($HttpCode, $Body), E_USER_NOTICE);
            return false;
        }

        // Portainer ist erreichbar und die Anmeldung gilt, auch wenn die Anfrage selbst fehlschlägt
        $this->SetStatusActive();
        $this->LastHttpCode = $HttpCode;
        if (!\Portainer\Api\Response::IsSuccess($HttpCode)) {
            $this->ReportHttpError($this->GetHttpErrorMessage($HttpCode, $Body));
            return false;
        }
        $Result = \Portainer\Api\Response::DecodeBody($Body);
        if ($Result === false) {
            $this->ReportHttpError($this->Translate('Invalid response from Portainer.'));
        }
        return $Result;
    }

    /**
     * Merkt sich einen HTTP-Fehler und meldet ihn, wenn die Anfrage nicht von einer Kind-Instanz stammt.
     *
     * @param string $Error Fehlermeldung
     */
    private function ReportHttpError(string $Error): void
    {
        $this->LastError = $Error;
        if ($this->ReportHttpErrors) {
            trigger_error($Error, E_USER_NOTICE);
        }
    }
}
