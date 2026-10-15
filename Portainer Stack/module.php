<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PortainerStack {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerStack {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerStack {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerStack {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/VariableHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * PortainerStack
 * Bildet einen Stack in Portainer ab und kann ihn starten und stoppen.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 *
 * @property int $ParentID ID des IO (InstanceStatus-Helper)
 * @property bool $VariablesMaintained Variablen seit dem letzten ApplyChanges angelegt bzw. aktualisiert
 */
class PortainerStack extends IPSModuleStrict
{
    use \PortainerStack\BufferHelper;
    use \PortainerStack\DebugHelper;
    use \PortainerStack\InstanceStatus {
        \PortainerStack\InstanceStatus::MessageSink as IOMessageSink;
        \PortainerStack\InstanceStatus::RequestAction as IORequestAction;
    }
    use \PortainerStack\VariableHelper;
    use \Portainer\Variables;
    use \Portainer\Device;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\Portainer\Stack\Property::EnvironmentId, 0);
        $this->RegisterPropertyInteger(\Portainer\Stack\Property::StackId, 0);
        $this->RegisterPropertyInteger(\Portainer\Stack\Property::UpdateInterval, 5);
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
        $this->ApplyDeviceChanges($this->ReadPropertyInteger(\Portainer\Stack\Property::UpdateInterval));
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message == IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
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
                case \Portainer\Stack\Variables::Status:
                    $this->SetStackStatus((int) $Value);
                    break;
                default:
                    trigger_error(sprintf($this->Translate('Invalid ident: %s'), $Ident), E_USER_NOTICE);
            }
        });
    }

    /**
     * Fragt den Status des Stacks ab und aktualisiert die Statusvariablen.
     *
     * @return bool true wenn die Statusvariablen aktualisiert wurden
     */
    public function RequestState(): bool
    {
        return $this->UpdateState(true);
    }

    /**
     * Stoppt den Stack.
     *
     * @return bool true wenn Portainer den Stack gestoppt hat
     */
    public function StopStack(): bool
    {
        return $this->SendAction($this->GetStartStopUri(\Portainer\Api\Url::StopStack), \Portainer\Api\HTTP::POST, 10000);
    }

    /**
     * Startet den Stack.
     *
     * @return bool true wenn Portainer den Stack gestartet hat
     */
    public function StartStack(): bool
    {
        return $this->SendAction($this->GetStartStopUri(\Portainer\Api\Url::StartStack), \Portainer\Api\HTTP::POST, 10000);
    }

    protected function IsConfigured(): bool
    {
        return ($this->ReadPropertyInteger(\Portainer\Stack\Property::EnvironmentId) > 0)
            && ($this->ReadPropertyInteger(\Portainer\Stack\Property::StackId) > 0);
    }

    protected function GetStateUri(): string
    {
        return \Portainer\Api\Url::GetStackUrl($this->ReadPropertyInteger(\Portainer\Stack\Property::StackId));
    }

    protected function ProcessState(array $Data, bool $MaintainVariables): void
    {
        $Pos = 0;
        $this->SetStateVariables($Data, \Portainer\Stack\Variables::$Config, $Pos, '', null, $MaintainVariables);
    }

    /**
     * Startet oder stoppt den Stack über die Statusvariable.
     *
     * @param int $Status Gewünschter Status (\Portainer\Stack\StackStatus)
     */
    private function SetStackStatus(int $Status): void
    {
        switch ($Status) {
            case \Portainer\Stack\StackStatus::Active:
                $this->StartStack();
                break;
            case \Portainer\Stack\StackStatus::Inactive:
                $this->StopStack();
                break;
            default:
                trigger_error(sprintf($this->Translate('Invalid value: %s'), $Status), E_USER_NOTICE);
        }
    }

    /**
     * Liefert den Pfad für Start bzw. Stopp des Stacks.
     *
     * @param string $StartStopUrl \Portainer\Api\Url::StartStack oder ::StopStack
     */
    private function GetStartStopUri(string $StartStopUrl): string
    {
        return \Portainer\Api\Url::GetStartStopUrl(
            $this->ReadPropertyInteger(\Portainer\Stack\Property::EnvironmentId),
            $this->ReadPropertyInteger(\Portainer\Stack\Property::StackId),
            $StartStopUrl
        );
    }
}
