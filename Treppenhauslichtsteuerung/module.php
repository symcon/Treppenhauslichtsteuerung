<?php

declare(strict_types=1);
define('THL_INPUT', 0);
define('THL_OUTPUT', 1);
class Treppenhauslichtsteuerung extends IPSModule
{
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

        //Timers
        $this->RegisterTimer('OffTimer', 0, "THL_Stop(\$_IPS['TARGET']);");
        $this->RegisterTimer('UpdateRemainingTimer', 0, "THL_UpdateRemaining(\$_IPS['TARGET']);");

        //Variables
        $this->RegisterVariableBoolean('Active', 'Treppenhauslichtsteuerung aktiv', '~Switch');
        $this->EnableAction('Active');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetStatus(IS_ACTIVE);

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Checking input sensors and register update messages
        $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
        
        //Inactive if no sensors
        if (sizeof($inputTriggers) > 0) {
            $inputError = true;
            $InputErrorCode = IS_ACTIVE;
            foreach ($inputTriggers as $inputTrigger) {
                $triggerID = $inputTrigger['VariableID'];
                $InputErrorCode = $this->checkVariable($triggerID, THL_INPUT);
                if ( $InputErrorCode == 0) {
                    $this->RegisterMessage($triggerID, VM_UPDATE);
                    $this->RegisterReference($triggerID);
                    $inputError = false;
                }
            }
            if($inputError) {
                $this->SetStatus(IS_EBASE);
            }   
        } else {
            $this->SetStatus(IS_INACTIVE);
        }

        //Checking output variables 
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        
        //Inactive if no output
        if (count($outputVariables) > 0) {
            $outputError = true;
            $OutputErrorCode = IS_ACTIVE;
            foreach ($outputVariables as $outputVariable) {
                $outputID = $outputVariable['VariableID'];
                $OutputErrorCode = $this->checkVariable($outputID, THL_OUTPUT);
                if ($OutputErrorCode == 0) {
                    $outputError = false;
                }
            }
            if ($outputError) {
                $this->SetStatus(IS_EBASE);
            }
        } else {
            $this->SetStatus(IS_INACTIVE);
        }

