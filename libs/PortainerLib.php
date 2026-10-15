<?php

declare(strict_types=1);

/**
 * PortainerLib
 * Enthält Klassen welche die API und Geräte Fähigkeiten abbilden.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       Custom NC-SA, siehe LICENSE im Hauptordner der Library
 *
 * @version       1.0
 */

namespace Portainer\Api
{
    /**
     * HTTP-Methoden der Portainer API.
     */
    class HTTP
    {
        public const GET = 'GET';
        public const PUT = 'PUT';
        public const POST = 'POST';
        public const DELETE = 'DELETE';
    }

    /**
     * Pfade der Portainer API (unterhalb von `/api`).
     * Die Docker API eines Environments erreicht Portainer unter `/endpoints/{id}/docker/...`.
     */
    class Url
    {
        public const Auth = '/auth';
        // Kleine Anfrage, die eine gültige Anmeldung erfordert (Prüfung des API-Tokens)
        public const AuthCheck = '/endpoints?limit=1';
        public const ListStacks = '/stacks';
        public const GetStack = '/stacks/%d';
        public const StartStack = '/start?endpointId=%d';
        public const StopStack = '/stop?endpointId=%d';
        public const ListEndpoints = '/endpoints';
        public const Endpoint = '/endpoints/%d';
        public const Dashboard = '/docker/%d/dashboard';
        // all=1: auch gestoppte Container
        public const ListDockerContainers = '/docker/containers/json?all=1';
        public const DockerContainerStart = '/docker/containers/%s/start';
        public const DockerContainerStop = '/docker/containers/%s/stop';
        public const DockerContainerPause = '/docker/containers/%s/pause';
        public const DockerContainerUnpause = '/docker/containers/%s/unpause';
        public const DockerContainerRestart = '/docker/containers/%s/restart';
        public const DockerContainerKill = '/docker/containers/%s/kill';
        public const DockerContainerInspect = '/docker/containers/%s/json';

        /**
         * Pfad eines Stacks.
         *
         * @param int $StackId ID des Stacks in Portainer
         */
        public static function GetStackUrl(int $StackId): string
        {
            return sprintf(self::GetStack, $StackId);
        }

        /**
         * Pfad zum Starten oder Stoppen eines Stacks.
         *
         * @param int    $EndpointId   ID des Environments des Stacks
         * @param int    $StackId      ID des Stacks
         * @param string $StartStopUrl self::StartStack oder self::StopStack
         */
        public static function GetStartStopUrl(int $EndpointId, int $StackId, string $StartStopUrl): string
        {
            return self::GetStackUrl($StackId) . sprintf($StartStopUrl, $EndpointId);
        }

        /**
         * Pfad eines Environments.
         *
         * @param int $EndpointId ID des Environments
         */
        public static function GetEndpointUrl(int $EndpointId): string
        {
            return sprintf(self::Endpoint, $EndpointId);
        }

        /**
         * Pfad der Übersicht (Dashboard) eines Docker-Environments.
         *
         * @param int $EndpointId ID des Environments
         */
        public static function GetDashboardUrl(int $EndpointId): string
        {
            return sprintf(self::Dashboard, $EndpointId);
        }

        /**
         * Pfad der Containerliste eines Docker-Environments (inkl. gestoppter Container).
         *
         * @param int $EndpointId ID des Environments
         */
        public static function ListDockerContainers(int $EndpointId): string
        {
            return self::GetEndpointUrl($EndpointId) . self::ListDockerContainers;
        }

        /**
         * Pfad der Docker API für einen Container.
         *
         * @param int    $EndpointId   ID des Environments
         * @param string $ContainerId  Name oder ID des Containers
         * @param string $ContainerUrl Eine der Konstanten self::DockerContainer*
         */
        public static function GetDockerContainerUrl(int $EndpointId, string $ContainerId, string $ContainerUrl): string
        {
            return self::GetEndpointUrl($EndpointId) . sprintf($ContainerUrl, $ContainerId);
        }
    }

    /**
     * Typen und Status der Environments (Endpoints) in Portainer.
     */
    class Endpoint
    {
        public const TypeDocker = 1;
        public const TypeAgentOnDocker = 2;
        public const TypeAzure = 3;
        public const TypeEdgeAgentOnDocker = 4;
        public const TypeKubernetesLocal = 5;
        public const TypeAgentOnKubernetes = 6;
        public const TypeEdgeAgentOnKubernetes = 7;

        public const StatusUp = 1;
        public const StatusDown = 2;

        /**
         * Prüft ob das Environment die Docker API bereitstellt (Dashboard und Container).
         *
         * @param int $Type Feld `Type` des Endpoints
         */
        public static function IsDocker(int $Type): bool
        {
            return in_array($Type, [self::TypeDocker, self::TypeAgentOnDocker, self::TypeEdgeAgentOnDocker], true);
        }

        /**
         * Prüft ob die Container live abgefragt werden können.
         * Edge-Agents verbinden sich nur zeitweise, offline Environments antworten nicht.
         *
         * @param array $Endpoint Daten des Endpoints
         */
        public static function CanQueryLive(array $Endpoint): bool
        {
            return in_array((int) ($Endpoint['Type'] ?? 0), [self::TypeDocker, self::TypeAgentOnDocker], true)
                && ((int) ($Endpoint['Status'] ?? 0) === self::StatusUp);
        }
    }

    /**
     * Auswertung von HTTP-Antworten der Portainer API.
     * Enthält keine Symcon-Abhängigkeiten, damit sie per Unit-Test geprüft werden kann.
     */
    class Response
    {
        /** @var string[] Englische Texte der HTTP-Statuscodes (Übersetzung erfolgt im Modul) */
        private const HttpStatusText = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            413 => 'Request Entity Too Large',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout'
        ];

        /**
         * Prüft ob der HTTP-Statuscode eine erfolgreiche Antwort darstellt.
         * 304 (Not Modified) liefert die Docker API z.B. beim Starten eines bereits laufenden Containers.
         *
         * @param int $HttpCode HTTP-Statuscode
         *
         * @return bool true bei 2xx und 304
         */
        public static function IsSuccess(int $HttpCode): bool
        {
            return (($HttpCode >= 200) && ($HttpCode < 300)) || ($HttpCode === 304);
        }

        /**
         * Prüft ob ein vorgeschalteter Reverse-Proxy meldet, dass Portainer selbst nicht erreichbar ist.
         *
         * @param int $HttpCode HTTP-Statuscode
         *
         * @return bool true bei 502, 503 und 504
         */
        public static function IsGatewayError(int $HttpCode): bool
        {
            return in_array($HttpCode, [502, 503, 504], true);
        }

