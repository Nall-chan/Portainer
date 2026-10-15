<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace DockerSystem {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerSystem {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerSystem {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerSystem {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/VariableHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * DockerSystem
 * Bildet die Übersicht (Dashboard) eines Docker-Environments in Portainer ab.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 *
 * @property int $ParentID ID des IO (InstanceStatus-Helper)
 * @property bool $VariablesMaintained Variablen seit dem letzten ApplyChanges angelegt bzw. aktualisiert
 */
class DockerSystem extends IPSModuleStrict
{
    use \DockerSystem\BufferHelper;
    use \DockerSystem\DebugHelper;
    use \DockerSystem\InstanceStatus {
        \DockerSystem\InstanceStatus::MessageSink as IOMessageSink;
        \DockerSystem\InstanceStatus::RequestAction as IORequestAction;
    }
    use \DockerSystem\VariableHelper;
    use \Portainer\Variables;
    use \Portainer\Device;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\Docker\System\Property::EnvironmentId, 0);
        $this->RegisterPropertyInteger(\Docker\System\Property::UpdateInterval, 5);
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
        $this->ApplyDeviceChanges($this->ReadPropertyInteger(\Docker\System\Property::UpdateInterval));
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
        switch ($Ident) {
            case \Portainer\Action::UpdateState:
                $this->UpdateState(false);
                return;
        }
        trigger_error(sprintf($this->Translate('Invalid ident: %s'), $Ident), E_USER_NOTICE);
    }

    /**
     * Fragt den Status des Environments ab und aktualisiert die Statusvariablen.
     *
     * @return bool true wenn die Statusvariablen aktualisiert wurden
     */
    public function RequestState(): bool
    {
        return $this->UpdateState(true);
    }

    protected function IsConfigured(): bool
    {
        return $this->ReadPropertyInteger(\Docker\System\Property::EnvironmentId) > 0;
    }

    protected function GetStateUri(): string
    {
        return \Portainer\Api\Url::GetDashboardUrl($this->ReadPropertyInteger(\Docker\System\Property::EnvironmentId));
    }

    protected function ProcessState(array $Data, bool $MaintainVariables): void
    {
        $Pos = 0;
        $this->SetStateVariables($Data, \Docker\System\Variables::$Config, $Pos, '', null, $MaintainVariables);
    }
}