        $this->MaintainVariable('Remaining', $this->Translate('Remaining time'), VARIABLETYPE_STRING, '', 10, $this->ReadPropertyBoolean('DisplayRemaining'));
    }

    public function GetConfigurationForm()
    {
        //Add options to form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        
        $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
        $errorsInput = $this->errorsToString($inputTriggers, THL_INPUT);
        
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        $errorsOutput = $this->errorsToString($outputVariables, THL_OUTPUT);
        if (($errorsInput != '') || ($errorsOutput != '')) {
            $jsonForm['elements'][0]['caption'] = $errorsInput .$errorsOutput;
            $jsonForm['elements'][0]['visible'] = true;
        }
        //Set visibility of remaining time options
        $jsonForm['elements'][5]['visible'] = $this->ReadPropertyBoolean('DisplayRemaining');
        return json_encode($jsonForm);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        //TODO: Check if sender is trigger
        if (($Message == VM_UPDATE) && (boolval($Data[0]))) {
            $this->Start($SenderID);
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

    public function ToggleDisplayInterval($visible)
    {
        $this->UpdateFormField('UpdateInterval', 'visible', $visible);
    }

    public function SetActive(bool $Value)
    {
        SetValue($this->GetIDForIdent('Active'), $Value);
    }

    public function Start(int $triggerID)
    {
        if (!GetValue($this->GetIDForIdent('Active'))) {
            return;
        }

        if ($this->GetStatus() != IS_ACTIVE) {
            return;
        }

        $this->SwitchVariable(true);


        $duration = $this->ReadPropertyInteger('Duration');
        $this->SetTimerInterval('OffTimer', $duration * 60 * 1000);
        if ($this->ReadPropertyBoolean('DisplayRemaining')) {
            $this->SetTimerInterval('UpdateRemainingTimer', 1000 * $this->ReadPropertyInteger('UpdateInterval'));
            $this->UpdateRemaining();
        }
    }

    public function Stop()
    {
        $this->SwitchVariable(false);
        $this->SetTimerInterval('OffTimer', 0);
        if ($this->ReadPropertyBoolean('DisplayRemaining')) {
            $this->SetTimerInterval('UpdateRemainingTimer', 0);
            $this->SetValue('Remaining', '00:00:00');
        }
    }

    public function UpdateRemaining()
    {
        $remainingID = $this->GetIDForIdent('Remaining');
        $nexRun = 0;
        $timers = IPS_GetTimerList();
        //Get NextRun of "OffTimer"
        foreach ($timers as $timerID) {
            $timer = IPS_GetTimer($timerID);
            if (($timer['InstanceID'] == $this->InstanceID) && ($timer['Name'] == 'OffTimer')) {
                $nextRun = $timer['NextRun'];
                break;
            }
        }
        $secondsRemaining = $nextRun - time();
        //Dispaly remaining time as string
        $this->SetValue('Remaining', sprintf('%02d:%02d:%02d', ($secondsRemaining / 3600), ($secondsRemaining / 60 % 60), $secondsRemaining % 60));
    }

    private function SwitchVariable(bool $Value)
    {
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $outputID = $outputVariable['VariableID'];
            if ($this->checkVariable($outputID, THL_OUTPUT) != 0) {
                continue;
            }

            if ($Value && $this->isTrigger($outputID) && GetValue($outputID)) {
                continue;
            }

            if (IPS_GetVariable($outputID)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                RequestAction($outputID, $Value);
                continue;
            } else {    
                //If we are enabling analog devices we want to switch to the maximum value (e.g. 100%)
                $actionValue = 0;
                if ($Value) {
                    $actionValue = $maxValue;
                } else {
                    $actionValue = $minValue;
                }

                RequestAction($outputID, $actionValue);
            }
        }
    }

    private function GetProfileName($variable)
    {
        if ($variable['VariableCustomProfile'] != '') {
            return $variable['VariableCustomProfile'];
        } else {
            return $variable['VariableProfile'];
        }
    }

    private function checkVariable($variableID, $type)
    {

        if (!IPS_VariableExists($variableID)) {
            switch ($type) {
                case THL_INPUT:
                    return IS_EBASE + 2;

                case THL_OUTPUT:
                    return IS_EBASE + 1;
            }
        }
        if ($type == THL_OUTPUT) {
            if (IPS_GetVariable($variableID)['VariableType'] == VARIABLETYPE_STRING) {
                return IS_EBASE;
            }
            if (!HasAction($variableID)) {
                $this->LogMessage($this->Translate('The output variable of the Treppenhauslichtsteuerung has no variable action. Please choose a variable with a variable action or add a variable action to the output variable.'), KL_WARNING);
                return IS_EBASE + 3;
            }
            
            if (IPS_GetVariable($variableID)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                return 0;
            } else {
                $profileName = $this->GetProfileName($variable);
                //Quit if output variable has no profile
                if (!IPS_VariableProfileExists($profileName)) {
                    $this->LogMessage($this->Translate('The output variable of the Treppenhauslichtsteuerung has no variable profile. Please choose a variable with a variable profile or add a variable profile to the output variable.'), KL_WARNING);
                    return IS_EBASE + 4;
                }
                $maxValue = IPS_GetVariableProfile($profileName)['MaxValue'];
                $minValue = IPS_GetVariableProfile($profileName)['MinValue'];
    
                //Quit if min is greater than max value
                if ($maxValue - $minValue <= 0) {
                $this->LogMessage($this->Translate('The profile of the output variable has no defined max value. Please update the max value or choose another profile.'), KL_WARNING);
                    return IS_EBASE + 5;
                }
    
            }
        }

        return 0;
        
    }

    private function errorsToString(array $list, int $type) {
        $errors = [];
        foreach ($list as $variable) {
            $variableID = $variable['VariableID'];
            $errorCode = $this->checkVariable($variableID, $type);
            if ( $errorCode != 0) {
                if(!in_array($errorCode, $errors)) {
                    $errors[$errorCode] = [];
                }
                $errors[$errorCode][] = $variableID;
            }
        }
        $labelCaption = '';
        foreach ($errors as $error => $variables) {
            switch($error) {
                case 200:
                    $labelCaption .= $this->Translate("The following output-variables must not be a string variable. Please select a non-string variable.") . PHP_EOL;
                    break;
                case 201:
                    $labelCaption .= $this->Translate("The following output-variables do not exist.") . PHP_EOL;
                    break;
                case 202:
                    $labelCaption .= $this->Translate("The following input sensors do not exist.") . PHP_EOL;
                    break;
                case 203:
                    $labelCaption .= $this->Translate("The following output-variables have no action.") . PHP_EOL;
                    break;
                default:
                $labelCaption .= $this->Translate("The following variables have unknown errors.") . PHP_EOL;
                break;
            }
            foreach ($variables as $variable) {
                if (!IPS_VariableExists($variable)) {
                    $labelCaption .= '  - ' . sprintf($this->Translate("The object #%s doesn't exist."), $variable) . PHP_EOL;
                } else {
                    $labelCaption .= '  - ' . IPS_GetLocation($variable) . PHP_EOL;
                }
            }
        }
        return $labelCaption;
    }

    private function isTrigger(int $outputID) {
        $inputTriggers = json_decode($this->ReadPropertyString('InputTriggers'), true);
        foreach($inputTriggers as $variable){
            if ($variable['VariableID'] == $outputID) {
                return true;
            }
        }
        return false;
    }
}
