<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/MessageStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';

use PHPUnit\Framework\TestCase;

class SwitchTest extends TestCase
{
    protected function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();

        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        //Create required profiles
        if (!IPS\ProfileManager::variableProfileExists('~Switch')) {
            IPS\ProfileManager::createVariableProfile('~Switch', 0);
        }
        if (!IPS\ProfileManager::variableProfileExists('~Switch.Reversed')) {
            IPS\ProfileManager::createVariableProfile('~Switch.Reversed', 0);
        }
        if (!IPS\ProfileManager::variableProfileExists('~Window.Reversed')) {
            IPS\ProfileManager::createVariableProfile('~Window.Reversed', 0);
        }
        if (!IPS\ProfileManager::variableProfileExists('~Intensity.255')) {
            IPS\ProfileManager::createVariableProfile('~Intensity.255', 0);
            IPS\ProfileManager::setVariableProfileValues('~Intensity.255', 0, 255, 1);
        }
        if (!IPS\ProfileManager::variableProfileExists('~Intensity.255.Reversed')) {
            IPS\ProfileManager::createVariableProfile('~Intensity.255.Reversed', 0);
            IPS\ProfileManager::setVariableProfileValues('~Intensity.255.Reversed', 0, 255, 1);
        }

        parent::setUp();
    }

    public function testSwitchBoolean(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));
        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertTrue(GetValue($ov_id));
    }

    public function testSwitchReversedBoolean(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomProfile($ov_id, '~Switch.Reversed');
        IPS_SetVariableCustomAction($ov_id, $scriptID);
        SetValue($ov_id, true);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));
        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertFalse(GetValue($ov_id));
    }

    public function testSwitchReversedBooleanNoResent(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomProfile($ov_id, '~Switch.Reversed');
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));
        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertFalse(GetValue($ov_id));
        $this->assertEquals(0, IPS_GetVariable($ov_id)['VariableUpdated']);
    }

    public function testSwitchReversedBooleanExplicitResent(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomProfile($ov_id, '~Switch.Reversed');
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));

        //Enable explicit resending of same action values
        IPS_SetProperty($iid, 'ResendAction', true);

        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertFalse(GetValue($ov_id));
        $this->assertNotEquals(0, IPS_GetVariable($ov_id)['VariableUpdated']);
    }

    public function testSwitchBooleanInvertedTrigger(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomProfile($tv_id, '~Window.Reversed');

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));
        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [false]);

        //The output needs to be enabled
        $this->assertTrue(GetValue($ov_id));
    }

    public function testSwitchDimmer(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetVariableCustomProfile($ov_id, '~Intensity.255');
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));
        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertEquals(255, GetValue($ov_id));
    }

    public function testSwitchDimmerNightMode(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetVariableCustomProfile($ov_id, '~Intensity.255');
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));

        //Create Night-Mode variable
        $nv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        SetValue($nv_id, true);

        //Set Night Mode
        IPS_SetProperty($iid, 'NightModeSource', $nv_id);

        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertEquals(floor(255 / 100 * 30), GetValue($ov_id));
    }

    public function testSwitchDimmerReversedNightMode(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Output
        $ov_id = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetVariableCustomProfile($ov_id, '~Intensity.255.Reversed');
        IPS_SetVariableCustomAction($ov_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $ov_id
        ]]));

        //Create Night-Mode variable
        $nv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        SetValue($nv_id, true);

        //Set Night Mode
        IPS_SetProperty($iid, 'NightModeSource', $nv_id);

        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertEquals(floor(255 / 100 * 70), GetValue($ov_id));
    }

    public function testDoNotSwitchIfTriggerIDIsOutputID(): void
    {
        $iid = IPS_CreateInstance('{9D5546FA-CDB2-49BB-9B1D-F40F21E8219B}');

        //Activate THL
        SetValue(IPS_GetObjectIDByIdent('Active', $iid), true);

        //Create Action
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');

        //Create Trigger
        $tv_id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetVariableCustomAction($tv_id, $scriptID);

        //Setup Trigger and Output
        IPS_SetProperty($iid, 'InputTriggers', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_SetProperty($iid, 'OutputVariables', json_encode([[
            'VariableID' => $tv_id
        ]]));
        IPS_ApplyChanges($iid);

        //Simulate a trigger change
        $interface = IPS\InstanceManager::getInstanceInterface($iid);
        $interface->MessageSink(strtotime('01.01.2000'), $tv_id, VM_UPDATE, [true]);

        //The output needs to be enabled
        $this->assertFalse(GetValue($tv_id));
    }
}