<?php

declare(strict_types=1);

/**
 * PortainerLib
 * Enthält Klassen welche die API und Geräte Fähigkeiten abbilden.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.0
 */

namespace Portainer\Api
{
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
                            ],
                            [
                                'IntervalMinValue'            => 1073741823,
                                'IntervalMaxValue'            => PHP_INT_MAX,
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
                    \Portainer\IPSVarType     => VARIABLETYPE_INTEGER,
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
