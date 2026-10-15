<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';
require_once __DIR__ . '/../libs/PortainerLib.php';

/**
 * Prüft die Variablendefinitionen gegen die Darstellungen von Symcon
 * und die Übersetzungen von Variablen und Formularen.
 *
 * tests/presentations.json enthält das Ergebnis von IPS_GetPresentation (Symcon 9.1)
 * für alle hier verwendeten Darstellungen.
 */
class PresentationTest extends TestCase
{
    private const Modules = ['Docker Container', 'Docker System', 'Portainer Configurator', 'Portainer IO', 'Portainer Stack'];

    public static function VariableConfigProvider(): array
    {
        return [
            'Docker System'    => [\Docker\System\Variables::$Config],
            'Docker Container' => [\Docker\Container\Variables::$Config],
            'Portainer Stack'  => [\Portainer\Stack\Variables::$Config]
        ];
    }

    public static function ModuleProvider(): array
    {
        $Modules = [];
        foreach (self::Modules as $Module) {
            $Modules[$Module] = [$Module];
        }
        return $Modules;
    }

    /**
     * @dataProvider VariableConfigProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('VariableConfigProvider')]
    public function testPresentationParameters(array $Config): void
    {
        $Presentations = $this->GetPresentations();
        foreach ($this->GetVariableDefinitions($Config) as $Ident => $Definition) {
            $Presentation = $Definition[\Portainer\IPSPresentation];
            if (count($Presentation) === 0) {
                continue;
            }
            $this->assertArrayHasKey('PRESENTATION', $Presentation, $Ident . ': PRESENTATION fehlt');
            $this->assertArrayHasKey($Presentation['PRESENTATION'], $Presentations, $Ident . ': unbekannte Darstellung');
            $Allowed = $this->GetAllowedParameters($Presentations[$Presentation['PRESENTATION']], $Definition[\Portainer\IPSVarType], $Ident);
            foreach ($Presentation as $Parameter => $Value) {
                if ($Parameter === 'PRESENTATION') {
                    continue;
                }
                $this->assertArrayHasKey($Parameter, $Allowed, $Ident . ': Parameter ' . $Parameter . ' gibt es für diese Darstellung und diesen Variablentyp nicht');
                if (isset($Allowed[$Parameter]['values'])) {
                    $this->assertContains($Value, $Allowed[$Parameter]['values'], $Ident . ': ungültiger Wert für ' . $Parameter);
                }
                if (isset($Allowed[$Parameter]['parameters'])) {
                    $this->assertIsArray($Value, $Ident . ': ' . $Parameter . ' muss eine Liste sein');
                    foreach ($Value as $Index => $Entry) {
                        foreach (array_keys($Entry) as $Field) {
                            $this->assertArrayHasKey($Field, $Allowed[$Parameter]['parameters'], $Ident . ': Feld ' . $Field . ' in ' . $Parameter . '[' . $Index . '] ist unbekannt');
                        }
                    }
                }
            }
        }
    }

    /**
     * @dataProvider VariableConfigProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('VariableConfigProvider')]
    public function testIntervalsDoNotOverlap(array $Config): void
    {
        $this->assertNotEmpty($Config);
        foreach ($this->GetVariableDefinitions($Config) as $Ident => $Definition) {
            $Intervals = $Definition[\Portainer\IPSPresentation]['INTERVALS'] ?? [];
            for ($i = 1; $i < count($Intervals); $i++) {
                $this->assertGreaterThan(
                    $Intervals[$i - 1]['IntervalMaxValue'],
                    $Intervals[$i]['IntervalMinValue'],
                    $Ident . ': Intervall ' . $i . ' überlappt mit dem vorherigen'
                );
            }
        }
    }

    /**
     * @dataProvider VariableConfigProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('VariableConfigProvider')]
    public function testVariableTextsAreTranslated(array $Config): void
    {
        $Translations = $this->GetTranslations(__DIR__ . '/../libs/locale.json');
        foreach ($this->GetVariableDefinitions($Config) as $Ident => $Definition) {
            foreach ($this->GetVariableTexts($Definition) as $Text) {
                $this->assertArrayHasKey($Text, $Translations, $Ident . ': Text "' . $Text . '" fehlt in libs/locale.json');
            }
        }
    }

    /**
     * @dataProvider ModuleProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ModuleProvider')]
    public function testFormIsTranslated(string $Module): void
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/../' . $Module . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $Translations = $this->GetTranslations(__DIR__ . '/../' . $Module . '/locale.json');
        foreach ($this->GetFormTexts($Form) as $Text) {
            $this->assertArrayHasKey($Text, $Translations, $Module . ': Text "' . $Text . '" fehlt in locale.json');
        }
    }

    /**
     * Liefert alle Variablendefinitionen mit ihrem Ident.
     */
    private function GetVariableDefinitions(array $Config, string $Prefix = ''): array
    {
        $Definitions = [];
        foreach ($Config as $Key => $Definition) {
            if (!isset($Definition[\Portainer\IPSVarType])) {
                $Definitions += $this->GetVariableDefinitions($Definition, $Key);
                continue;
            }
            $Definitions[$Prefix !== '' ? $Prefix . '_' . $Key : $Key] = $Definition;
        }
        return $Definitions;
    }

