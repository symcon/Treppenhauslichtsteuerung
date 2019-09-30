<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class TreppenhauslichtsteuerungValidationTest extends TestCaseSymconValidation
{
    public function testValidateTreppenhauslichtsteuerung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateTreppenhauslichtsteuerungModule(): void
    {
        $this->validateModule(__DIR__ . '/../Treppenhauslichtsteuerung');
    }
}