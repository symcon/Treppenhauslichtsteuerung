<?
class Treppenhauslichtsteuerung extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger("InputTriggerID", 0);
        $this->RegisterPropertyInteger("Duration", 1);
        $this->RegisterPropertyInteger("OutputID", 0);
        $this->RegisterTimer("OffTimer", 0, "THL_Stop(\$_IPS['TARGET']);");
        $this->RegisterVariableBoolean("Active", "Treppenhauslichtsteuerung aktiv", "~Switch");
        $this->EnableAction("Active");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $triggerID = $this->ReadPropertyInteger("InputTriggerID");

        $this->RegisterMessage($triggerID, 10603 /* VM_UPDATE */);
    
        $outputID = $this->ReadPropertyInteger("OutputID");
            
        if (IPS_GetVariable($outputID)["VariableType"] == 3) {
            $this->SetStatus(200);
        } else {
            $this->SetStatus(102);
        }    
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $triggerID = $this->ReadPropertyInteger("InputTriggerID");
        if (($SenderID == $triggerID) && ($Message == 10603) && (boolval($Data[0]))) {
            $this->Start();
        }
    }

    public function RequestAction($Ident, $Value)
    {

        switch ($Ident) {
            case "Active":
                $this->SetActive($Value);
                break;
            default:
                throw new Exception("Invalid ident");
        }
    }

    public function SetActive(bool $Value)
    {
        SetValue($this->GetIDForIdent("Active"), $Value);
    }

    public function Start()
    {
        if (!GetValue($this->GetIDForIdent("Active"))) {
            return;
        }

        if ($this->GetStatus() != 102) {
            return;
        }

        $triggerID = $this->ReadPropertyInteger("InputTriggerID");
        $outputID = $this->ReadPropertyInteger("OutputID");

        //If InputTriggerID and TargetID are the same we need to check if switching is required
        //Otherwise it will result in an endless loop
        if ($triggerID == $outputID) {
            if (!GetValue($outputID)) {
                $this->SwitchVariable(true);
            }
        } else {
            $this->SwitchVariable(true);
        }

        $duration = $this->ReadPropertyInteger("Duration");
        $this->SetTimerInterval("OffTimer", $duration * 60 * 1000);
    }

    public function Stop()
    {
        $this->SwitchVariable(false);
        $this->SetTimerInterval("OffTimer", 0);
    }

    private function SwitchVariable(bool $Value)
    {

        $outputID = $this->ReadPropertyInteger("OutputID");
        $variable = IPS_GetVariable($outputID);
        $actionID = $this->GetProfileAction($variable);

        //Quit if actionID is not a valid target
        if ($actionID < 10000) {
            echo $this->Translate("The output variable of the Treppenhauslichtsteuerung has no variable action. Please choose a variable with a variable action or add a variable action to the output variable.");
            return;
        }

        if (IPS_GetVariable($outputID)["VariableType"] == 0) {
            RequestAction($outputID, $Value);
        } else {
            $profileName = $this->GetProfileName($variable);
            //Quit if output variable has no profile
            if (!IPS_VariableProfileExists($profileName)) {
                echo $this->Translate("The output variable of the Treppenhauslichtsteuerung has no variable profile. Please choose a variable with a variable profile or add a variable profile to the output variable.");
                return;
            }
            $maxValue = IPS_GetVariableProfile($profileName)['MaxValue'];
            $minValue = IPS_GetVariableProfile($profileName)['MinValue'];

            //Quit if min is greater than max value
            if ($maxValue - $minValue <= 0) {
                echo $this->Translate("The profile of the output variable has no defined max value. Please update the max value or choose another profile.");
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
        if ($variable['VariableCustomProfile'] != "") {
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
