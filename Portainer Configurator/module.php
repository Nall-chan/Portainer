<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PortainerConfigurator {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PortainerConfigurator {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class PortainerConfigurator extends IPSModuleStrict
{
    use \PortainerConfigurator\BufferHelper;
    use \PortainerConfigurator\DebugHelper;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
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
    }
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }

        if (!$this->HasActiveParent() || (IPS_GetInstance($this->InstanceID)['ConnectionID'] == 0)) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Instance has no active parent.'
                        ]
                    ]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $InstancesEnvironments = $this->GetEnvironmentIPSInstances();
        $AllInstancesContainers = $this->GetContainerIPSInstances();
        $Endpoints = $this->ListEndpoints();
        $EndpointsNames = [];
        $Values = [];
        foreach ($Endpoints as $Endpoint) {
            $InstanceIDEnvironment = array_search($Endpoint['Id'], $InstancesEnvironments);
            if ($InstanceIDEnvironment) {
                unset($InstancesEnvironments[$InstanceIDEnvironment]);
            }
            $EndpointsNames[$Endpoint['Id']] = $Endpoint['Name'];
            $Value = [
                'id'         => $Endpoint['Id'],
                'type'       => 'Environment',
                'name'       => $InstanceIDEnvironment ? IPS_GetName($InstanceIDEnvironment) : $Endpoint['Name'],
                'instanceID' => ($InstanceIDEnvironment ? $InstanceIDEnvironment : 0),
                'create'     => [
                    'moduleID'      => \Portainer\GUID::System,
                    'configuration' => [
                        \Docker\System\Property::EnvironmentId  => $Endpoint['Id'],
                        \Docker\System\Property::UpdateInterval => ($InstanceIDEnvironment ? IPS_GetProperty($InstanceIDEnvironment, \Docker\System\Property::UpdateInterval) : 5)
                    ]
                ]

            ];
            $Values[] = $Value;
            $InstancesContainers = $this->GetContainerIPSInstances($Endpoint['Id']);
            foreach ($Endpoint['Snapshots'][0]['DockerSnapshotRaw']['Containers'] as $Container) {
                $InstanceIDContainer = array_search(substr($Container['Names'][0], 1), $InstancesContainers);
                $ContainerValue['parent'] = $Endpoint['Id'];
                $ContainerValue['type'] = 'Container';
                $ContainerValue['instanceID'] = $InstanceIDContainer ? $InstanceIDContainer : 0;
                $ContainerValue['name'] = $InstanceIDContainer ? IPS_GetName($InstanceIDContainer) : substr($Container['Names'][0], 1); // remove leading slash
                $ContainerValue['create'] =
                    [
                        'moduleID'      => \Portainer\GUID::Container,
                        'location'      => [$Endpoint['Name']],
                        'configuration' => [
                            \Docker\Container\Property::EnvironmentId   => $Endpoint['Id'],
                            \Docker\Container\Property::ContainerName   => substr($Container['Names'][0], 1), // remove leading slash
                            \Docker\Container\Property::UpdateInterval  => ($InstanceIDContainer ? IPS_GetProperty($InstanceIDContainer, \Docker\Container\Property::UpdateInterval) : 5)
                        ]
                    ];
                $Values[] = $ContainerValue;
                if ($InstanceIDContainer) {
                    unset($AllInstancesContainers[$InstanceIDContainer]);
                    unset($InstancesContainers[$InstanceIDContainer]);
                }
            }
            // todo $InstancesContainers containers that do not exist in Portainer
        }

        $InstancesStacks = $this->GetStackIPSInstances();
        $ListStacks = $this->ListStacks();
        foreach ($ListStacks as $Stack) {
            $InstanceIDStack = array_search($Stack['Id'], $InstancesStacks);
            if ($InstanceIDStack) {
                unset($InstancesStacks[$InstanceIDStack]);
                $StackEndpointId = IPS_GetProperty($InstanceIDStack, \Portainer\Stack\Property::EnvironmentId);
                if ($StackEndpointId != $Stack['EndpointId']) {
                    $Value = [
                        'id'             => $Stack['Id'],
                        'type'           => 'Stack',
                        'name'           => IPS_GetName($InstanceIDStack),
                        'instanceID'     => $InstanceIDStack,
                    ];
                    if (isset($EndpointsNames[$Stack['EndpointId']])) {
                        $Value['parent'] = $Stack['EndpointId'];
                    }
                    $Values[] = $Value;
                    $InstanceIDStack = 0;

                }
            }
            $Values[] = [
                'id'             => $Stack['Id'],
                'parent'         => $Stack['EndpointId'],
                'type'           => 'Stack',
                'name'           => $InstanceIDStack ? IPS_GetName($InstanceIDStack) : $Stack['Name'],
                'instanceID'     => $InstanceIDStack ? $InstanceIDStack : 0,
                'create'         => [
                    'moduleID'      => \Portainer\GUID::Stack,
                    'location'      => [$EndpointsNames[$Stack['EndpointId']]],
                    'configuration' => [
                        \Portainer\Stack\Property::EnvironmentId  => $Stack['EndpointId'],
                        \Portainer\Stack\Property::StackId        => $Stack['Id'],
                        \Portainer\Stack\Property::UpdateInterval => ($InstanceIDStack ? IPS_GetProperty($InstanceIDStack, \Portainer\Stack\Property::UpdateInterval) : 5)
                    ]
                ]
            ];
        }
        // todo $InstancesStacks Stacks that do not exist in Portainer
        // todo $InstancesEnvironments Environments that do not exist in Portainer
        // todo $AllInstancesContainers containers that do not exist in Portainer
        $this->SendDebug('Values', $Values, 0);
        $Form['actions'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    private function ListEndpoints(): array
    {
        $this->SendDebug('ListEndpoints Get', \Portainer\Api\Url::ListEndpoints, 0);
        $Response = $this->SendDataToParent(json_encode(
            [
                'DataID' => \Portainer\GUID::SendToIO,
                'URI'    => \Portainer\Api\Url::ListEndpoints,
                'Method' => \Portainer\Api\HTTP::GET,
                'Data'   => []
            ]
        ));
        $this->SendDebug('ListEndpoints Result', $Response, 0);
        if ($Response !== false) {
            $Response = unserialize($Response);
            if ($Response !== false) {
                $this->SendDebug('ListEndpoints Data', $Response, 0);
                return $Response;
            }
        }
        return [];
    }
    private function ListStacks(): array
    {
        $this->SendDebug('ListStacks Get', \Portainer\Api\Url::ListStacks, 0);
        $Response = $this->SendDataToParent(json_encode(
            [
                'DataID' => \Portainer\GUID::SendToIO,
                'URI'    => \Portainer\Api\Url::ListStacks,
                'Method' => \Portainer\Api\HTTP::GET,
                'Data'   => []
            ]
        ));
        $this->SendDebug('ListStacks Result', $Response, 0);
        if ($Response !== false) {
            $Response = unserialize($Response);
            if ($Response !== false) {
                $this->SendDebug('ListStacks Data', $Response, 0);
                return $Response;
            }
        }
        return [];
    }
    private function GetEnvironmentIPSInstances(): array
    {
        $Environments = [];
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID(\Portainer\GUID::System), [$this, 'FilterInstancesByConnection']);
        foreach ($InstanceIDList as $InstanceID) {
            $Environments[$InstanceID] = @IPS_GetProperty($InstanceID, \Docker\System\Property::EnvironmentId);
        }
        return array_filter($Environments);
    }
    private function GetContainerIPSInstances(int $EnvironmentId = -1): array
    {
        $Environments = [];
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID(\Portainer\GUID::Container), [$this, 'FilterInstancesByConnection']);
        foreach ($InstanceIDList as $InstanceID) {
            if (($EnvironmentId != -1) && (IPS_GetProperty($InstanceID, \Docker\Container\Property::EnvironmentId) != $EnvironmentId)) {
                continue;
            }
            $Environments[$InstanceID] = @IPS_GetProperty($InstanceID, \Docker\Container\Property::ContainerName);
        }
        return array_filter($Environments);
    }
    private function GetStackIPSInstances(): array
    {
        $Stacks = [];
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID(\Portainer\GUID::Stack), [$this, 'FilterInstancesByConnection']);
        foreach ($InstanceIDList as $InstanceID) {
            $Stacks[$InstanceID] = @IPS_GetProperty($InstanceID, \Portainer\Stack\Property::StackId);
        }
        return array_filter($Stacks);
    }
    private function FilterInstancesByConnection(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID'];
    }
}