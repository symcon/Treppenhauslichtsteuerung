<?php

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class StaircaseLightControls extends IPSModule
{
    use HelperSwitchDevice;
    use HelperDimDevice;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyString('InputTriggers', '[]');
        $this->RegisterPropertyString('OutputVariables', '[]');
        $this->RegisterPropertyInteger('Duration', 1);
        $this->RegisterPropertyBoolean('DisplayRemaining', false);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyBoolean('ResendAction', false);
        $this->RegisterPropertyString('NightMode', 'off');
        $this->RegisterPropertyInteger('NightModeSource', 0);
        $this->RegisterPropertyBoolean('NightModeInverted', false);
        $this->RegisterPropertyInteger('NightModeValue', 30);
        $this->RegisterPropertyInteger('DayModeValue', 100);
        $this->RegisterPropertyInteger('NightModeSourceInteger', 0);
        $this->RegisterPropertyInteger('AmbientBrightnessThreshold', 0);

        //Registering legacy properties to transfer the data
        $this->RegisterPropertyInteger('InputTriggerID', 0);
        $this->RegisterPropertyInteger('OutputID', 0);

        //Timers
        $this->RegisterTimer('OffTimer', 0, "THL_Stop(\$_IPS['TARGET']);");
        $this->RegisterTimer('UpdateRemainingTimer', 0, "THL_UpdateRemaining(\$_IPS['TARGET']);");

        //Variables
        $this->RegisterVariableBoolean('Active', 'Treppenhauslichtsteuerung aktiv', '~Switch');
        $this->EnableAction('Active');

        //Attributes
        $this->RegisterAttributeBoolean('Migrated', false);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (!$this->ReadAttributeBoolean('Migrated') && $this->ReadPropertyInteger('NightModeSource')) {
            $this->SetNightMode('boolean');
            IPS_SetProperty($this->InstanceID, 'NightMode', 'boolean');
            $this->WriteAttributeBoolean('Migrated', true);
            IPS_ApplyChanges($this->InstanceID);
            return;
        }

        //Register variable if enabled
        $this->MaintainVariable('Remaining', $this->Translate('Remaining time'), VARIABLETYPE_STRING, '', 10, $this->ReadPropertyBoolean('DisplayRemaining'));

        //Transfer legacy data
        $transferProperty = function ($legacy, $new)
        {
            $newProperty = json_decode($this->ReadPropertyString($new), true);
            if ($this->ReadPropertyInteger($legacy) != 0) {
                $newProperty[] = ['VariableID' => $this->ReadPropertyInteger($legacy)];
                IPS_SetProperty($this->InstanceID, $legacy, 0);
                IPS_SetProperty($this->InstanceID, $new, json_encode($newProperty));
                return true;
            } else {
                return false;
            }
        };

        $legacyUpdateInput = $transferProperty('InputTriggerID', 'InputTriggers');
        $legacyUpdateOutput = $transferProperty('OutputID', 'OutputVariables');

        if ($legacyUpdateInput || $legacyUpdateOutput) {
            IPS_ApplyChanges($this->InstanceID);
            return;
        }

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        //Register update messages and references
        $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
        foreach ($inputTriggers as $inputTrigger) {
            $triggerID = $inputTrigger['VariableID'];
            $this->RegisterMessage($triggerID, VM_UPDATE);
            $this->RegisterReference($triggerID);
        }
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $outputID = $outputVariable['VariableID'];
            $this->RegisterReference($outputID);
        }

        //Check status column for inputs
        $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
        $inputTriggerOkCount = 0;
        foreach ($inputTriggers as $inputTrigger) {
            if ($this->GetTriggerStatus($inputTrigger['VariableID']) == 'OK') {
                $inputTriggerOkCount++;
            }
        }

        //Check status column for outputs
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        $outputVariablesOkCount = 0;
        foreach ($outputVariables as $outputVariable) {
            if ($this->GetOutputStatus($outputVariable['VariableID']) == 'OK') {
                $outputVariablesOkCount++;
            }
        }

        //If we are missing triggers or outputs the instance will not work
        if (($inputTriggerOkCount == 0) || ($outputVariablesOkCount == 0)) {
            $status = IS_INACTIVE;
        } else {
            $status = IS_ACTIVE;
        }

        $this->SetStatus($status);
    }

    public function GetConfigurationForm()
    {
        //Add options to form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Set status column for inputs
        $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
        foreach ($inputTriggers as $inputTrigger) {
            $jsonForm['elements'][0]['values'][] = [
                'Status' => $this->GetTriggerStatus($inputTrigger['VariableID'])
            ];
        }

        //Set status column for outputs
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $jsonForm['elements'][1]['values'][] = [
                'Status' => $this->GetOutputStatus($outputVariable['VariableID'])
            ];
        }

        $nightMode = $this->ReadPropertyString('NightMode');
        $boolVisible = $nightMode == 'boolean';
        $jsonForm['elements'][3]['items'][1]['visible'] = $boolVisible;
        $jsonForm['elements'][3]['items'][2]['visible'] = $boolVisible;
        $jsonForm['elements'][3]['items'][3]['visible'] = $boolVisible;
        $jsonForm['elements'][3]['items'][4]['visible'] = $boolVisible;

        $intVisible = $nightMode == 'integer';
        $jsonForm['elements'][3]['items'][5]['visible'] = $intVisible;
        $jsonForm['elements'][3]['items'][6]['visible'] = $intVisible;
        $jsonForm['elements'][3]['items'][7]['visible'] = $intVisible;
        $jsonForm['elements'][3]['items'][8]['visible'] = $intVisible;

        $brightnessVisible = in_array($nightMode, ['boolean', 'integer']);
        $jsonForm['elements'][3]['items'][9]['visible'] = $brightnessVisible;
        $jsonForm['elements'][3]['items'][10]['visible'] = $brightnessVisible;

        //Set visibility of remaining time options
        $jsonForm['elements'][7]['visible'] = $this->ReadPropertyBoolean('DisplayRemaining');

        return json_encode($jsonForm);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message == VM_UPDATE) {
            $getProfileName = function ($variableID)
            {
                $variable = IPS_GetVariable($variableID);
                if ($variable['VariableCustomProfile'] != '') {
                    return $variable['VariableCustomProfile'];
                } else {
                    return $variable['VariableProfile'];
                }
            };

            $isProfileReversed = function ($VariableID) use ($getProfileName)
            {
                return preg_match('/\.Reversed$/', $getProfileName($VariableID));
            };

            if (boolval($Data[0]) ^ $isProfileReversed($SenderID)) {
                $this->Start();
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Active':
                $this->SetActive($Value);
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    public function ToggleDisplayInterval(bool $visible)
    {
        $this->UpdateFormField('UpdateInterval', 'visible', $visible);
    }

    public function SetActive(bool $Value)
    {
        $this->SetValue('Active', $Value);
    }

    public function Start()
    {
        if (!$this->GetValue('Active')) {
            return;
        }

        $this->SwitchVariable(true);

        //Start OffTimer
        $duration = $this->ReadPropertyInteger('Duration');
        $this->SetTimerInterval('OffTimer', $duration * 60 * 1000);

        //Update display variable periodically if enabled
        if ($this->ReadPropertyBoolean('DisplayRemaining')) {
            $this->SetTimerInterval('UpdateRemainingTimer', 1000 * $this->ReadPropertyInteger('UpdateInterval'));
            $this->UpdateRemaining();
        }
    }

    public function Stop()
    {
        $this->SwitchVariable(false);

        //Disable OffTimer
        $this->SetTimerInterval('OffTimer', 0);

        //Disable updating of display variable
        if ($this->ReadPropertyBoolean('DisplayRemaining')) {
            $this->SetTimerInterval('UpdateRemainingTimer', 0);
            $this->SetValue('Remaining', '00:00:00');
        }
    }

    public function UpdateRemaining()
    {
        $secondsRemaining = 0;
        foreach (IPS_GetTimerList() as $timerID) {
            $timer = IPS_GetTimer($timerID);
            if (($timer['InstanceID'] == $this->InstanceID) && ($timer['Name'] == 'OffTimer')) {
                $secondsRemaining = $timer['NextRun'] - time();
                break;
            }
        }

        //Display remaining time as string
        $this->SetValue('Remaining', sprintf('%02d:%02d:%02d', ($secondsRemaining / 3600), ($secondsRemaining / 60 % 60), $secondsRemaining % 60));
    }

    public function SetNightMode(string $NightMode)
    {
        $boolVisible = $NightMode == 'boolean';
        $this->UpdateFormField('LabelNightModeSource', 'visible', $boolVisible);
        $this->UpdateFormField('NightModeSource', 'visible', $boolVisible);
        $this->UpdateFormField('LabelNightModeSourceInverted', 'visible', $boolVisible);
        $this->UpdateFormField('NightModeInverted', 'visible', $boolVisible);

        $intVisible = $NightMode == 'integer';
        $this->UpdateFormField('LabelNightModeSourceInteger', 'visible', $intVisible);
        $this->UpdateFormField('NightModeSourceInteger', 'visible', $intVisible);
        $this->UpdateFormField('LabelNightModeSourceIntegerThreshold', 'visible', $intVisible);
        $this->UpdateFormField('AmbientBrightnessThreshold', 'visible', $intVisible);

        $brightnessVisible = in_array($NightMode, ['boolean', 'integer']);
        $this->UpdateFormField('NightModeValue', 'visible', $brightnessVisible);
        $this->UpdateFormField('DayModeValue', 'visible', $brightnessVisible);
    }

    private function GetTriggerStatus($triggerID)
    {
        if (!IPS_VariableExists($triggerID)) {
            return 'Missing';
        } elseif (IPS_GetVariable($triggerID)['VariableType'] == VARIABLETYPE_STRING) {
            return 'Bool/Int/Float required';
        } else {
            return 'OK';
        }
    }

    private function GetOutputStatus($outputID)
    {
        if (!IPS_VariableExists($outputID)) {
            return 'Missing';
        } else {
            switch (IPS_GetVariable($outputID)['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    return self::getSwitchCompatibility($outputID);
                case VARIABLETYPE_INTEGER:
                case VARIABLETYPE_FLOAT:
                    return self::getDimCompatibility($outputID);
                default:
                    return 'Bool/Int/Float required';
            }
        }
    }

    private function SwitchVariable(bool $Value)
    {
        $isTrigger = function (int $outputID)
        {
            $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
            foreach ($inputTriggers as $variable) {
                if ($variable['VariableID'] == $outputID) {
                    return true;
                }
            }
            return false;
        };

        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $outputID = $outputVariable['VariableID'];

            $doResend = $this->ReadPropertyBoolean('ResendAction');

            //Prevent endless loops and do not allow resends if outputID is also a trigger
            if ($doResend) {
                if ($isTrigger($outputID)) {
                    $doResend = false;
                }
            }

            $setDevice = function ($Value) use ($outputID, $doResend)
            {
                //Depending on the type we need to switch differently
                switch (IPS_GetVariable($outputID)['VariableType']) {
                    case VARIABLETYPE_BOOLEAN:
                        if ($doResend || (self::getSwitchValue($outputID) != $Value)) {
                            self::switchDevice($outputID, $Value);
                        }
                        break;
                    case VARIABLETYPE_INTEGER:
                    case VARIABLETYPE_FLOAT:
                        if ($doResend || (self::getDimValue($outputID) != $Value)) {
                            self::dimDevice($outputID, $Value);
                        }
                        break;

                    default:
                        //Unsupported. Do nothing
                        break;
                }
            };

            if ($Value) {
                //We might need to set a different value if night-mode is in use
                switch ($this->ReadPropertyString('NightMode')) {
                    case 'boolean':
                        if (IPS_VariableExists($this->ReadPropertyInteger('NightModeSource'))
                        && (GetValue($this->ReadPropertyInteger('NightModeSource')) ^ $this->ReadPropertyBoolean('NightModeInverted'))) {
                            $setDevice($this->ReadPropertyInteger('NightModeValue'));
                        } else {
                            $setDevice($this->ReadPropertyInteger('DayModeValue'));
                        }
                        break;

                    case 'integer':
                        if (IPS_VariableExists($this->ReadPropertyInteger('NightModeSourceInteger'))
                        && (GetValue($this->ReadPropertyInteger('NightModeSourceInteger')) < $this->ReadPropertyInteger('AmbientBrightnessThreshold'))) {
                            $setDevice($this->ReadPropertyInteger('NightModeValue'));
                        } else {
                            $setDevice($this->ReadPropertyInteger('DayModeValue'));
                        }
                        break;

                    case 'off':
                        $setDevice(100);
                        break;

                    default:
                        //Unsupported. Do nothing
                        break;
                }
            } else {
                $setDevice(0);
            }
        }
    }
}
