<?php

declare(strict_types=1);
class Treppenhauslichtsteuerung extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('InputTriggerID', 0);
        $this->RegisterPropertyInteger('Duration', 1);
        $this->RegisterPropertyInteger('OutputID', 0);
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

        $triggerID = $this->ReadPropertyInteger('InputTriggerID');
        if (IPS_VariableExists($triggerID)) {
            $this->RegisterMessage($triggerID, VM_UPDATE);
            $this->SetStatus(IS_ACTIVE);
        } else {
            if ($triggerID == 0) {
                $this->SetStatus(IS_INACTIVE);
            } else {
                $this->SetStatus(IS_EBASE + 2);
            }
        }

        $outputID = $this->ReadPropertyInteger('OutputID');
        if (IPS_VariableExists($outputID) && $this->GetStatus() == IS_ACTIVE) {
            if (IPS_GetVariable($outputID)['VariableType'] == VARIABLETYPE_STRING) {
                $this->SetStatus(IS_EBASE);
            } else {
                $this->SetStatus(IS_ACTIVE);
            }
        } elseif ($this->GetStatus() == IS_ACTIVE) {
            if ($outputID == 0) {
                $this->SetStatus(IS_INACTIVE);
            } else {
                $this->SetStatus(IS_EBASE + 1);
            }
        }

        //Add references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        if (IPS_VariableExists($triggerID)) {
            $this->RegisterReference($triggerID);
        }
        if (IPS_VariableExists($outputID)) {
            $this->RegisterReference($outputID);
        }

        $this->MaintainVariable('Remaining', $this->Translate('Remaining time'), VARIABLETYPE_STRING, '', 10, $this->ReadPropertyBoolean('DisplayRemaining'));
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $triggerID = $this->ReadPropertyInteger('InputTriggerID');
        if (($SenderID == $triggerID) && ($Message == VM_UPDATE) && (boolval($Data[0]))) {
            $this->Start();
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

    public function GetConfigurationForm()
    {
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $jsonForm['elements'][4]['visible'] = $this->ReadPropertyBoolean('DisplayRemaining');
        return json_encode($jsonForm);
    }

    public function ToggleDisplayInterval($visible)
    {
        $this->UpdateFormField('UpdateInterval', 'visible', $visible);
    }

    public function SetActive(bool $Value)
    {
        SetValue($this->GetIDForIdent('Active'), $Value);
    }

    public function Start()
    {
        if (!GetValue($this->GetIDForIdent('Active'))) {
            return;
        }

        if ($this->GetStatus() != IS_ACTIVE) {
            return;
        }

        $triggerID = $this->ReadPropertyInteger('InputTriggerID');
        $outputID = $this->ReadPropertyInteger('OutputID');

        //If InputTriggerID and TargetID are the same we need to check if switching is required
        //Otherwise it will result in an endless loop
        if ($triggerID == $outputID) {
            if (!GetValue($outputID)) {
                $this->SwitchVariable(true);
            }
        } else {
            $this->SwitchVariable(true);
        }

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
        $outputID = $this->ReadPropertyInteger('OutputID');
        $variable = IPS_GetVariable($outputID);
        $actionID = $this->GetProfileAction($variable);

        //Quit if actionID is not a valid target
        if ($actionID < 10000) {
            echo $this->Translate('The output variable of the Treppenhauslichtsteuerung has no variable action. Please choose a variable with a variable action or add a variable action to the output variable.');
            return;
        }

        if (IPS_GetVariable($outputID)['VariableType'] == VARIABLETYPE_BOOLEAN) {
            RequestAction($outputID, $Value);
        } else {
            $profileName = $this->GetProfileName($variable);
            //Quit if output variable has no profile
            if (!IPS_VariableProfileExists($profileName)) {
                echo $this->Translate('The output variable of the Treppenhauslichtsteuerung has no variable profile. Please choose a variable with a variable profile or add a variable profile to the output variable.');
                return;
            }
            $maxValue = IPS_GetVariableProfile($profileName)['MaxValue'];
            $minValue = IPS_GetVariableProfile($profileName)['MinValue'];

            //Quit if min is greater than max value
            if ($maxValue - $minValue <= 0) {
                echo $this->Translate('The profile of the output variable has no defined max value. Please update the max value or choose another profile.');
                return;
            }

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

    private function GetProfileName($variable)
    {
        if ($variable['VariableCustomProfile'] != '') {
            return $variable['VariableCustomProfile'];
        } else {
            return $variable['VariableProfile'];
        }
    }

    private function GetProfileAction($variable)
    {
        if ($variable['VariableCustomAction'] > 0) {
            return $variable['VariableCustomAction'];
        } else {
            return $variable['VariableAction'];
        }
    }
}
