<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PortainerConfigurator {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/PortainerLib.php';

/**
 * PortainerConfigurator
 * Listet Environments, Container und Stacks von Portainer und gleicht sie mit den Instanzen in Symcon ab.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class PortainerConfigurator extends IPSModuleStrict
{
    use \PortainerConfigurator\DebugHelper;
    use \Portainer\SendToIO;

    /** Versatz der Zeilen-IDs für Stacks, damit sie nicht mit den IDs der Environments kollidieren */
    private const StackRowOffset = 1000000;
    /** Standard-Intervall neuer Instanzen in Sekunden */
    private const DefaultInterval = 5;

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
        if (!$this->HasActiveParent()) {
            $this->AddPopupAlert($Form, [$this->Translate('Instance has no active parent.')]);
            return json_encode($Form);
        }
        $Errors = [];
        $Endpoints = $this->RequestList(\Portainer\Api\Url::ListEndpoints, $Errors);
        $Stacks = $this->RequestList(\Portainer\Api\Url::ListStacks, $Errors);

        $InstancesEnvironments = $this->GetEnvironmentIPSInstances();
        $InstancesContainers = $this->GetContainerIPSInstances();
        $InstancesStacks = $this->GetStackIPSInstances();
        $EndpointNames = [];
        $UsedRowIds = [];
        $Values = [];

        // Environments mit ihren Containern
        foreach ($Endpoints as $Endpoint) {
            if (!isset($Endpoint['Id'])) {
                continue;
            }
            $EndpointId = (int) $Endpoint['Id'];
            $EndpointName = (string) ($Endpoint['Name'] ?? $EndpointId);
            $EndpointNames[$EndpointId] = $EndpointName;
            $IsDocker = \Portainer\Api\Endpoint::IsDocker((int) ($Endpoint['Type'] ?? 0));
            $InstanceID = array_search($EndpointId, $InstancesEnvironments, true);
            if ($InstanceID !== false) {
                unset($InstancesEnvironments[$InstanceID]);
            }
            $Row = [
                'id'          => $EndpointId,
                'expanded'    => true,
                'portainerId' => $EndpointId,
                'type'        => $this->Translate($IsDocker ? 'Environment' : 'Environment (not supported)'),
                'name'        => ($InstanceID !== false) ? IPS_GetName($InstanceID) : $EndpointName,
                'instanceID'  => ($InstanceID !== false) ? $InstanceID : 0
            ];
            // Dashboard und Container gibt es nur bei Docker-Environments
            if ($IsDocker) {
                $Row['create'] = [
                    'moduleID'      => \Portainer\GUID::System,
                    'configuration' => [
                        \Docker\System\Property::EnvironmentId  => $EndpointId,
                        \Docker\System\Property::UpdateInterval => $this->GetInterval($InstanceID, \Docker\System\Property::UpdateInterval)
                    ]
                ];
            }
            $Values[] = $Row;
            $UsedRowIds[$EndpointId] = true;
            if (!$IsDocker) {
                continue;
            }
            foreach ($this->GetContainers($Endpoint, $Errors) as $Container) {
                // Docker liefert Namen mit führendem Slash
                $ContainerName = ltrim((string) ($Container['Names'][0] ?? ''), '/');
                if ($ContainerName === '') {
                    continue;
                }
                $InstanceID = $this->FindContainerInstance($InstancesContainers, $EndpointId, $ContainerName);
                if ($InstanceID !== false) {
                    unset($InstancesContainers[$InstanceID]);
                }
                $Values[] = [
                    'parent'     => $EndpointId,
                    'type'       => $this->Translate('Container'),
                    'name'       => ($InstanceID !== false) ? IPS_GetName($InstanceID) : $ContainerName,
                    'instanceID' => ($InstanceID !== false) ? $InstanceID : 0,
                    'create'     => [
                        'moduleID'      => \Portainer\GUID::Container,
                        'location'      => [$EndpointName],
                        'configuration' => [
                            \Docker\Container\Property::EnvironmentId  => $EndpointId,
                            \Docker\Container\Property::ContainerName  => $ContainerName,
                            \Docker\Container\Property::UpdateInterval => $this->GetInterval($InstanceID, \Docker\Container\Property::UpdateInterval)
                        ]
                    ]
                ];
            }
        }

        // System-Instanzen ohne Environment in Portainer
        foreach ($InstancesEnvironments as $InstanceID => $EnvironmentId) {
            $Row = [
                'expanded'    => true,
                'portainerId' => $EnvironmentId,
                'type'        => $this->Translate('Environment'),
                'instanceID'  => $InstanceID,
                'name'        => IPS_GetName($InstanceID)
            ];
            if (!isset($UsedRowIds[$EnvironmentId])) {
                $Row['id'] = $EnvironmentId;
                $UsedRowIds[$EnvironmentId] = true;
                $EndpointNames[$EnvironmentId] = IPS_GetName($InstanceID);
            }
            $Values[] = $Row;
        }

        // Container-Instanzen ohne Container in Portainer
        foreach ($InstancesContainers as $InstanceID => $Container) {
            $Row = [
                'type'       => $this->Translate('Container'),
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID)
            ];
            if (isset($UsedRowIds[$Container[\Docker\Container\Property::EnvironmentId]])) {
                $Row['parent'] = $Container[\Docker\Container\Property::EnvironmentId];
            }
            $Values[] = $Row;
        }

        // Stacks
        foreach ($Stacks as $Stack) {
            if (!isset($Stack['Id'])) {
                continue;
            }
            $StackId = (int) $Stack['Id'];
            $StackEndpointId = (int) ($Stack['EndpointId'] ?? 0);
            $InstanceID = array_search($StackId, $InstancesStacks, true);
            // Eine Instanz mit anderem Environment bleibt als verwaiste Instanz in der Liste
            if (($InstanceID !== false) && (IPS_GetProperty($InstanceID, \Portainer\Stack\Property::EnvironmentId) === $StackEndpointId)) {
                unset($InstancesStacks[$InstanceID]);
            } else {
                $InstanceID = false;
            }
            $Row = [
                'id'          => self::StackRowOffset + $StackId,
                'portainerId' => $StackId,
                'type'        => $this->Translate('Stack'),
                'name'        => ($InstanceID !== false) ? IPS_GetName($InstanceID) : (string) ($Stack['Name'] ?? $StackId),
                'instanceID'  => ($InstanceID !== false) ? $InstanceID : 0,
                'create'      => [
                    'moduleID'      => \Portainer\GUID::Stack,
                    'configuration' => [
                        \Portainer\Stack\Property::EnvironmentId  => $StackEndpointId,
                        \Portainer\Stack\Property::StackId        => $StackId,
                        \Portainer\Stack\Property::UpdateInterval => $this->GetInterval($InstanceID, \Portainer\Stack\Property::UpdateInterval)
                    ]
                ]
            ];
            if (isset($EndpointNames[$StackEndpointId])) {
                $Row['create']['location'] = [$EndpointNames[$StackEndpointId]];
            }
            if (isset($UsedRowIds[$StackEndpointId])) {
                $Row['parent'] = $StackEndpointId;
            }
            $Values[] = $Row;
        }

        // Stack-Instanzen ohne Stack in Portainer bzw. mit anderem Environment
        foreach ($InstancesStacks as $InstanceID => $StackId) {
            $EnvironmentId = IPS_GetProperty($InstanceID, \Portainer\Stack\Property::EnvironmentId);
            $Row = [
                'portainerId' => $StackId,
                'type'        => $this->Translate('Stack'),
                'instanceID'  => $InstanceID,
                'name'        => IPS_GetName($InstanceID)
            ];
            if (isset($UsedRowIds[$EnvironmentId])) {
                $Row['parent'] = $EnvironmentId;
            }
            $Values[] = $Row;
        }

        $this->SendDebug('Values', $Values, 0);
        $Form['actions'][0]['values'] = $Values;
        if (count($Errors)) {
            $this->AddPopupAlert($Form, $Errors);
        }
        return json_encode($Form);
    }

    /**
     * Fragt eine Liste bei Portainer ab.
     *
     * @param string $Uri    Pfad unterhalb von `/api`
     * @param array  $Errors Fehlermeldungen für das Formular
     *
     * @return array Liste oder leeres Array bei Fehler
     */
    private function RequestList(string $Uri, array &$Errors): array
    {
        $Response = $this->SendToIO($Uri);
        if (is_array($Response[\Portainer\IOResponse::Result])) {
            return $Response[\Portainer\IOResponse::Result];
        }
        $Errors[] = ($Response[\Portainer\IOResponse::Error] !== '')
            ? $Response[\Portainer\IOResponse::Error]
            : $this->Translate('Could not read data from Portainer.');
        return [];
    }

    /**
     * Liefert die Container eines Docker-Environments.
     * Live abgefragt, sonst aus dem Snapshot von Portainer (bis zu 5 Minuten alt).
     *
     * @param array $Endpoint Daten des Endpoints
     * @param array $Errors   Fehlermeldungen für das Formular
     *
     * @return array Container im Format von `/containers/json`
     */
    private function GetContainers(array $Endpoint, array &$Errors): array
    {
        if (\Portainer\Api\Endpoint::CanQueryLive($Endpoint)) {
            $Response = $this->SendToIO(\Portainer\Api\Url::ListDockerContainers((int) $Endpoint['Id']));
            if (is_array($Response[\Portainer\IOResponse::Result])) {
                return $Response[\Portainer\IOResponse::Result];
            }
            $this->SendDebug('Containers', 'Live request failed, using snapshot', 0);
        }
        $Containers = $Endpoint['Snapshots'][0]['DockerSnapshotRaw']['Containers'] ?? null;
        if (is_array($Containers)) {
            return $Containers;
        }
        $Errors[] = sprintf($this->Translate('Containers of environment %s are not available.'), (string) ($Endpoint['Name'] ?? $Endpoint['Id']));
        return [];
    }

    /**
     * Fügt dem Formular eine Meldung als Popup hinzu.
     *
     * @param array    $Form     Formular
     * @param string[] $Messages Meldungen
     */
    private function AddPopupAlert(array &$Form, array $Messages): void
    {
        $Items = [];
        foreach ($Messages as $Message) {
            $Items[] = ['type' => 'Label', 'caption' => $Message];
        }
        $Form['actions'][] = [
            'type'  => 'PopupAlert',
            'popup' => ['items' => $Items]
        ];
    }

    /**
     * Liefert das Intervall einer vorhandenen Instanz, damit der Konfigurator sie nicht als abweichend anzeigt.
     *
     * @param int|false $InstanceID Instanz oder false
     * @param string    $Property   Name der Eigenschaft
     */
    private function GetInterval(int|false $InstanceID, string $Property): int
    {
        return ($InstanceID !== false) ? (int) IPS_GetProperty($InstanceID, $Property) : self::DefaultInterval;
    }

    /**
     * Sucht die Instanz eines Containers.
     *
     * @param array  $Instances     Ergebnis von GetContainerIPSInstances
     * @param int    $EnvironmentId Environment des Containers
     * @param string $ContainerName Name des Containers
     *
     * @return int|false Instanz-ID oder false
     */
    private function FindContainerInstance(array $Instances, int $EnvironmentId, string $ContainerName): int|false
    {
        foreach ($Instances as $InstanceID => $Container) {
            if (($Container[\Docker\Container\Property::EnvironmentId] === $EnvironmentId)
                && ($Container[\Docker\Container\Property::ContainerName] === $ContainerName)) {
                return $InstanceID;
            }
        }
        return false;
    }

    /**
     * Liefert die Docker-System-Instanzen an diesem IO.
     *
     * @return array [InstanzID => EnvironmentId]
     */
    private function GetEnvironmentIPSInstances(): array
    {
        $Environments = [];
        foreach ($this->GetInstancesOfModule(\Portainer\GUID::System) as $InstanceID) {
            $Environments[$InstanceID] = (int) IPS_GetProperty($InstanceID, \Docker\System\Property::EnvironmentId);
        }
        return array_filter($Environments);
    }

    /**
     * Liefert die Docker-Container-Instanzen an diesem IO.
     *
     * @return array [InstanzID => [EnvironmentId => int, ContainerName => string]]
     */
    private function GetContainerIPSInstances(): array
    {
        $Containers = [];
        foreach ($this->GetInstancesOfModule(\Portainer\GUID::Container) as $InstanceID) {
            $ContainerName = (string) IPS_GetProperty($InstanceID, \Docker\Container\Property::ContainerName);
            if ($ContainerName === '') {
                continue;
            }
            $Containers[$InstanceID] = [
                \Docker\Container\Property::EnvironmentId => (int) IPS_GetProperty($InstanceID, \Docker\Container\Property::EnvironmentId),
                \Docker\Container\Property::ContainerName => $ContainerName
            ];
        }
        return $Containers;
    }

    /**
     * Liefert die Stack-Instanzen an diesem IO.
     *
     * @return array [InstanzID => StackId]
     */
    private function GetStackIPSInstances(): array
    {
        $Stacks = [];
        foreach ($this->GetInstancesOfModule(\Portainer\GUID::Stack) as $InstanceID) {
            $Stacks[$InstanceID] = (int) IPS_GetProperty($InstanceID, \Portainer\Stack\Property::StackId);
        }
        return array_filter($Stacks);
    }

    /**
     * Liefert die Instanzen eines Moduls, die am selben IO wie dieser Konfigurator hängen.
     *
     * @param string $ModuleID GUID des Moduls
     *
     * @return int[] Instanz-IDs
     */
    private function GetInstancesOfModule(string $ModuleID): array
    {
        $ConnectionID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        return array_filter(
            IPS_GetInstanceListByModuleID($ModuleID),
            static fn (int $InstanceID): bool => IPS_GetInstance($InstanceID)['ConnectionID'] == $ConnectionID
        );
    }
}
