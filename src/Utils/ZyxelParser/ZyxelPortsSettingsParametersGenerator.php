<?php

namespace App\Utils\ZyxelParser;

use App\Entity\ZyxelDeviceType;

class ZyxelPortsSettingsParametersGenerator implements ZyxelParametersGeneratorInterface
{
    public const COMMAND_NAME = "ports_settings";
    public const PORT_SPEED_AUTO = 0;
    public const PORT_SPEED_10 = 2;
    public const PORT_SPEED_100 = 4;

    public function generateParameters(string $device, array $cmdParse): array
    {
        if (!array_key_exists(1, $cmdParse)) {
            throw new ZyxelPortsSettingsParametersGeneratorException("At least one parameter is required after the command " . self::COMMAND_NAME);
        }

        //port_settings P1 all_default P2 state_enabled flow_ctl_enabled poe_enabled speed_10
        //port_settings all_default

        // speed 0=Auto 2=10 4=100

        // all ports will have default value : state_enabled flow_ctl_disabled poe_enabled speed_auto
        if ($cmdParse[1] == 'all_default') {
            return $this->handleAllDefaultParameters($device, $cmdParse);
        }

        // here we have to handle specific ports
        $argumentInMemories = [];
        $currentParameters = $this->getAllDefaultParameters($device);
        foreach ($cmdParse as $key => $argument) {
            // We need to ignore the first one argumen which is the command name
            if ($key == array_key_first($cmdParse)) {
                continue;
            }
            if (preg_match("/^P[1-8]$/", $argument) == true) {
                // If we finish with a port name, that is an error
                if ($key == array_key_last($cmdParse)) {
                    throw new ZyxelPortsSettingsParametersGeneratorException("you need to pass an argument after a port name.");
                }
                // If we have memorized a previous list of argument, then we generate the correspondant values
                if ($argumentInMemories) {
                    $this->handleSpecificPortParameters($device, $argumentInMemories, $currentParameters);
                }
                // Initialization with the new PX line
                $argumentInMemories = [$argument];
            } else {
                $argumentInMemories[] = $argument;
                // If last argument, we handle the last port
                if ($key == array_key_last($cmdParse)) {
                    $this->handleSpecificPortParameters($device, $argumentInMemories, $currentParameters);
                }
            }
        }
        return $currentParameters;
    }
    private function checkPortsArguments(string $device, array $arguments, int $portToUpdate): void
    {
        if (count($arguments) < 2) {
            throw new ZyxelPortsSettingsParametersGeneratorException("at least one argument is needed after the port name");
        }
        // Check if POE can be handle
        if ($device == ZyxelDeviceType::GS1200_5HP && $portToUpdate == 4 && (in_array("poe_enabled", $arguments) || in_array("poe_disabled", $arguments))) {
            throw new ZyxelPortsSettingsParametersGeneratorException("POE not available on the last port");
        }
        if ($device == ZyxelDeviceType::GS1200_8HP && $portToUpdate == 7 && (in_array("poe_enabled", $arguments) || in_array("poe_disabled", $arguments))) {
            throw new ZyxelPortsSettingsParametersGeneratorException("POE not available on the last port");
        }
    }
    private function handleSpecificPortParameters(string $device, array $arguments, array &$parametersToUpdate)
    {
        $portToUpdate = (int) substr($arguments[0], 1, 1) - 1;
        $this->checkPortsArguments($device, $arguments, $portToUpdate);
        $parameter_speed_name = "g_port_speed" . $portToUpdate;
        foreach ($arguments as $argument) {
            switch ($argument) {
                    //state_enabled flow_ctl_enabled poe_enabled speed_10
                case 'state_enabled':
                    // nothing to do, because enabled by default
                    break;
                case 'state_disabled':
                    // need to substract the corresponding value of the total amount
                    $parametersToUpdate['g_port_state'] = $parametersToUpdate['g_port_state'] - pow(2, $portToUpdate);
                    break;
                case 'flow_ctl_enabled':
                    // need to add the corresponding value of the total amount
                    $parametersToUpdate['g_port_flwcl'] = $parametersToUpdate['g_port_flwcl'] + pow(2, $portToUpdate);
                    break;
                case 'flow_ctl_disabled':
                    // nothing to do, because disabled by default
                    break;
                case 'poe_enabled':
                    // nothing to do, because enabled by default
                    break;
                case 'poe_disabled':
                    // need to substract the corresponding value of the total amount
                    $parametersToUpdate['g_port_poe'] = $parametersToUpdate['g_port_poe'] - pow(2, $portToUpdate);
                    break;
                case 'speed_10':
                    $parametersToUpdate[$parameter_speed_name] = self::PORT_SPEED_10;
                    break;
                case 'speed_100':
                    $parametersToUpdate[$parameter_speed_name] = self::PORT_SPEED_100;
                    break;
                case 'speed_auto':
                    $parametersToUpdate[$parameter_speed_name] = self::PORT_SPEED_AUTO;
                    break;
            }
        }
    }
    private function getAllDefaultParameters(string $device): array
    {
        if (ZyxelDeviceType::GS1200_5HP == $device) {
            return [
                'g_port_state' => 31,
                'g_port_flwcl' => 0,
                'g_port_poe' => 15,
                'g_port_speed0' => 0,
                'g_port_speed1' => 0,
                'g_port_speed2' => 0,
                'g_port_speed3' => 0,
                'g_port_speed4' => 0
            ];
        } else {
            return [
                'g_port_state' => 255,
                'g_port_flwcl' => 0,
                'g_port_poe' => 127,
                'g_port_speed0' => 0,
                'g_port_speed1' => 0,
                'g_port_speed2' => 0,
                'g_port_speed3' => 0,
                'g_port_speed4' => 0,
                'g_port_speed5' => 0,
                'g_port_speed6' => 0,
                'g_port_speed7' => 0
            ];
        }
    }
    private function handleAllDefaultParameters(string $device, array $cmdParse): array
    {
        if (array_key_exists(2, $cmdParse)) {
            throw new ZyxelPortsSettingsParametersGeneratorException("With the argument 'all_default', no other argument can be present");
        }

        return $this->getAllDefaultParameters($device);
    }

    public function generateUrlAndMethod(): array
    {
        return ["url" => "/port_state_set.cgi", "method" => "POST"];
    }
    public function generateHeaders(): array
    {
        return  ['Content-Type' => 'application/x-www-form-urlencoded'];
    }
}
