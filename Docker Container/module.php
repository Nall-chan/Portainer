<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace DockerContainer {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerContainer {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerContainer {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerContainer {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/VariableHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * DockerContainer
 * Bildet einen Docker-Container in Portainer ab und kann ihn steuern.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 *
 * @property int $ParentID ID des IO (InstanceStatus-Helper)
 * @property bool $VariablesMaintained Variablen seit dem letzten ApplyChanges angelegt bzw. aktualisiert
 */
class DockerContainer extends IPSModuleStrict
{
    use \DockerContainer\BufferHelper;
    use \DockerContainer\DebugHelper;
    use \DockerContainer\InstanceStatus {
        \DockerContainer\InstanceStatus::MessageSink as IOMessageSink;
        \DockerContainer\InstanceStatus::RequestAction as IORequestAction;
    }
    use \DockerContainer\VariableHelper;
    use \Portainer\Variables;
    use \Portainer\Device;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\Docker\Container\Property::EnvironmentId, 0);
        $this->RegisterPropertyString(\Docker\Container\Property::ContainerName, '');
        $this->RegisterPropertyInteger(\Docker\Container\Property::UpdateInterval, 5);
        $this->RegisterTimer(\Portainer\Timer::UpdateInfo, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'' . \Portainer\Action::UpdateState . '\', 0);');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->ParentID = 0;
        $this->VariablesMaintained = false;
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        // ParentID wird nicht zurückgesetzt, RegisterParent benötigt den bisherigen Wert zum Abmelden der Nachrichten
        $this->VariablesMaintained = false;
        //Never delete this line!
        parent::ApplyChanges();
        $this->ApplyDeviceChanges($this->ReadPropertyInteger(\Docker\Container\Property::UpdateInterval));
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message == IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    /**
     * Fragt den Status des Containers ab und aktualisiert die Statusvariablen.
     *
     * @return bool true wenn die Statusvariablen aktualisiert wurden
     */
    public function RequestState(): bool
    {
        return $this->UpdateState(true);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        if ($Ident === \Portainer\Action::UpdateState) {
            $this->UpdateState(false);
            return;
        }
        // Bedienung aus der Visualisierung: Fehler dem Nutzer anzeigen
        $this->RunUserAction(function () use ($Ident, $Value): void
        {
            switch ($Ident) {
                case \Docker\Container\Variables::StatusIdent:
                    $this->SetContainerState((string) $Value);
                    break;
                default:
                    trigger_error(sprintf($this->Translate('Invalid ident: %s'), $Ident), E_USER_NOTICE);
            }
        });
    }

    /**
     * Startet den Container.
     *
     * @return bool true wenn Docker den Start angenommen hat (auch wenn der Container bereits läuft)
     */
    public function StartContainer(): bool
    {
        return $this->PostAction(\Portainer\Api\Url::DockerContainerStart);
    }

    /**
     * Stoppt den Container.
     *
     * @return bool true wenn Docker den Stopp angenommen hat (auch wenn der Container bereits gestoppt ist)
     */
    public function StopContainer(): bool
    {
        return $this->PostAction(\Portainer\Api\Url::DockerContainerStop);
    }

    /**
     * Pausiert den Container.
     *
     * @return bool true wenn Docker die Aktion angenommen hat
     */
    public function PauseContainer(): bool
    {
        return $this->PostAction(\Portainer\Api\Url::DockerContainerPause);
    }

    /**
     * Setzt einen pausierten Container fort.
     *
     * @return bool true wenn Docker die Aktion angenommen hat
     */
    public function UnpauseContainer(): bool
    {
        return $this->PostAction(\Portainer\Api\Url::DockerContainerUnpause);
    }

    /**
     * Startet den Container neu.
     *
     * @return bool true wenn Docker die Aktion angenommen hat
     */
    public function RestartContainer(): bool
    {
        return $this->PostAction(\Portainer\Api\Url::DockerContainerRestart);
    }

    /**
     * Beendet den Container zwangsweise (SIGKILL).
     *
     * @return bool true wenn Docker die Aktion angenommen hat
     */
    public function KillContainer(): bool
    {
        return $this->PostAction(\Portainer\Api\Url::DockerContainerKill);
    }

    /**
     * Rechnet Werte der Docker API für die Statusvariablen um.
     *
     * @param string $Key  Ident der Variable
     * @param mixed  $Data Wert aus der API
     */
    protected function AdjustSpecialValues(string $Key, mixed &$Data): void
    {
        switch ($Key) {
            case \Docker\Container\Variables::Name:
                $Data = ltrim((string) $Data, '/');
                break;
            case \Docker\Container\Variables::Created:
            case \Docker\Container\Variables::StartedAtIdent:
                $Data = $this->ConvertTimestamp((string) $Data);
                break;
        }
    }

    protected function IsConfigured(): bool
    {
        return ($this->ReadPropertyInteger(\Docker\Container\Property::EnvironmentId) > 0)
            && ($this->ReadPropertyString(\Docker\Container\Property::ContainerName) !== '');
    }

    protected function GetStateUri(): string
    {
        return $this->GetContainerUri(\Portainer\Api\Url::DockerContainerInspect);
    }

    protected function ProcessState(array $Data, bool $MaintainVariables): void
    {
        $Pos = 0;
        $this->SetStateVariables($Data, \Docker\Container\Variables::$Config, $Pos, '', [$this, 'AdjustSpecialValues'], $MaintainVariables);
    }

    /**
     * Liefert den Pfad der Docker API für diesen Container.
     *
     * @param string $ContainerUrl Eine der Konstanten \Portainer\Api\Url::DockerContainer*
     */
    private function GetContainerUri(string $ContainerUrl): string
    {
        return \Portainer\Api\Url::GetDockerContainerUrl(
            $this->ReadPropertyInteger(\Docker\Container\Property::EnvironmentId),
            $this->ReadPropertyString(\Docker\Container\Property::ContainerName),
            $ContainerUrl
        );
    }

    /**
     * Sendet eine Aktion an den Container. Die Statusvariablen werden danach neu abgefragt (SendAction).
     *
     * @param string $Uri Eine der Konstanten \Portainer\Api\Url::DockerContainer*
     *
     * @return bool true wenn Docker die Aktion angenommen hat
     */
    private function PostAction(string $Uri): bool
    {
        return $this->SendAction($this->GetContainerUri($Uri));
    }

    /**
     * Setzt den Zustand des Containers über die Statusvariable.
     *
     * @param string $State Gewünschter Zustand (\Docker\Container\ContainerState)
     */
    private function SetContainerState(string $State): void
    {
        switch ($State) {
            case \Docker\Container\ContainerState::Running:
                // Ein pausierter Container wird fortgesetzt, sonst gestartet
                if ($this->GetValue(\Docker\Container\Variables::StatusIdent) === \Docker\Container\ContainerState::Paused) {
                    $this->UnpauseContainer();
                } else {
                    $this->StartContainer();
                }
                break;
            case \Docker\Container\ContainerState::Paused:
                $this->PauseContainer();
                break;
            case \Docker\Container\ContainerState::Restarting:
                $this->RestartContainer();
                break;
            case \Docker\Container\ContainerState::Exited:
                $this->StopContainer();
                break;
            case \Docker\Container\ContainerState::Dead:
                $this->KillContainer();
                break;
            case \Docker\Container\ContainerState::Created:
                trigger_error($this->Translate('Creating a container is not supported.'), E_USER_NOTICE);
                break;
            case \Docker\Container\ContainerState::Removing:
                trigger_error($this->Translate('Removing a container is not supported.'), E_USER_NOTICE);
                break;
            default:
                trigger_error(sprintf($this->Translate('Invalid value: %s'), $State), E_USER_NOTICE);
        }
    }

    /**
     * Wandelt einen Zeitstempel der Docker API in Unix-Zeit um.
     * Docker liefert `0001-01-01T00:00:00Z`, wenn kein Zeitpunkt existiert (z.B. nie gestartet).
     * Daraus wird 0, das die Darstellung Datum/Uhrzeit als „-“ anzeigt.
     *
     * @param string $Value Zeitstempel im Format RFC 3339 (mit Nanosekunden)
     *
     * @return int Unix-Zeit oder 0
     */
    private function ConvertTimestamp(string $Value): int
    {
        try {
            $Timestamp = (new DateTime($Value))->getTimestamp();
        } catch (Exception $e) {
            $this->SendDebug('Invalid timestamp', $Value, 0);
            return 0;
        }
        return max(0, $Timestamp);
    }
}