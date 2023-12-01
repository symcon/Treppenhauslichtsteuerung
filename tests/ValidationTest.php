<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class ValidationTest extends TestCaseSymconValidation
{
    public function testValidateTreppenhauslichtsteuerung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateStaircaseLightControlsModule(): void
    {
        $this->validateModule(__DIR__ . '/../StaircaseLightControls');
    }
}