        /**
         * Liefert den letzten Header-Block einer Antwort.
         * Bei Weiterleitungen oder `100 Continue` enthält die Antwort mehrere Header-Blöcke.
         *
         * @param string $Response   Komplette Antwort inkl. Header
         * @param int    $HeaderSize Größe aller Header (CURLINFO_HEADER_SIZE)
         *
         * @return string Header der letzten Antwort
         */
        public static function GetHeader(string $Response, int $HeaderSize): string
        {
            $Blocks = explode("\r\n\r\n", trim(substr($Response, 0, $HeaderSize)));
            return (string) end($Blocks);
        }

        /**
         * Liefert den Body einer Antwort.
         *
         * @param string $Response   Komplette Antwort inkl. Header
         * @param int    $HeaderSize Größe aller Header (CURLINFO_HEADER_SIZE)
         *
         * @return string Body der Antwort
         */
        public static function GetBody(string $Response, int $HeaderSize): string
        {
            return substr($Response, $HeaderSize);
        }

        /**
         * Dekodiert den Body einer erfolgreichen Antwort.
         *
         * @param string $Body Body der Antwort
         *
         * @return bool|array true bei leerem Body, Array bei gültigem JSON, sonst false
         */
        public static function DecodeBody(string $Body): bool|array
        {
            if (trim($Body) === '') {
                return true;
            }
            $Data = json_decode($Body, true);
            return is_array($Data) ? $Data : false;
        }

        /**
         * Liefert die Fehlermeldung aus dem Body einer Fehlerantwort.
         * Portainer liefert `{"message":"...","details":"..."}`, die Docker API `{"message":"..."}`.
         *
         * @param string $Body Body der Antwort
         *
         * @return string Fehlermeldung oder Leerstring, wenn der Body keine enthält
         */
        public static function GetErrorMessage(string $Body): string
        {
            $Data = json_decode($Body, true);
            if (!is_array($Data)) {
                return '';
            }
            $Message = is_string($Data['message'] ?? null) ? trim($Data['message']) : '';
            $Details = is_string($Data['details'] ?? null) ? trim($Data['details']) : '';
            if ($Message === '') {
                return $Details;
            }
            if (($Details !== '') && ($Details !== $Message)) {
                return $Message . ': ' . $Details;
            }
            return $Message;
        }

        /**
         * Liefert den englischen Text zu einem HTTP-Statuscode.
         *
         * @param int $HttpCode HTTP-Statuscode
         *
         * @return string Text oder `Unknown error`
         */
        public static function GetHttpStatusText(int $HttpCode): string
        {
            return self::HttpStatusText[$HttpCode] ?? 'Unknown error';
        }