    /**
     * Liefert die erlaubten Parameter einer Darstellung für einen Variablentyp.
     */
    private function GetAllowedParameters(array $Presentation, int $VariableType, string $Ident): array
    {
        $Restriction = $Presentation['restrictions']['variableType'] ?? null;
        if ($Restriction !== null) {
            $this->assertContains($VariableType, $Restriction, $Ident . ': Darstellung unterstützt den Variablentyp nicht');
        }
        $Allowed = $Presentation['presentationParameters'] ?? [];
        foreach ($Presentation['groups'] ?? [] as $Group) {
            if (in_array($VariableType, $Group['restrictions']['variableType'] ?? [$VariableType], true)) {
                $Allowed += $Group['presentationParameters'] ?? [];
            }
        }
        return $Allowed;
    }

    /**
     * Liefert die Texte einer Variable, die übersetzt werden.
     */
    private function GetVariableTexts(array $Definition): array
    {
        $Texts = [$Definition[\Portainer\IPSVarName]];
        $Presentation = $Definition[\Portainer\IPSPresentation];
        foreach (['PREFIX', 'SUFFIX'] as $Key) {
            if (($Presentation[$Key] ?? '') !== '') {
                $Texts[] = $Presentation[$Key];
            }
        }
        foreach ($Presentation['OPTIONS'] ?? [] as $Option) {
            $Texts[] = $Option['Caption'];
        }
        foreach ($Presentation['INTERVALS'] ?? [] as $Interval) {
            foreach (['Constant', 'Prefix', 'Suffix'] as $Key) {
                if (($Interval[$Key . 'Active'] ?? false) && ($Interval[$Key . 'Value'] !== '')) {
                    $Texts[] = $Interval[$Key . 'Value'];
                }
            }
        }
        return $Texts;
    }

    /**
     * Sammelt alle Beschriftungen eines Formulars.
     */
    private function GetFormTexts(array $Form): array
    {
        $Texts = [];
        array_walk_recursive($Form, function ($Value, $Key) use (&$Texts)
        {
            if (in_array($Key, ['caption', 'suffix'], true) && is_string($Value) && ($Value !== '')) {
                $Texts[] = $Value;
            }
        });
        return array_unique($Texts);
    }

    private function GetTranslations(string $File): array
    {
        return json_decode(file_get_contents($File), true, 512, JSON_THROW_ON_ERROR)['translations']['de'];
    }

    private function GetPresentations(): array
    {
        $Presentations = [];
        foreach (json_decode(file_get_contents(__DIR__ . '/presentations.json'), true, 512, JSON_THROW_ON_ERROR) as $Entry) {
            $Presentations[$Entry['guid']] = $Entry['presentation'];
        }
        return $Presentations;
    }
}
