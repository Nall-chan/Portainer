<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../Portainer Configurator');
    }

    public function testValidateIO(): void
    {
        $this->validateModule(__DIR__ . '/../Portainer IO');
    }

    public function testValidateStack(): void
    {
        $this->validateModule(__DIR__ . '/../Portainer Stack');
    }

    public function testValidateSystem(): void
    {
        $this->validateModule(__DIR__ . '/../Docker System');
    }
    public function testValidateContainer(): void
    {
        $this->validateModule(__DIR__ . '/../Docker Container');
    }

}