        /**
         * Maskiert Passwörter, Tokens und Umgebungsvariablen für Debug-Ausgaben.
         *
         * @param string $Text JSON-Daten oder HTTP-Header
         *
         * @return string Text mit maskierten Zugangsdaten
         */
        public static function MaskSecrets(string $Text): string
        {
            $Data = json_decode($Text, true);
            if (is_array($Data)) {
                return (string) json_encode(self::MaskSecretsInData($Data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $Text = preg_replace('/"(jwt|password)"\s*:\s*"(?:[^"\\\\]|\\\\.)*"/i', '"$1":"***"', $Text);
            $Text = preg_replace('/^((?:Authorization:\s*Bearer|X-API-Key:)\s*)\S+/im', '$1***', $Text);
            // Portainer liefert das Token zusätzlich als Cookie `portainer_api_key`
            return preg_replace('/^((?:Set-)?Cookie:\s*[^=;\s]+=)[^;\r\n]*/im', '$1***', $Text);
        }

        /**
         * Maskiert Passwörter, Tokens und Umgebungsvariablen in dekodierten API-Daten.
         * Umgebungsvariablen von Stacks und Containern enthalten oft Zugangsdaten (z.B. Datenbank-Passwörter).
         *
         * @param array $Data Dekodierte API-Daten
         *
         * @return array Daten mit maskierten Werten, die Namen der Umgebungsvariablen bleiben erhalten
         */
        public static function MaskSecretsInData(array $Data): array
        {
            foreach ($Data as $Key => &$Value) {
                if (is_string($Key) && in_array(strtolower($Key), ['jwt', 'password'], true) && is_string($Value)) {
                    $Value = '***';
                    continue;
                }
                if (($Key === 'Env') && is_array($Value)) {
                    $Value = self::MaskEnvironment($Value);
                    continue;
                }
                if (is_array($Value)) {
                    $Value = self::MaskSecretsInData($Value);
                }
            }
            return $Data;
        }

        /**
         * Maskiert die Werte von Umgebungsvariablen.
         * Docker liefert `["NAME=Wert"]`, Portainer-Stacks `[{"name":"NAME","value":"Wert"}]`.
         *
         * @param array $Environment Liste der Umgebungsvariablen
         *
         * @return array Liste mit maskierten Werten
         */
        private static function MaskEnvironment(array $Environment): array
        {
            foreach ($Environment as &$Entry) {
                if (is_string($Entry)) {
                    $Pos = strpos($Entry, '=');
                    if ($Pos !== false) {
                        $Entry = substr($Entry, 0, $Pos + 1) . '***';
                    }
                } elseif (is_array($Entry) && array_key_exists('value', $Entry)) {
                    $Entry['value'] = '***';
                }
            }
            return $Environment;
        }
    }
}

namespace Portainer
{
    /**
     * GUIDs der Module und Datenfluss-Schnittstellen (siehe module.json).
     */
    class GUID
    {
        public const IO = '{FEB4D3D2-AD8A-9C6A-72D8-DF99AC625768}';
        public const Configurator = '{781E99AA-6F79-4430-0DF5-388D82E29019}';
        public const Container = '{80AA764D-EABE-B85E-D997-43A2D244D6E8}';
        public const Stack = '{B4110D02-0282-F7F6-363C-906280A3510A}';
        public const System = '{80544234-7E77-8F76-8376-D6C285B58443}';
        /** Schnittstelle IO → Kinder; nur für die Zuordnung in module.json, der IO sendet nichts an die Kinder */
        public const SendToChild = '{80CBC81C-3722-EFA3-BF6F-D7CC9B0E0076}';
        /** Schnittstelle Kinder → IO (ForwardData) */
        public const SendToIO = '{A5558F9B-F5AE-A5B0-C252-328740544A21}';
    }

    /**
     * Felder einer Anfrage der Kind-Instanzen an den IO.
     */
    class ForwardData
    {
        public const DataID = 'DataID';
        public const Uri = 'URI';
        public const Method = 'Method';
        public const Data = 'Data';
        public const Timeout = 'Timeout';
    }

    /**
     * Felder der Antwort des IO auf eine weitergeleitete Anfrage.
     * HttpCode 0 bedeutet: Verbindungs- oder Anmeldefehler, den der IO bereits selbst gemeldet hat.
     */
    class IOResponse
    {
        public const HttpCode = 'HttpCode';
        public const Result = 'Result';
        public const Error = 'Error';
    }

    /**
     * Statuscodes der Kind-Instanzen (Container, System, Stack).
     */
    class DeviceStatus
    {
        public const NotFound = IS_EBASE + 1;
        public const RequestFailed = IS_EBASE + 2;
    }

    /**
     * Timer der Kind-Instanzen.
     */
    class Timer
    {
        public const UpdateInfo = 'UpdateInfo';
        public const RefreshAfterAction = 'RefreshAfterAction';
        /** Wartezeit vor der Nachaktualisierung nach einer Aktion in Mikrosekunden */
        public const RefreshDelay = 1000000;
    }

    /**
     * Idents für RequestAction der Kind-Instanzen.
     */
    class Action
    {
        public const UpdateState = 'UpdateState';
    }

    // Felder einer Variablendefinition in den $Config-Tabellen der Module
    /** Darstellung der Variable (Parameter wie bei IPS_SetVariableCustomPresentation) */
    const IPSPresentation = 'Presentation';
    /** Englischer Name, wird über libs/locale.json übersetzt */
    const IPSVarName = 'VarName';
    /** Variablentyp (VARIABLETYPE_*) */
    const IPSVarType = 'VarType';
    /** Variable ist schaltbar (RequestAction) */
    const IPSVarAction = 'VarAction';

    /**
     * Übersetzung von Variablennamen und Darstellungen aus libs/locale.json.
     */
    class Presentation
    {
        /** @var array|null Übersetzungen aus libs/locale.json, wird je PHP-Aufruf einmal gelesen */
        private static ?array $Translation = null;

        /**
         * Übersetzt einen Text (Variablennamen, Beschriftungen) in die Systemsprache.
         * Fehlt der Text für die vollständige Sprache (z.B. `de_DE`), wird die Sprache ohne Region (`de`) genutzt.
         *
         * @param string $Text Englischer Text
         *
         * @return string Übersetzung oder der Text selbst
         */
        public static function TranslateValue(string $Text): string
        {
            $Translations = self::GetPresentationTranslation()['translations'] ?? [];
            $Language = IPS_GetSystemLanguage();
            $Code = explode('_', $Language)[0];
            return $Translations[$Language][$Text] ?? $Translations[$Code][$Text] ?? $Text;
        }

        /**
         * Übersetzt die Texte einer Darstellung und kodiert OPTIONS und INTERVALS als JSON,
         * wie es MaintainVariable erwartet.
         *
         * @param array $Presentation Darstellung aus einer Variablendefinition
         *
         * @return array Darstellung für MaintainVariable
         */
        public static function TranslatePresentation(array $Presentation): array
        {
            if (isset($Presentation['PREFIX'])) {
                $Presentation['PREFIX'] = self::TranslateValue($Presentation['PREFIX']);
            }
            if (isset($Presentation['SUFFIX'])) {
                $Presentation['SUFFIX'] = self::TranslateValue($Presentation['SUFFIX']);
            }
            if (isset($Presentation['OPTIONS'])) {
                $Options = $Presentation['OPTIONS'];
                foreach ($Options as &$Option) {
                    $Option['Caption'] = self::TranslateValue($Option['Caption']);
                }
                $Presentation['OPTIONS'] = json_encode($Options);
            }
            if (isset($Presentation['INTERVALS'])) {
                $Intervals = $Presentation['INTERVALS'];
                foreach ($Intervals as &$Interval) {
                    if (isset($Interval['ConstantValue'])) {
                        $Interval['ConstantValue'] = self::TranslateValue($Interval['ConstantValue']);
                    }
                    if (isset($Interval['PrefixValue'])) {
                        $Interval['PrefixValue'] = self::TranslateValue($Interval['PrefixValue']);
                    }
                    if (isset($Interval['SuffixValue'])) {
                        $Interval['SuffixValue'] = self::TranslateValue($Interval['SuffixValue']);
                    }
                }
                $Presentation['INTERVALS'] = json_encode($Intervals);
            }
            return $Presentation;
        }

        private static function GetPresentationTranslation(): array
        {
            if (self::$Translation === null) {
                $Translation = json_decode((string) file_get_contents(__DIR__ . '/locale.json'), true);
                self::$Translation = is_array($Translation) ? $Translation : [];
            }
            return self::$Translation;
        }
    }

    trait Variables
    {
        /**
         * Schreibt die Werte aus den API-Daten in die Statusvariablen.
         *
         * @param array         $Data                API-Daten
         * @param array         $Variables           Variablendefinitionen (verschachtelt wie die API-Daten)
         * @param int           $Pos                 Laufende Position der Variablen
         * @param string        $Prefix              Präfix des Idents bei verschachtelten Daten
         * @param callable|null $AdjustSpecialValues Umrechnung einzelner Werte (Ident, &Wert)
         * @param bool          $MaintainVariables   Variablen anlegen bzw. Name/Darstellung aktualisieren.
         *                                           Ohne werden nur fehlende Variablen angelegt.
         */
        protected function SetStateVariables(array $Data, array $Variables, int &$Pos = 0, string $Prefix = '', ?callable $AdjustSpecialValues = null, bool $MaintainVariables = true): void
        {
            foreach ($Variables as $Key => $VariableData) {

                if (!isset($VariableData[\Portainer\IPSVarType])) {
                    if (isset($Data[$Key])) {
                        $this->SetStateVariables($Data[$Key], $VariableData, $Pos, $Key, $AdjustSpecialValues, $MaintainVariables);
                    }
                    continue;
                }
                $Pos++;
                if (isset($Data[$Key])) {
                    $Ident = $Prefix !== '' ? $Prefix . '_' . $Key : $Key;
                    $VariableID = $this->FindIDForIdent($Ident);
                    // Liefert die API einen Wert erst später, wird die Variable dann angelegt
                    if ($MaintainVariables || ($VariableID === 0)) {
                        $Type = $VariableData[\Portainer\IPSVarType];
                        // Den Typ einer vorhandenen Variable nie ändern: MaintainVariable würde sie samt Archivdaten
                        // löschen und neu anlegen (z.B. Integer → Float auf 32-Bit-Systemen). Das entscheidet der Nutzer.
                        if ($VariableID !== 0) {
                            $ExistingType = IPS_GetVariable($VariableID)['VariableType'];
                            if ($ExistingType !== $Type) {
                                $this->SendDebug('Variable type kept', $Ident . ': ' . $ExistingType . ' instead of ' . $Type, 0);
                                $Type = $ExistingType;
                            }
                        }
                        $this->MaintainVariable(
                            $Ident,
                            \Portainer\Presentation::TranslateValue($VariableData[\Portainer\IPSVarName]),
                            $Type,
                            \Portainer\Presentation::TranslatePresentation(
                                $VariableData[\Portainer\IPSPresentation]
                            ),
                            $Pos,
                            true
                        );
                        if ($VariableData[\Portainer\IPSVarAction]) {
                            $this->EnableAction($Ident);
                        }
                    }
                    if ($AdjustSpecialValues !== null) {
                        $AdjustSpecialValues($Ident, $Data[$Key]);
                    }
                    $this->SetValue($Ident, $Data[$Key]);
                }
            }
        }
    }

    /**
     * Anfragen an den Portainer IO.
     * Benötigt DebugHelper.
     */
    trait SendToIO
    {
        /**
         * Sendet eine Anfrage über den IO an die Portainer API.
         *
         * @param string $Uri     Pfad unterhalb von `/api`
         * @param string $Method  HTTP-Methode
         * @param int    $Timeout Zeitüberschreitung in Millisekunden
         *
         * @return array Antwort mit den Feldern aus IOResponse
         */
        protected function SendToIO(string $Uri, string $Method = \Portainer\Api\HTTP::GET, int $Timeout = 5000): array
        {
            $this->SendDebug('Request', $Method . ' ' . $Uri, 0);
            $Raw = $this->SendDataToParent(json_encode([
                ForwardData::DataID  => GUID::SendToIO,
                ForwardData::Uri     => $Uri,
                ForwardData::Method  => $Method,
                ForwardData::Data    => [],
                ForwardData::Timeout => $Timeout
            ]));
            $Response = ($Raw === '') ? false : unserialize($Raw, ['allowed_classes' => false]);
            if (!is_array($Response) || !array_key_exists(IOResponse::Result, $Response)) {
                // Keine Antwort: Der IO hat den Fehler (Verbindung, Anmeldung) bereits gemeldet
                $Response = [IOResponse::HttpCode => 0, IOResponse::Result => false, IOResponse::Error => ''];
            }
            $DebugResponse = $Response;
            if (is_array($DebugResponse[IOResponse::Result])) {
                $DebugResponse[IOResponse::Result] = \Portainer\Api\Response::MaskSecretsInData($DebugResponse[IOResponse::Result]);
            }
            $this->SendDebug('Response', $DebugResponse, 0);
            return $Response;
        }
    }

    /**
     * Gemeinsame Logik der Kind-Instanzen (Docker Container, Docker System, Portainer Stack).
     * Benötigt BufferHelper, DebugHelper, InstanceStatus (ParentIOHelper) und Variables.
     *
     * Fehlerbehandlung: Ein Abruf per Timer oder nach Statuswechsel des IO meldet einen Fehler nur,
     * wenn sich der Status der Instanz dadurch ändert. Aufrufe durch den Nutzer melden jeden Fehler.
     *
     * @property bool $VariablesMaintained Variablen seit dem letzten ApplyChanges angelegt bzw. aktualisiert
     */
    trait Device
    {
        use SendToIO;

        /**
         * Prüft ob die Instanz vollständig konfiguriert ist.
         */
        abstract protected function IsConfigured(): bool;

        /**
         * Liefert den Pfad der API für den Status der Instanz.
         */
        abstract protected function GetStateUri(): string;

        /**
         * Verarbeitet die API-Daten des Status.
         *
         * @param array $Data              API-Daten
         * @param bool  $MaintainVariables Variablen anlegen bzw. Name/Darstellung aktualisieren
         */
        abstract protected function ProcessState(array $Data, bool $MaintainVariables): void;

        /**
         * Gemeinsamer Teil von ApplyChanges.
         *
         * @param int $Interval Intervall der Statusabfrage in Sekunden, 0 = aus
         */
        protected function ApplyDeviceChanges(int $Interval): void
        {
            $this->VariablesMaintained = false;
            $this->RegisterMessage($this->InstanceID, FM_CONNECT);
            $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
            if (!$this->IsConfigured()) {
                $this->SetTimerInterval(Timer::UpdateInfo, 0);
                $this->SetStatus(IS_INACTIVE);
                return;
            }
            $this->SetTimerInterval(Timer::UpdateInfo, $Interval * 1000);
            // Beim Systemstart erst nach IPS_KERNELSTARTED abfragen
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            $this->RegisterParent();
            if ($this->HasActiveParent()) {
                $this->UpdateState(false);
            }
        }

        /**
         * Wird nach dem Systemstart (IPS_KERNELSTARTED) aufgerufen.
         */
        protected function KernelReady(): void
        {
            $this->RegisterParent();
            if ($this->IsConfigured() && $this->HasActiveParent()) {
                $this->UpdateState(false);
            }
        }

        /**
         * Wird vom InstanceStatus-Helper bei einem Statuswechsel des IO aufgerufen.
         *
         * @param int $State Neuer Status des IO
         */
        protected function IOChangeState(int $State): void
        {
            if (($State == IS_ACTIVE) && $this->IsConfigured()) {
                $this->UpdateState(false);
            }
        }

        /**
         * Fragt den Status ab und aktualisiert die Statusvariablen.
         *
         * @param bool $ReportErrors Jeden Fehler melden (Aufruf durch den Nutzer)
         *
         * @return bool true wenn die Statusvariablen aktualisiert wurden
         */
        protected function UpdateState(bool $ReportErrors): bool
        {
            if (!$this->CanSendRequest($ReportErrors)) {
                return false;
            }
            $Response = $this->SendToIO($this->GetStateUri());
            if (!is_array($Response[IOResponse::Result])) {
                $this->HandleRequestError($Response, $ReportErrors, true);
                return false;
            }
            if ($this->GetStatus() != IS_ACTIVE) {
                $this->SetStatus(IS_ACTIVE);
            }
            $this->ProcessState($Response[IOResponse::Result], !$this->VariablesMaintained);
            $this->VariablesMaintained = true;
            return true;
        }

        /**
         * Sendet eine Aktion (z.B. Start/Stop). Fehler werden immer gemeldet, der Status bleibt unverändert.
         * Nach Erfolg werden die Statusvariablen kurz darauf neu abgefragt.
         *
         * @param string $Uri     Pfad unterhalb von `/api`
         * @param string $Method  HTTP-Methode
         * @param int    $Timeout Zeitüberschreitung in Millisekunden
         *
         * @return bool true wenn Portainer die Aktion angenommen hat
         */
        protected function SendAction(string $Uri, string $Method = \Portainer\Api\HTTP::POST, int $Timeout = 5000): bool
        {
            if (!$this->CanSendRequest(true)) {
                return false;
            }
            $Response = $this->SendToIO($Uri, $Method, $Timeout);
            if ($Response[IOResponse::Result] === false) {
                $this->HandleRequestError($Response, true, false);
                return false;
            }
            // Eigener Thread, damit der Aufrufer nicht auf die Nachaktualisierung warten muss
            $this->RegisterOnceTimer(
                Timer::RefreshAfterAction,
                'usleep(' . Timer::RefreshDelay . ');IPS_RequestAction(' . $this->InstanceID . ', \'' . Action::UpdateState . '\', 0);'
            );
            return true;
        }

        /**
         * Führt eine Bedienung aus der Visualisierung aus (RequestAction einer Statusvariable).
         * Fehler werden per echo ausgegeben, damit Symcon sie dem Nutzer anzeigt.
         *
         * @param callable $Action Auszuführende Aktion
         */
        protected function RunUserAction(callable $Action): void
        {
            set_error_handler([$this, 'ModulErrorHandler'], E_USER_NOTICE | E_USER_WARNING);
            try {
                $Action();
            } finally {
                restore_error_handler();
            }
        }

        /**
         * Gibt Fehlermeldungen bei Bedienung aus der Visualisierung aus.
         */
        protected function ModulErrorHandler(int $errno, string $errstr): bool
        {
            echo $errstr . PHP_EOL;
            return true;
        }

        /**
         * Prüft Konfiguration und IO vor einer Anfrage.
         *
         * @param bool $ReportErrors Fehler melden
         *
         * @return bool true wenn die Anfrage gesendet werden kann
         */
        private function CanSendRequest(bool $ReportErrors): bool
        {
            if (!$this->IsConfigured()) {
                if ($ReportErrors) {
                    trigger_error($this->Translate('Instance is not configured.'), E_USER_NOTICE);
                }
                return false;
            }
            if (!$this->HasActiveParent()) {
                if ($ReportErrors) {
                    trigger_error($this->Translate('Portainer IO is not active.'), E_USER_NOTICE);
                }
                return false;
            }
            return true;
        }

        /**
         * Wertet eine fehlgeschlagene Anfrage aus.
         *
         * @param array $Response     Antwort von SendToIO
         * @param bool  $ReportErrors Fehler immer melden, sonst nur bei Statuswechsel
         * @param bool  $UpdateStatus Status der Instanz anhand des Fehlers setzen
         */
        private function HandleRequestError(array $Response, bool $ReportErrors, bool $UpdateStatus): void
        {
            $HttpCode = (int) $Response[IOResponse::HttpCode];
            if ($HttpCode == 0) {
                // Fehler der Verbindung oder Anmeldung meldet der IO selbst
                return;
            }
            $Error = (string) $Response[IOResponse::Error];
            if ($Error === '') {
                $Error = $this->Translate('Invalid response from Portainer.');
            }
            $this->SendDebug('Error', $Error, 0);
            $StatusChanged = false;
            if ($UpdateStatus) {
                $NewStatus = ($HttpCode == 404) ? DeviceStatus::NotFound : DeviceStatus::RequestFailed;
                $StatusChanged = ($this->GetStatus() != $NewStatus);
                if ($StatusChanged) {
                    $this->SetStatus($NewStatus);
                }
            }
            if ($ReportErrors || $StatusChanged) {
                trigger_error($Error, E_USER_NOTICE);
            }
        }
    }
}

namespace Portainer\IO
{
    /**
     * Eigenschaften des Portainer IO.
     */
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Username = 'Username';
        public const Password = 'Password';
        /** TLS-Zertifikat von Portainer prüfen (aus: auch selbst signierte Zertifikate werden akzeptiert) */
        public const VerifyCertificate = 'VerifyCertificate';
        /** API-Token (Access Token) eines Portainer-Benutzers; gesetzt ersetzt es Benutzername und Passwort */
        public const ApiKey = 'ApiKey';
    }

    /**
     * Attribute des Portainer IO.
     */
    class Attribute
    {
        /** JWT der Anmeldung, zugleich Feldname in der Antwort von /auth */
        public const Token = 'jwt';
    }

    /**
     * Timer des Portainer IO.
     */
    class Timer
    {
        public const UpdateToken = 'UpdateToken';
    }

    /**
     * Idents für RequestAction des Portainer IO.
     */
    class Action
    {
        /** Timer: Sitzungs-Token erneuern */
        public const UpdateToken = 'UpdateToken';
        /** Formular: API-Token wurde geändert, Felder für Benutzername und Passwort umschalten */
        public const ApiKeyChanged = 'ApiKeyChanged';
    }
}

namespace Docker\System
{
    /**
     * Eigenschaften von Docker System.
     */
    class Property
    {
        public const EnvironmentId = 'EnvironmentId';
        public const UpdateInterval = 'Interval';
    }

