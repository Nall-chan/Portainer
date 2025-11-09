<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PortainerStack {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerStack {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class PortainerStack extends IPSModuleStrict
{
    use \PortainerStack\BufferHelper;
    use \PortainerStack\DebugHelper;
    use \Portainer\Variables;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\Portainer\Stack\Property::EnvironmentId, 0);
        $this->RegisterPropertyInteger(\Portainer\Stack\Property::StackId, 0);
        $this->RegisterPropertyInteger(\Portainer\Stack\Property::UpdateInterval, 0);
        $this->RegisterTimer(\Portainer\Stack\Timer::UpdateInfo, 0, 'PORTAINER_RequestState($_IPS[\'TARGET\']);');

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

        $Interval = $this->ReadPropertyInteger(\Portainer\Stack\Property::UpdateInterval) * 1000;
        $this->SetTimerInterval(\Portainer\Stack\Timer::UpdateInfo, $Interval);
        if ($this->HasActiveParent()) {
            $this->RequestState();
        }
    }

    public function RequestState(): bool
    {
        $Result = $this->FetchData();
        if (is_array($Result)) {
            $this->SetStateVariables($Result, \Portainer\Stack\Variables::$Config);
            return true;
        }
        return false;
    }

    public function StopStack(): bool
    {
        $Uri = \Portainer\Api\url::GetStartStopUrl($this->ReadPropertyInteger(\Portainer\Stack\Property::EnvironmentId), $this->ReadPropertyInteger(\Portainer\Stack\Property::StackId), \Portainer\Api\Url::StopStack);
        $this->SendDebug('StopStack', $Uri, 0);
        $Response = $this->SendDataToParent(json_encode(
            [
                'DataID' => \Portainer\GUID::SendToIO,
                'URI'    => $Uri,
                'Method' => \Portainer\Api\HTTP::POST,
                'Data'   => [],
                'Timeout'=> 10000
            ]
        ));
        $this->SendDebug('StopStack Result', $Response, 0);
        if ($Response !== false) {
            $Response = unserialize($Response);
            if (is_array($Response)) {
                return true;
            }
        }
        return false;
    }

    public function StartStack(): bool
    {
        $Uri = \Portainer\Api\url::GetStartStopUrl($this->ReadPropertyInteger(\Portainer\Stack\Property::EnvironmentId), $this->ReadPropertyInteger(\Portainer\Stack\Property::StackId), \Portainer\Api\Url::StartStack);
        $this->SendDebug('StartStack', $Uri, 0);
        $Response = $this->SendDataToParent(json_encode(
            [
                'DataID' => \Portainer\GUID::SendToIO,
                'URI'    => $Uri,
                'Method' => \Portainer\Api\HTTP::POST,
                'Data'   => [],
                'Timeout'=> 10000
            ]
        ));
        $this->SendDebug('StartStack Result', $Response, 0);
        if ($Response !== false) {
            $Response = unserialize($Response);
            if (is_array($Response)) {
                return true;
            }
        }
        return false;

    }

    private function FetchData(): bool|array
    {
        $Uri = \Portainer\Api\url::GetStackUrl($this->ReadPropertyInteger(\Portainer\Stack\Property::StackId));
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