<?php

/*
|--------------------------------------------------------------------------
| Smart Pump Gateway
|--------------------------------------------------------------------------
|
| Version : v1.5.0
| Updated : 2026-05-29
|
| Changes:
| - MQTT command handling
| - ICT input monitoring
| - State change detection
| - Event-driven MQTT publishing
| - Schedule command support
| - ACK implementation
| - Alarm cancel implemented 
| - Schedule start and Inhibit Schedules are implemented
| - Certificate renewal implemented
| - Aligned Recurring daily schedule time range for Schedule start and Inhibit 
| - Updated Severity and Faultcode as per the ICT document  
*/

require_once __DIR__ . '/config/config.php';

require_once __DIR__ . '/classes/Logger.php';
require_once __DIR__ . '/classes/MQTTClient.php';
require_once __DIR__ . '/classes/ICTController.php';
require_once __DIR__ . '/classes/IncomingCommandHandler.php';
require_once __DIR__ . '/classes/EventPublisher.php';
require_once __DIR__ . '/classes/ScheduleManager.php';
require_once __DIR__ . '/classes/ScheduleEngine.php';
require_once __DIR__ . '/classes/InhibitScheduleEngine.php';

echo "====================================\n";
echo "Version: v1.4.0\n";
echo " SMART PUMP MQTT GATEWAY STARTED\n";
echo "====================================\n";

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

