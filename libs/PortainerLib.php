<?php

declare(strict_types=1);

/**
 * PortainerLib
 * Enthält Klassen welche die API und Geräte Fähigkeiten abbilden.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.0
 */

namespace Portainer\Api
{
    /*
    const ErrorCode = 'errorCode';
    const Message = 'msg';
    const Result = 'result';
     */
    class HTTP
    {
        public const GET = 'GET';
        public const PUT = 'PUT';
        public const POST = 'POST';
        public const DELETE = 'DELETE';
    }
    class Url
    {
        public const Auth = '/auth';
        public const Info = '/info';
        public const Version = '/version';
        public const Events = '/events';
        public const Ping = '/_ping';
        public const ListStacks = '/stacks';
        public const GetStack = '/stacks/%d';
        public const StartStack = '/start?endpointId=%d';
        public const StopStack = '/stop?endpointId=%d';
        public const ListEndpoints = '/endpoints';
        public const Endpoint = '/endpoints/%d';
        public const Dashboard = '/docker/%d/dashboard';
        public const ListDockerContainers = '/docker/containers/json';
        public const DockerContainerStart = '/docker/containers/%s/start';
        public const DockerContainerStop = '/docker/containers/%s/stop';
        public const DockerContainerPause = '/docker/containers/%s/pause';
        public const DockerContainerUnpause = '/docker/containers/%s/unpause';
        public const DockerContainerRestart = '/docker/containers/%s/restart';
        public const DockerContainerKill = '/docker/containers/%s/kill';
        public const DockerContainerInspect = '/docker/containers/%s/json';

        public static function GetStackUrl(int $StackId): string
        {
            return sprintf(self::GetStack, $StackId);
        }
        public static function GetStartStopUrl(int $EndpointId, int $StackId, string $StartStopUrl): string
        {
            return self::GetStackUrl($StackId) . sprintf($StartStopUrl, $EndpointId);
        }
        public static function GetEndpointUrl(int $EndpointId): string
        {
            return sprintf(self::Endpoint, $EndpointId);
        }
        public static function GetDashboardUrl(int $EndpointId): string
        {
            return sprintf(self::Dashboard, $EndpointId);
        }

        public static function ListDockerContainers(int $EndpointId): string
        {
            return self::GetEndpointUrl($EndpointId) . self::ListDockerContainers;
        }
        public static function GetDockerContainerUrl(int $EndpointId, string $ContainerId, string $ContainerUrl): string
        {
            return self::GetEndpointUrl($EndpointId) . sprintf($ContainerUrl, $ContainerId);
        }

    }