    /**
     * Idents und Definitionen der Statusvariablen.
     * $Config ist wie die Antwort der API verschachtelt, Idents darunter erhalten das Präfix der Gruppe.
     */
    class Variables
    {
        public const Running = 'running';
        public const Stopped = 'stopped';
        public const Healthy = 'healthy';
        public const Unhealthy = 'unhealthy';
        public const Total = 'total';
        public const Services = 'services';
        public const Size = 'size';
        public const Volumes = 'volumes';
        public const Networks = 'networks';
        public const Stacks = 'stacks';
        /**
         * Größen in Byte: Integer nur auf 64-Bit-Systemen. Auf 32-Bit-Systemen (z.B. armhf) endet Integer bei 2 GiB,
         * dort wird Float verwendet (Symcon-Doku zu SetValueInteger).
         */
        public const SizeType = PHP_INT_SIZE >= 8 ? VARIABLETYPE_INTEGER : VARIABLETYPE_FLOAT;
        /** Obergrenze des letzten Intervalls, der Typ folgt dem Variablentyp */
        public const SizeMax = PHP_INT_SIZE >= 8 ? PHP_INT_MAX : 1.0E18;
        public static $Config = [
            'containers'=> [
                self::Running=> [
                    \Portainer\IPSVarName     => 'No of running containers',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::Stopped=> [
                    \Portainer\IPSVarName     => 'No of stopped containers',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::Healthy=> [
                    \Portainer\IPSVarName     => 'No of healthy containers',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::Unhealthy=> [
                    \Portainer\IPSVarName     => 'No of unhealthy containers',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::Total=> [
                    \Portainer\IPSVarName     => 'No of total containers',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ]],
            self::Services=> [
                \Portainer\IPSVarName     => 'No of services',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            'images'=> [
                self::Total=> [
                    \Portainer\IPSVarName     => 'No of images',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::Size=> [
                    \Portainer\IPSVarName     => 'Size of all images',
                    \Portainer\IPSVarType     => self::SizeType,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION'    => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'ICON'            => 'hard-drive',
                        'COLOR'           => -1,
                        'PREFIX'          => '',
                        'SUFFIX'          => ' Byte',
                        'USAGE_TYPE'      => 0,
                        'PERCENTAGE'      => false,
                        'MIN'             => 0,
                        'MAX'             => 0,
                        'DIGITS'          => 2,
                        'INTERVALS_ACTIVE'=> true,
                        'INTERVALS'       => [
                            [
                                'IntervalMinValue'            => 0,
                                'IntervalMaxValue'            => 1023,
                                'ConstantActive'              => false,
                                'ConstantValue'               => '',
                                'ConversionFactor'            => 0,
                                'PrefixActive'                => false,
                                'PrefixValue'                 => '',
                                'SuffixActive'                => false,
                                'SuffixValue'                 => '',
                                'DigitsActive'                => false,
                                'DigitsValue'                 => 0,
                                'IconActive'                  => false,
                                'IconValue'                   => '',
                                'ColorActive'                 => false,
                                'ColorValue'                  => -1
                            ],
                            [
                                'IntervalMinValue'            => 1024,
                                'IntervalMaxValue'            => 1048575,
                                'ConstantActive'              => false,
                                'ConstantValue'               => '',
                                'ConversionFactor'            => 1024,
                                'PrefixActive'                => false,
                                'PrefixValue'                 => '',
                                'SuffixActive'                => true,
                                'SuffixValue'                 => ' kByte',
                                'DigitsActive'                => true,
                                'DigitsValue'                 => 2,
                                'IconActive'                  => false,
                                'IconValue'                   => '',
                                'ColorActive'                 => false,
                                'ColorValue'                  => -1
                            ],
                            [
                                'IntervalMinValue'            => 1048576,
                                'IntervalMaxValue'            => 1073741823,
                                'ConstantActive'              => false,
                                'ConstantValue'               => '',
                                'ConversionFactor'            => 1048576,
                                'PrefixActive'                => false,
                                'PrefixValue'                 => '',
                                'SuffixActive'                => true,
                                'SuffixValue'                 => ' MByte',
                                'DigitsActive'                => true,
                                'DigitsValue'                 => 2,
                                'IconActive'                  => false,
                                'IconValue'                   => '',
                                'ColorActive'                 => false,
                                'ColorValue'                  => -1
                            ],
                            [
                                'IntervalMinValue'            => 1073741824,
                                'IntervalMaxValue'            => self::SizeMax,
                                'ConstantActive'              => false,
                                'ConstantValue'               => '',
                                'ConversionFactor'            => 1073741824,
                                'PrefixActive'                => false,
                                'PrefixValue'                 => '',
                                'SuffixActive'                => true,
                                'SuffixValue'                 => ' GByte',
                                'DigitsActive'                => true,
                                'DigitsValue'                 => 2,
                                'IconActive'                  => false,
                                'IconValue'                   => '',
                                'ColorActive'                 => false,
                                'ColorValue'                  => -1
                            ]
                        ]
                    ],
                    \Portainer\IPSVarAction   => false
                ]
            ],
            self::Volumes=> [
                \Portainer\IPSVarName     => 'No of volumes',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            self::Networks=> [
                \Portainer\IPSVarName     => 'No of networks',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            self::Stacks=> [
                \Portainer\IPSVarName     => 'No of stacks',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ]
        ];
    }
}

namespace Docker\Container
{
    /**
     * Zustände eines Containers (Feld `State.Status` der Docker API).
     */
    class ContainerState
    {
        public const Created = 'created';
        public const Running = 'running';
        public const Paused = 'paused';
        public const Restarting = 'restarting';
        public const Removing = 'removing';
        public const Exited = 'exited';
        public const Dead = 'dead';
    }

    /**
     * Eigenschaften von Docker Container.
     */
    class Property
    {
        public const EnvironmentId = 'EnvironmentId';
        public const ContainerName = 'ContainerName';
        public const UpdateInterval = 'Interval';
    }

    /**
     * Idents und Definitionen der Statusvariablen.
     * $Config ist wie die Antwort der API verschachtelt, Idents darunter erhalten das Präfix der Gruppe.
     */
    class Variables
    {
        public const Hostname = 'Hostname';
        public const Image = 'Image';
        public const Created = 'Created';
        public const Name = 'Name';
        public const Platform = 'Platform';
        public const RestartCount = 'RestartCount';
        public const Running = 'Running';
        public const Restarting = 'Restarting';
        public const Paused = 'Paused';
        public const Dead = 'Dead';
        public const Error = 'Error';
        public const Status = 'Status';
        public const StartedAt = 'StartedAt';
        public const ExitCode = 'ExitCode';
        public const OOMKilled = 'OOMKilled';
        /** Untergruppe `State.Health`, nur vorhanden wenn das Image einen Healthcheck definiert */
        public const Health = 'Health';
        /** Gruppe `State` der Docker API, Idents darunter erhalten das Präfix `State_` */
        public const State = 'State';
        public const StatusIdent = self::State . '_' . self::Status;
        public const StartedAtIdent = self::State . '_' . self::StartedAt;
        public static $Config = [
            'Config'=> [
                self::Hostname=> [
                    \Portainer\IPSVarName     => self::Hostname,
                    \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::Image=> [
                    \Portainer\IPSVarName     => self::Image,
                    \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
            ],
            self::Created=> [
                \Portainer\IPSVarName     => self::Created,
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'           => 1,
                    'MONTH_TEXT'     => false,
                    'DAY_OF_THE_WEEK'=> false,
                    'TIME'           => 2
                ],
                \Portainer\IPSVarAction   => false
            ],
            self::Name=> [
                \Portainer\IPSVarName     => self::Name,
                \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            self::Platform=> [
                \Portainer\IPSVarName     => self::Platform,
                \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            self::RestartCount=> [
                \Portainer\IPSVarName     => 'No of Restarts',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            self::State=> [
                self::Running=> [
                    \Portainer\IPSVarName     => self::Running,
                    \Portainer\IPSVarType     => VARIABLETYPE_BOOLEAN,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'ICON'         => '',

                        'OPTIONS'      => [
                            [
                                'Value'       => false,
                                'Caption'     => 'not running',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'ColorActive' => false,
                                'ColorValue'  => -1
                            ],
                            [
                                'Value'       => true,
                                'Caption'     => 'running',
                                'IconActive'  => true,
                                'IconValue'   => 'person-running-fast',
                                'ColorActive' => true,
                                'ColorValue'  => 0x00ff00
                            ]
                        ]
                    ],
                    \Portainer\IPSVarAction   => false
                ],
                self::Restarting=> [
                    \Portainer\IPSVarName     => self::Restarting,
                    \Portainer\IPSVarType     => VARIABLETYPE_BOOLEAN,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'ICON'         => '',
                        'OPTIONS'      => [
                            [
                                'Value'       => false,
                                'Caption'     => 'not restarting',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'ColorActive' => false,
                                'ColorValue'  => -1
                            ],
                            [
                                'Value'       => true,
                                'Caption'     => 'restarting',
                                'IconActive'  => true,
                                'IconValue'   => 'arrows-repeat',
                                'ColorActive' => true,
                                'ColorValue'  => 0xff0000
                            ]
                        ]
                    ],
                    \Portainer\IPSVarAction   => false
                ],
                self::Paused=> [
                    \Portainer\IPSVarName     => self::Paused,
                    \Portainer\IPSVarType     => VARIABLETYPE_BOOLEAN,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'OPTIONS'      => [
                            [
                                'Value'       => false,
                                'Caption'     => 'not paused',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'ColorActive' => false,
                                'ColorValue'  => -1
                            ],
                            [
                                'Value'       => true,
                                'Caption'     => 'paused',
                                'IconActive'  => true,
                                'IconValue'   => 'pause',
                                'ColorActive' => true,
                                'ColorValue'  => 0x0000ff
                            ]
                        ]
                    ],
                    \Portainer\IPSVarAction   => false
                ],
                self::Dead=> [
                    \Portainer\IPSVarName     => self::Dead,
                    \Portainer\IPSVarType     => VARIABLETYPE_BOOLEAN,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'OPTIONS'      => [
                            [
                                'Value'       => false,
                                'Caption'     => 'not dead',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'ColorActive' => false,
                                'ColorValue'  => -1
                            ],
                            [
                                'Value'       => true,
                                'Caption'     => 'dead',
                                'IconActive'  => true,
                                'IconValue'   => 'skull',
                                'ColorActive' => true,
                                'ColorValue'  => 0xff0000
                            ]
                        ]
                    ],
                    \Portainer\IPSVarAction   => false
                ],
                self::Error=> [
                    \Portainer\IPSVarName     => self::Error,
                    \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'ICON'         => 'message-xmark'

                    ],
                    \Portainer\IPSVarAction   => false
                ],
                self::Status=> [
                    \Portainer\IPSVarName     => self::Status,
                    \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                        'ICON'         => 'gear-code',
                        'OPTIONS'      => [
                            [
                                'Value'       => ContainerState::Created,
                                'Caption'     => 'Created',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'Color'       => -1
                            ],
                            [
                                'Value'       => ContainerState::Running,
                                'Caption'     => 'Running',
                                'IconActive'  => true,
                                'IconValue'   => 'person-running',
                                'Color'       => 0x00ff00
                            ],
                            [
                                'Value'       => ContainerState::Paused,
                                'Caption'     => 'Paused',
                                'IconActive'  => true,
                                'IconValue'   => 'pause',
                                'Color'       => 0x0000ff
                            ],
                            [
                                'Value'       => ContainerState::Restarting,
                                'Caption'     => 'Restarting',
                                'IconActive'  => true,
                                'IconValue'   => 'arrows-repeat',
                                'Color'       => 0x00ffb3
                            ],
                            [
                                'Value'       => ContainerState::Removing,
                                'Caption'     => 'Removing',
                                'IconActive'  => true,
                                'IconValue'   => 'skull',
                                'Color'       => 0xff0000
                            ],
                            [
                                'Value'       => ContainerState::Exited,
                                'Caption'     => 'Exited',
                                'IconActive'  => true,
                                'IconValue'   => 'stop',
                                'Color'       => 0xffae00
                            ],
                            [
                                'Value'       => ContainerState::Dead,
                                'Caption'     => 'Dead',
                                'IconActive'  => true,
                                'IconValue'   => 'skull',
                                'Color'       => 0xff0000
                            ]
                        ],
                    ],
                    \Portainer\IPSVarAction   => true

                ],
                self::StartedAt=> [
                    \Portainer\IPSVarName     => 'Started at',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION'   => VARIABLE_PRESENTATION_DATE_TIME,
                        'DATE'           => 1,
                        'MONTH_TEXT'     => false,
                        'DAY_OF_THE_WEEK'=> false,
                        'TIME'           => 2
                    ],
                    \Portainer\IPSVarAction   => false
                ],
                self::ExitCode=> [
                    \Portainer\IPSVarName     => 'Exit code',
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
                    \Portainer\IPSVarAction   => false
                ],
                self::OOMKilled=> [
                    \Portainer\IPSVarName     => 'Killed due to lack of memory',
                    \Portainer\IPSVarType     => VARIABLETYPE_BOOLEAN,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'ICON'         => 'memory',
                        'OPTIONS'      => [
                            [
                                'Value'       => false,
                                'Caption'     => 'no',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'ColorActive' => false,
                                'ColorValue'  => -1
                            ],
                            [
                                'Value'       => true,
                                'Caption'     => 'yes',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'ColorActive' => true,
                                'ColorValue'  => 0xff0000
                            ]
                        ]
                    ],
                    \Portainer\IPSVarAction   => false
                ],
                // Ident Health_Status
                self::Health=> [
                    self::Status=> [
                        \Portainer\IPSVarName     => 'Health status',
                        \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                        \Portainer\IPSPresentation=> [
                            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                            'ICON'         => 'heart-pulse',
                            'OPTIONS'      => [
                                [
                                    'Value'       => 'none',
                                    'Caption'     => 'No health check',
                                    'IconActive'  => false,
                                    'IconValue'   => '',
                                    'ColorActive' => false,
                                    'ColorValue'  => -1
                                ],
                                [
                                    'Value'       => 'starting',
                                    'Caption'     => 'Starting',
                                    'IconActive'  => false,
                                    'IconValue'   => '',
                                    'ColorActive' => true,
                                    'ColorValue'  => 0xffae00
                                ],
                                [
                                    'Value'       => 'healthy',
                                    'Caption'     => 'Healthy',
                                    'IconActive'  => false,
                                    'IconValue'   => '',
                                    'ColorActive' => true,
                                    'ColorValue'  => 0x00ff00
                                ],
                                [
                                    'Value'       => 'unhealthy',
                                    'Caption'     => 'Unhealthy',
                                    'IconActive'  => false,
                                    'IconValue'   => '',
                                    'ColorActive' => true,
                                    'ColorValue'  => 0xff0000
                                ]
                            ]
                        ],
                        \Portainer\IPSVarAction   => false
                    ]
                ]
            ]
        ];
    }
}

namespace Portainer\Stack
{
    /**
     * Status eines Stacks (Feld `Status` der Portainer API).
     */
    class StackStatus
    {
        public const Active = 1;
        public const Inactive = 2;
    }

    /**
     * Eigenschaften von Portainer Stack.
     */
    class Property
    {
        public const EnvironmentId = 'EnvironmentId';
        public const StackId = 'StackId';
        public const UpdateInterval = 'Interval';
    }

    /**
     * Idents und Definitionen der Statusvariablen.
     * $Config ist wie die Antwort der API verschachtelt, Idents darunter erhalten das Präfix der Gruppe.
     */
    class Variables
    {
        public const Name = 'Name';
        public const Status = 'Status';
        public const Type = 'Type';
        public const CreationDate = 'CreationDate';
        public const UpdateDate = 'UpdateDate';
        public static $Config = [
            self::Name=> [
                \Portainer\IPSVarName     => self::Name,
                \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                \Portainer\IPSPresentation=> [],
                \Portainer\IPSVarAction   => false
            ],
            self::Status=> [
                \Portainer\IPSVarName     => self::Status,
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                // Aufzählung, damit der Stack über die Variable gestartet und gestoppt werden kann
                \Portainer\IPSPresentation=> [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'ICON'         => 'layer-group',
                    'OPTIONS'      => [
                        [
                            'Value'      => StackStatus::Active,
                            'Caption'    => 'active',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => 0x00ff00
                        ],
                        [
                            'Value'      => StackStatus::Inactive,
                            'Caption'    => 'inactive',
                            'IconActive' => false,
                            'IconValue'  => '',
                            'Color'      => -1
                        ]
                    ]
                ],
                \Portainer\IPSVarAction   => true
            ],
            self::Type=> [
                \Portainer\IPSVarName     => self::Type,
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [
                    'PRESENTATION'    => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'ICON'            => '',
                    'COLOR'           => -1,
                    'PREFIX'          => '',
                    'SUFFIX'          => '',
                    'USAGE_TYPE'      => 0,
                    'PERCENTAGE'      => false,
                    'MIN'             => 1,
                    'MAX'             => 3,
                    'DIGITS'          => 0,
                    'INTERVALS_ACTIVE'=> true,
                    'INTERVALS'       => [
                        [
                            'IntervalMinValue'            => 1,
                            'IntervalMaxValue'            => 1,
                            'ConstantActive'              => true,
                            'ConstantValue'               => 'Docker Swarm Stack',
                            'ConversionFactor'            => 0,
                            'PrefixActive'                => false,
                            'PrefixValue'                 => '',
                            'SuffixActive'                => false,
                            'SuffixValue'                 => '',
                            'DigitsActive'                => false,
                            'DigitsValue'                 => 0,
                            'IconActive'                  => false,
                            'IconValue'                   => '',
                            'ColorActive'                 => false,
                            'ColorValue'                  => -1
                        ],
                        [
                            'IntervalMinValue'            => 2,
                            'IntervalMaxValue'            => 2,
                            'ConstantActive'              => true,
                            'ConstantValue'               => 'Docker Compose Stack',
                            'ConversionFactor'            => 0,
                            'PrefixActive'                => false,
                            'PrefixValue'                 => '',
                            'SuffixActive'                => false,
                            'SuffixValue'                 => '',
                            'DigitsActive'                => false,
                            'DigitsValue'                 => 0,
                            'IconActive'                  => false,
                            'IconValue'                   => '',
                            'ColorActive'                 => false,
                            'ColorValue'                  => -1
                        ],
                        [
                            'IntervalMinValue'            => 3,
                            'IntervalMaxValue'            => 3,
                            'ConstantActive'              => true,
                            'ConstantValue'               => 'Kubernetes Stack',
                            'ConversionFactor'            => 0,
                            'PrefixActive'                => false,
                            'PrefixValue'                 => '',
                            'SuffixActive'                => false,
                            'SuffixValue'                 => '',
                            'DigitsActive'                => false,
                            'DigitsValue'                 => 0,
                            'IconActive'                  => false,
                            'IconValue'                   => '',
                            'ColorActive'                 => false,
                            'ColorValue'                  => -1

                        ]
                    ]
                ],
                \Portainer\IPSVarAction   => false
            ],
            self::CreationDate=> [
                \Portainer\IPSVarName     => 'Creation date',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'           => 1,
                    'MONTH_TEXT'     => false,
                    'DAY_OF_THE_WEEK'=> false,
                    'TIME'           => 2
                ],
                \Portainer\IPSVarAction   => false
            ],
            self::UpdateDate=> [
                \Portainer\IPSVarName     => 'Update date',
                \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                \Portainer\IPSPresentation=> [
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'           => 1,
                    'MONTH_TEXT'     => false,
                    'DAY_OF_THE_WEEK'=> false,
                    'TIME'           => 2
                ],
                \Portainer\IPSVarAction   => false
            ],
        ];
    }
}