try {

    //   ICT Controller

    $ict = new ICTController();

    $connected = $ict->connect();

    if (!$connected) {

        echo "[SYSTEM] ICT Connection Failed\n";

        exit;
    }


    $mqtt = new MQTTClient($ict);

    $mqtt->connect();

    $mqtt->subscribe();

    $eventPublisher = new EventPublisher($mqtt);

    $scheduleManager = new ScheduleManager();
    $scheduleEngine = new ScheduleEngine($ict, $scheduleManager);

    $inhibitScheduleEngine = new InhibitScheduleEngine($ict, $scheduleManager);

    echo "[SYSTEM] Gateway Running...\n";

    // Load Initial States

    $previousInputs = [];

    $initialInputs =
        $ict->readInputs();

    foreach ($initialInputs as $label => $data) {

        $previousInputs[$label] =
            $data['status'];
    }

    echo "[SYSTEM] Initial input states loaded\n";



    while (true) {

        //echo date('Y-m-d H:i:s') . "\n";
        $mqtt->loop();
        $inputs = $ict->readInputs();

        // Schedule Engine
        $isAutoMode = $inputs['AUTO_ON']['status'] === 'ACTIVE';  //true; 

        if ($isAutoMode) {
            $scheduleEngine->process();
            $inhibitScheduleEngine->process($inputs);
        }


        foreach ($inputs as $label => $data) {

            $currentStatus =
                $data['status'];

            $previousStatus =
                $previousInputs[$label]
                ?? null;



            if ($currentStatus !== $previousStatus) {

                echo "\n[" . date('Y-m-d H:i:s') . "] [STATE CHANGE] {$label} => {$currentStatus}\n";

                //    Pump 1 Events

                if (
                    $label === 'PUMP1_RUNNING'
                    &&
                    $currentStatus === 'ACTIVE'
                ) {

                    echo "[INFO] Pump 1 RUNNING\n";

                    $eventPublisher->publish(

                        "Waterpump/pump#started",
                        [

                            "PumpRunStatus" =>
                            "Running",

                            "Description" =>
                            "TransferPump1"
                        ]
                    );
                }

                if (
                    $label === 'PUMP1_STOPPED'
                    &&
                    $currentStatus === 'ACTIVE'
                ) {

                    echo "[INFO] Pump 1 STOPPED\n";

                    $eventPublisher->publish(

                        "Waterpump/pump#stopped",

                        [

                            "PumpRunStatus" =>
                            "Stopped",

                            "Description" =>
                            "TransferPump1"
                        ]
                    );
                }

                if (
                    $label === 'PUMP1_TRIP'
                    &&
                    $currentStatus === 'ACTIVE'
                ) {

                    echo "[ALARM] Pump 1 TRIPPED!\n";

                    $eventPublisher->publish(

                        "Waterpump/pump#tripfailure",

                        [

                            "PumpTripStatus" => "TripON",

                            "Description" => "TransferPump1 Trip Failure",
                        ]
                    );
                }

                // Pump 2 Events

                if ($label === 'PUMP2_RUNNING' && $currentStatus === 'ACTIVE') {

                    echo "[INFO] Pump 2 RUNNING\n";

                    $eventPublisher->publish(

                        "Waterpump/pump#started",
                        [

                            "PumpRunStatus" => "Running",

                            "Description" => "TransferPump2"
                        ]
                    );
                }

                if ($label === 'PUMP2_STOPPED' && $currentStatus === 'ACTIVE') {

                    echo "[INFO] Pump 2 STOPPED\n";

                    $eventPublisher->publish(

                        "Waterpump/pump#stopped",

                        [
                            "PumpRunStatus" => "Stopped",
                            "Description" => "TransferPump2"
                        ]
                    );
                }

                if ($label === 'PUMP2_TRIP' && $currentStatus === 'ACTIVE') {

                    echo "[ALARM] Pump 2 TRIPPED!\n";

                    $eventPublisher->publish(

                        "Waterpump/pump#tripfailure",

                        [
                            "PumpTripStatus" => "TripON",
                            "Description" => "TransferPump2 Trip Failure",
                        ]
                    );
                }

                //    Pump Mode Events

                if ($label === 'MANUAL_ON' && $currentStatus === 'ACTIVE') {

                    echo "[MODE] MANUAL Mode\n";

                    $eventPublisher->publish(

                        "Waterpump/system#reading",

                        [
                            "SystemPumpMode" => "Manual"
                        ]
                    );
                }

                if ($label === 'AUTO_ON' && $currentStatus === 'ACTIVE') {

                    echo "[MODE] AUTO Mode\n";

                    $eventPublisher->publish(

                        "Waterpump/system#reading",

                        [
                            "SystemPumpMode" => "Auto"
                        ]
                    );
                }

                // Inhibit Events

                if ($label === 'IS_INHIBIT_ON' && $currentStatus === 'ACTIVE') {

                    echo "[INFO] Inhibit ACTIVE\n";

                    $eventPublisher->publish(

                        "Waterpump/system#inhibitactivated",

                        [
                            "InhibitStatus" => "On",
                            "Description" => "Pump Inhibit Activated",
                        ]
                    );
                }


                if ($label === 'IS_POWER_FAILURE' && $currentStatus === 'ACTIVE') {

                    echo "[ALARM] Power Failure!\n";

                    $eventPublisher->publish(

                        "Waterpump/system#powerfailure",

                        [
                            "CPSupplyPowerStatus" => "Off",
                            "Description" => "Power Failure",
                        ]
                    );
                }


                if ($label === 'ROOF_TANK_LOW' && $currentStatus === 'ACTIVE') {

                    echo "[WARNING] Roof Tank LOW\n";

                    $eventPublisher->publish(

                        "Waterpump/system#criticallylow",

                        [
                            "RoofTopTankLevel" => "Low",
                            "Description" => "Roof Tank Low Level",
                        ]
                    );
                }

                if ($label === 'ROOF_TANK_LOW_LOW' && $currentStatus === 'ACTIVE') {

                    echo "[ALARM] Roof Tank LOW LOW\n";

                    $eventPublisher->publish(

                        "Waterpump/system#criticallylow",

                        [
                            "RoofTopTankLevel" => "LLow",
                            "Description" => "Roof Tank Low Low Level",
                        ]
                    );
                }

                if ($label === 'ROOF_TANK_NORMAL' && $currentStatus === 'ACTIVE') {

                    echo "[INFO] Roof Tank NORMAL\n";

                    $eventPublisher->publish(

                        "Waterpump/system#reading",

                        [

                            "RoofTopTankLevel" => "High"
                        ]
                    );
                }

                if ($label === 'ROOF_TANK_OVERFLOW' && $currentStatus === 'ACTIVE') {

                    echo "[ALARM] Roof Tank OVERFLOW\n";

                    $eventPublisher->publish(

                        "Waterpump/system#overflow",

                        [
                            "RoofTopTankLevel" => "HHigh",
                            "Description" => "Roof Tank Overflow",
                        ]
                    );
                }



                if ($label === 'SUCTION_TANK_LOW' && $currentStatus === 'ACTIVE') {

                    echo "[WARNING] Suction Tank LOW\n";

                    $eventPublisher->publish(

                        "Waterpump/system#criticallylow",

                        [
                            "SuctionTankLevel" => "Low",
                            "Description" => "Suction Tank Low Level",
                        ]
                    );
                }

                if ($label === 'SUCTION_TANK_NORMAL' && $currentStatus === 'ACTIVE') {

                    echo "[INFO] Suction Tank NORMAL\n";

                    $eventPublisher->publish(

                        "Waterpump/system#reading",

                        [
                            "SuctionTankLevel" => "High"
                        ]
                    );
                }

                if ($label === 'SUCTION_TANK_OVERFLOW' && $currentStatus === 'ACTIVE') {

                    echo "[ALARM] Suction Tank OVERFLOW\n";

                    $eventPublisher->publish(

                        "Waterpump/system#overflow",

                        [
                            "SuctionTankLevel" => "HHigh",
                            "Description" => "Suction Tank Overflow",
                        ]
                    );
                }

                echo "\n";
            }


            $previousInputs[$label] = $currentStatus;
        }

        usleep(200000);
    }
} catch (Exception $e) {

    Logger::error(
        $e->getMessage(),
        'error.log'
    );

    echo "[ERROR] "
        . $e->getMessage()
        . "\n";
}