    /*
    class Method
    {
        // Connection
        public const Handshake = 'handshake';
        public const Login = 'login_device';

        public const MultipleRequest = 'multipleRequest';

        // Get/Set Values
        public const GetDeviceInfo = 'get_device_info';
        public const SetDeviceInfo = 'set_device_info';

        // Get/Set Time
        public const GetDeviceTime = 'get_device_time';
        public const SetDeviceTime = 'set_device_time';

        // Get Energy Values
        public const GetCurrentPower = 'get_current_power';
        public const GetEnergyUsage = 'get_energy_usage';

        // not used (now)
        public const GetDeviceUsage = 'get_device_usage';
        public const SetLightingEffect = 'set_lighting_effect';

        // not working :(
        //public const Reboot = 'reboot';
        //public const SetRelayState = 'set_relay_state'; // 'state' => int
        //public const SetLedOff = 'set_led_off'; //array 'off'=>int
        //public const GetLightState = 'get_light_state';

        // Control Child
        public const GetChildDeviceList = 'get_child_device_list';
        public const GetChildDeviceComponentList = 'get_child_device_component_list';
        public const ControlChild = 'control_child';

        //get_child_device_component_list

        public const CountdownRule = 'add_countdown_rule'; // todo wie löschen?
    }

    class Param
    {
        public const Username = 'username';
        public const Password = 'password';
    }

    class Result
    {
        public const Nickname = 'nickname';
        public const Response = 'response';
        public const EncryptedKey = 'key';
        public const Ip = 'ip';
        public const Mac = 'mac';
        public const DeviceType = 'device_type';
        public const Type = 'type';
        public const DeviceModel = 'device_model';
        public const Model = 'model';
        public const DeviceID = 'device_id';
        public const MGT = 'mgt_encrypt_schm';
        public const Protocol = 'encrypt_type';
        public const ChildList = 'child_device_list';
        public const Position = 'position';
        public const SlotNumber = 'slot_number';
        public const ResponseData = 'responseData';
        public const Category = 'category';
    }
     */
    class Protocol
    {
        /*        public const Method = 'method';
                public const Params = 'params';
                private const ParamHandshakeKey = 'key';
                private const DiscoveryKey = 'rsa_key';
                private const requestTimeMils = 'requestTimeMils';
                private const TerminalUUID = 'terminalUUID';
         */
        /*
            public static $ErrorCodes = [
                0      => 'Success',
                -1001  => 'Invalid parameter',
                -1005	 => 'Operation forbidden',
                -44106 => 'The client id or client secret is invalid',
                -44107 => 'The response type is invalid',
                -44108	=> 'The CSRF token is invalid',
                -44109	=> 'The session id is invalid',
                -44110	=> 'The auth code has expired',
                -44111	=> 'The grant type is invalid',
                -44112	=> 'The access token has expired. Please re-initiate the refreshToken process to obtain the access token.',
                -44113	=> 'The access token is Invalid',
                -44114	=> 'The refresh token has expired. Please re-initiate the authentication process to obtain the refresh token.',
                -44116	=> 'Open API authorized failed, please check whether the input parameters are legal.',
                -44118	=> 'This interface only supports the authorization code mode, not the client credentials mode.'
            ];
         */
        /*
                public static function BuildHandshakeRequest(string $publicKey): string
                {
                    return json_encode([
                        self::Method=> Method::Handshake,
                        self::Params=> [
                            self::ParamHandshakeKey          => mb_convert_encoding($publicKey, 'ISO-8859-1', 'UTF-8')
                        ],
                        self::requestTimeMils => 0

                    ]);
                }

                public static function BuildRequest(string $Method, string $TerminalUUID = '', array $Params = []): array
                {
                    $Request = [
                        self::Method          => $Method,
                        self::requestTimeMils => 0 //(round(time() * 1000))
                    ];
                    if ($TerminalUUID) {
                        $Request[self::TerminalUUID] = $TerminalUUID;
                    }
                    if (count($Params)) {
                        $Request[self::Params] = $Params;
                        //$Request[self::requestTimeMils] = 0;
                    }

                    return $Request;
                }

                public static function BuildDiscoveryRequest(string $publicKey): string
                {
                    return json_encode([
                        self::Params=> [
                            self::DiscoveryKey=> mb_convert_encoding($publicKey, 'ISO-8859-1', 'UTF-8')
                        ]
                    ]);
                }
         */
    }
}

namespace Portainer
{
    class GUID
    {
        public const IO = '{FEB4D3D2-AD8A-9C6A-72D8-DF99AC625768}';
        public const Configurator = '{781E99AA-6F79-4430-0DF5-388D82E29019}';
        public const Container = '{80AA764D-EABE-B85E-D997-43A2D244D6E8}';
        public const Stack = '{B4110D02-0282-F7F6-363C-906280A3510A}';
        public const System = '{80544234-7E77-8F76-8376-D6C285B58443}';
        public const SendToChild = '{80CBC81C-3722-EFA3-BF6F-D7CC9B0E0076}';
        public const SendToIO = '{A5558F9B-F5AE-A5B0-C252-328740544A21}';
    }

