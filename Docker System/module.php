<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace DockerSystem {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace DockerSystem {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class DockerSystem extends IPSModuleStrict
{
    use \DockerSystem\BufferHelper;
    use \DockerSystem\DebugHelper;
    use \Portainer\Variables;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\Docker\System\Property::EnvironmentId, 0);
        $this->RegisterPropertyInteger(\Docker\System\Property::UpdateInterval, 0);
        $this->RegisterTimer(\Docker\System\Timer::UpdateInfo, 0, 'PORTAINER_RequestState($_IPS[\'TARGET\']);');
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
        $Interval = $this->ReadPropertyInteger(\Docker\System\Property::UpdateInterval) * 1000;
        $this->SetTimerInterval(\Docker\System\Timer::UpdateInfo, $Interval);
        if ($this->HasActiveParent()) {
            $this->RequestState();
        }
    }

    public function RequestState(): bool
    {
        $Result = $this->FetchData();
        if (is_array($Result)) {
            $this->SetStateVariables($Result, \Docker\System\Variables::$Config);
            return true;
        }
        return false;
    }

    private function FetchData(): bool|array
    {
        $Uri = \Portainer\Api\url::GetDashboardUrl($this->ReadPropertyInteger(\Docker\System\Property::EnvironmentId));
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
}