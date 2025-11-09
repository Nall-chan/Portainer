<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace DockerContainer {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerContainer {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class DockerContainer extends IPSModuleStrict
{
    use \DockerContainer\BufferHelper;
    use \DockerContainer\DebugHelper;
    use \Portainer\Variables;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\Docker\Container\Property::EnvironmentId, 0);
        $this->RegisterPropertyString(\Docker\Container\Property::ContainerName, '');
        $this->RegisterPropertyInteger(\Docker\Container\Property::UpdateInterval, 5);
        $this->RegisterTimer(\Docker\Container\Timer::UpdateInfo, 0, 'PORTAINER_RequestState($_IPS[\'TARGET\']);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->ReadPropertyString(\Docker\Container\Property::ContainerName) != '') {
            $Interval = $this->ReadPropertyInteger(\Docker\Container\Property::UpdateInterval) * 1000;
            $this->SetTimerInterval(\Docker\Container\Timer::UpdateInfo, $Interval);
            if ($this->HasActiveParent()) {
                $this->RequestState();
            }

        }
    }

    public function RequestState(): bool
    {
        if ($this->ReadPropertyString(\Docker\Container\Property::ContainerName) == '') {
            echo 'Container name is empty.';
            return false;
        }
        $Result = $this->FetchData();
        if (is_array($Result)) {
            $pos = 0;
            $this->SetStateVariables($Result, \Docker\Container\Variables::$Config, $pos, '', [$this, 'AdjustSpecialValues']);
            return true;
        }
        return false;
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'State_' . \Docker\Container\Variables::Status:
                $OldState = $this->GetValue('State_' . \Docker\Container\Variables::Status);
                switch ($Value) {
                    case 'created':
                        echo 'Creating container is not supported.';
                        return;
                    case 'running':
                        if ($OldState == 'paused') {
                            $this->UnpauseContainer();
                        } else {
                            $this->StartContainer();
                        }
                        break;
                    case 'paused':
                        $this->PauseContainer();
                        break;
                    case 'restarting':
                        $this->PauseContainer();
                        break;
                    case 'removing':
                        echo 'Removing container is not supported.';
                        return;
                    case 'exited':
                        $this->StopContainer();
                        break;
                    case 'dead':
                        $this->KillContainer();
                        break;
                    default:
                        throw new Exception('Invalid Value for Status Action');
                }
                break;
            default:
                throw new Exception('Invalid Ident');
        }
    }

    public function StartContainer(): bool
    {
        $Uri = \Portainer\Api\Url::DockerContainerStart;
        return $this->PostAction($Uri);
    }

    public function StopContainer(): bool
    {
        $Uri = \Portainer\Api\Url::DockerContainerStop;
        return $this->PostAction($Uri);
    }

    public function PauseContainer(): bool
    {
        $Uri = \Portainer\Api\Url::DockerContainerPause;
        return $this->PostAction($Uri);
    }

    public function UnpauseContainer(): bool
    {
        $Uri = \Portainer\Api\Url::DockerContainerUnpause;
        return $this->PostAction($Uri);
    }

    public function RestartContainer(): bool
    {
        $Uri = \Portainer\Api\Url::DockerContainerRestart;
        return $this->PostAction($Uri);
    }

    public function KillContainer(): bool
    {
        $Uri = \Portainer\Api\Url::DockerContainerKill;
        return $this->PostAction($Uri);
    }

    protected function AdjustSpecialValues(string $Key, mixed &$Data): void
    {
        switch ($Key) {
            case \Docker\Container\Variables::Name:
                $Data = ltrim($Data, '/');
                break;
            case \Docker\Container\Variables::Created:
            case 'State_' . \Docker\Container\Variables::StartedAt:
                $Data = (new DateTime($Data))->getTimestamp();
                break;
        }
    }

    private function FetchData(): bool|array
    {
        $Uri = \Portainer\Api\url::GetDockerContainerUrl($this->ReadPropertyInteger(\Docker\Container\Property::EnvironmentId), $this->ReadPropertyString(\Docker\Container\Property::ContainerName), \Portainer\Api\Url::DockerContainerInspect);
        $this->SendDebug('FetchData', $Uri, 0);
        $Response = $this->SendDataToParent(json_encode(
            [
                'DataID' => \Portainer\GUID::SendToIO,
                'URI'    => $Uri,
                'Method' => \Portainer\Api\HTTP::GET,
                'Data'   => []
            ]
        ));
        $this->SendDebug('FetchData Result', $Response, 0);
        if ($Response !== false) {
            $Response = unserialize($Response);
            $this->SendDebug('FetchData Data', $Response, 0);
            return $Response;
        }
        return [];
    }

    private function PostAction(string $Uri): bool
    {

        $Uri = \Portainer\Api\url::GetDockerContainerUrl($this->ReadPropertyInteger(\Docker\Container\Property::EnvironmentId), $this->ReadPropertyString(\Docker\Container\Property::ContainerName), $Uri);
        $this->SendDebug('PostAction', $Uri, 0);
        $Response = $this->SendDataToParent(json_encode(
            [
                'DataID' => \Portainer\GUID::SendToIO,
                'URI'    => $Uri,
                'Method' => \Portainer\Api\HTTP::POST,
                'Data'   => []
            ]
        ));
        $this->SendDebug('PostAction Result', $Response, 0);
        if ($Response !== false) {
            $Response = unserialize($Response);
            if (is_bool($Response)) {
                IPS_RunScriptText('IPS_Sleep(1000);PORTAINER_RequestState(' . $this->InstanceID . ');');
                return $Response;
            }
        }
        return false;
    }
}