    const IPSPresentation = 'Presentation';
    const IPSVarName = 'VarName';
    const IPSVarType = 'VarType';
    const IPSVarAction = 'VarAction';
    const ArrayIndex = 'ArrayIndex';
    class Presentation
    {
        public static function TranslateValue(string $Text): string
        {
            $translation = self::GetPresentationTranslation();
            $language = IPS_GetSystemLanguage();
            $code = explode('_', $language)[0];
            if (isset($translation['translations'])) {
                if (isset($translation['translations'][$language])) {
                    if (isset($translation['translations'][$language][$Text])) {
                        return $translation['translations'][$language][$Text];
                    }
                } elseif (isset($translation['translations'][$code])) {
                    if (isset($translation['translations'][$code][$Text])) {
                        return $translation['translations'][$code][$Text];
                    }
                }
            }
            return $Text;
        }
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
            return json_decode(file_get_contents(__DIR__ . '/locale.json'), true);
        }
    }
    trait Variables
    {
        protected function SetStateVariables(array $Data, array $Variables, int &$Pos = 0, string $Prefix = '', ?callable $AdjustSpecialValues = null): void
        {
            foreach ($Variables as $Key => $VariableData) {

                if (!isset($VariableData[\Portainer\IPSVarType])) {
                    if (isset($Data[$Key])) {
                        $this->SetStateVariables($Data[$Key], $VariableData, $Pos, $Key, $AdjustSpecialValues);
                    }
                    continue;
                }
                if (isset($Data[$Key])) {
                    $Ident = $Prefix !== '' ? $Prefix . '_' . $Key : $Key;
                    $this->MaintainVariable(
                        $Ident,
                        \Portainer\Presentation::TranslateValue($VariableData[\Portainer\IPSVarName]),
                        $VariableData[\Portainer\IPSVarType],
                        \Portainer\Presentation::TranslatePresentation(
                            $VariableData[\Portainer\IPSPresentation]
                        ),
                        ++$Pos,
                        true
                    );
                    if ($AdjustSpecialValues !== null) {
                        $AdjustSpecialValues($Ident, $Data[$Key]);
                    }
                    $this->SetValue($Ident, $Data[$Key]);
                    if ($VariableData[\Portainer\IPSVarAction]) {
                        $this->EnableAction($Ident);
                    }
                }
            }
        }
    }
}

namespace Portainer\IO
{
    class Property
    {
        public const Open = 'Open';
        public const Host = 'Host';
        public const Username = 'Username';
        public const Password = 'Password';
    }

    class Attribute
    {
        public const Token = 'jwt';
    }

    class Timer
    {
        public const UpdateToken = 'UpdateToken';
    }
}

namespace Docker\System
{
    class Property
    {
        public const EnvironmentId = 'EnvironmentId';
        public const UpdateInterval = 'Interval';
    }

    class Timer
    {
        public const UpdateInfo = 'UpdateInfo';
    }

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
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
                    \Portainer\IPSPresentation=> [],
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

    class Property
    {
        public const EnvironmentId = 'EnvironmentId';
        public const ContainerName = 'ContainerName';
        public const UpdateInterval = 'Interval';
    }

    class Timer
    {
        public const UpdateInfo = 'UpdateInfo';
    }

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
            'State'=> [
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
                                'Value'       => 'created',
                                'Caption'     => 'Created',
                                'IconActive'  => false,
                                'IconValue'   => '',
                                'Color'       => -1
                            ],
                            [
                                'Value'       => 'running',
                                'Caption'     => 'Running',
                                'IconActive'  => true,
                                'IconValue'   => 'running',
                                'Color'       => 0x00ff00
                            ],
                            [
                                'Value'       => 'paused',
                                'Caption'     => 'Paused',
                                'IconActive'  => true,
                                'IconValue'   => 'pause',
                                'Color'       => 0x0000ff
                            ],
                            [
                                'Value'       => 'restarting',
                                'Caption'     => 'Restarting',
                                'IconActive'  => true,
                                'IconValue'   => 'arrows-repeat',
                                'Color'       => 0x00ffb3
                            ],
                            [
                                'Value'       => 'removing',
                                'Caption'     => 'Removing',
                                'IconActive'  => true,
                                'IconValue'   => 'skull',
                                'Color'       => 0xff0000
                            ],
                            [
                                'Value'       => 'exited',
                                'Caption'     => 'Exited',
                                'IconActive'  => true,
                                'IconValue'   => 'stop',
                                'Color'       => 0xffae00
                            ],
                            [
                                'Value'       => 'dead',
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
                    \Portainer\IPSVarType     => VARIABLETYPE_STRING,
                    \Portainer\IPSPresentation=> [
                        'PRESENTATION'   => VARIABLE_PRESENTATION_DATE_TIME,
                        'DATE'           => 1,
                        'MONTH_TEXT'     => false,
                        'DAY_OF_THE_WEEK'=> false,
                        'TIME'           => 2
                    ],
                    \Portainer\IPSVarAction   => false
                ]
            ]
        ];
    }
}

namespace Portainer\Stack
{
    class Property
    {
        public const EnvironmentId = 'EnvironmentId';
        public const StackId = 'StackId';
        public const UpdateInterval = 'Interval';
    }

    class Timer
    {
        public const UpdateInfo = 'UpdateInfo';
    }
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
                            'ConstantValue'               => 'active',
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
                            'ConstantValue'               => 'inactive',
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
