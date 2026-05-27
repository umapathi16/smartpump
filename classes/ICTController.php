<?php

require_once __DIR__ . '/../src/ControllerAPI.php';

use ICT\ControllerAPI;

class ICTController
{
    private $httpClient;

    const INPUTS = [

        0  => 'SUCTION_TANK_OVERFLOW',

        14 => 'PUMP1_RUNNING',
        15 => 'PUMP1_STOPPED',
        16 => 'PUMP1_TRIP',

        17 => 'PUMP2_RUNNING',
        18 => 'PUMP2_STOPPED',
        19 => 'PUMP2_TRIP',

        20 => 'MANUAL_ON',
        21 => 'AUTO_ON',

        22 => 'IS_INHIBIT_ON',

        23 => 'IS_POWER_FAILURE',

        24 => 'ROOF_TANK_LOW',
        25 => 'ROOF_TANK_LOW_LOW',
        26 => 'ROOF_TANK_NORMAL',
        27 => 'ROOF_TANK_OVERFLOW',

        28 => 'SUCTION_TANK_LOW',
        29 => 'SUCTION_TANK_NORMAL'
    ];


    const OUTPUTS = [

        124 => 'PUMP1_ON1',
        125 => 'PUMP1_ON2',

        126 => 'PUMP2_ON1',
        127 => 'PUMP2_ON2',

        128 => 'INHIBIT_ON1',
        129 => 'INHIBIT_ON2',

        130 => 'ALARM_CANCEL_ON1',
        131 => 'ALARM_CANCEL_ON2'
    ];

    private $outputLabelToPin = [];


    public function __construct()
    {
        $this->outputLabelToPin =
            array_flip(self::OUTPUTS);
    }


    public function connect()
    {
        $this->httpClient =
            new ControllerAPI("192.168.1.2", false);  // 

        $passwordHash =
            ControllerAPI::sha1FromString("12345678");

        echo "[ICT] Logging in...\n";

        $loggedIn = $this->httpClient->logIn(
            "admin",
            strtolower($passwordHash)
        );

        if (!$loggedIn) {

            echo "[ICT] Login Failed\n";

            return false;
        }

        echo "[ICT] Connected Successfully\n";

        return true;
    }


    public function readInputs(): array
    {
        $allInputs =
            $this->httpClient->listInputsStatus();

        $result = [];

        foreach (self::INPUTS as $pin => $label) {

            $rawValue =
                $allInputs[$pin] ?? "0,0";

            $result[$label] = [

                'pin' => $pin,

                'raw' => $rawValue,

                'status' =>
                $this->parseInputState($rawValue)
            ];
        }

        return $result;
    }


    private function parseInputState($value): string
    {
        return ($value === "1,0")
            ? "ACTIVE"
            : "INACTIVE";
    }


    public function setOutput(
        string $label,
        bool $state
    ): bool {

        if (
            !isset($this->outputLabelToPin[$label])
        ) {

            echo "[ICT] Unknown Output: {$label}\n";

            return false;
        }

        $pin =
            $this->outputLabelToPin[$label];

        $mode = $state ? 1 : 0;

        echo "[ICT] Setting {$label} => "
            . ($state ? "ON" : "OFF")
            . "\n";

        $response =
            $this->httpClient->controlOutput(
                $pin,
                $mode,
                0,
                0
            );

        echo "[ICT] Response: {$response}\n";

        return ($response === "OK");
    }



    public function startPump($pumpNo): bool
    {
        echo "[ICT] Starting Pump {$pumpNo}\n";

        switch ($pumpNo) {

            case 1:

                $r1 = $this->setOutput(
                    'PUMP1_ON1',
                    true
                );

                $r2 = $this->setOutput(
                    'PUMP1_ON2',
                    true
                );

                return ($r1 && $r2);

            case 2:

                $r1 = $this->setOutput(
                    'PUMP2_ON1',
                    true
                );

                $r2 = $this->setOutput(
                    'PUMP2_ON2',
                    true
                );

                return ($r1 && $r2);
        }

        return false;
    }

    public function stopPump($pumpNo): bool
    {
        echo "[ICT] Stopping Pump {$pumpNo}\n";

        switch ($pumpNo) {

            case 1:

                $r1 = $this->setOutput(
                    'PUMP1_ON1',
                    false
                );

                $r2 = $this->setOutput(
                    'PUMP1_ON2',
                    false
                );

                return ($r1 && $r2);

            case 2:

                $r1 = $this->setOutput(
                    'PUMP2_ON1',
                    false
                );

                $r2 = $this->setOutput(
                    'PUMP2_ON2',
                    false
                );

                return ($r1 && $r2);
        }

        return false;
    }


    public function enableInhibit(): bool
    {
        $r1 = $this->setOutput(
            'INHIBIT_ON1',
            true
        );

        $r2 = $this->setOutput(
            'INHIBIT_ON2',
            true
        );

        return ($r1 && $r2);
    }

    public function disableInhibit(): bool
    {
        $r1 = $this->setOutput(
            'INHIBIT_ON1',
            false
        );

        $r2 = $this->setOutput(
            'INHIBIT_ON2',
            false
        );

        return ($r1 && $r2);
    }

    public function cancelAlarm(): bool
    {
        echo "[ICT] Cancelling Alarm\n";

        $r1 = $this->setOutput(
            'ALARM_CANCEL_ON1',
            true
        );

        $r2 = $this->setOutput(
            'ALARM_CANCEL_ON2',
            true
        );

        return ($r1 && $r2);
    }

    public function resetAlarmMute(): bool
    {
        $success1 =
            $this->setOutput(
                'ALARM_CANCEL_ON1',
                false
            );

        $success2 =
            $this->setOutput(
                'ALARM_CANCEL_ON2',
                false
            );

        return $success1 && $success2;
    }


    public function disconnect()
    {
        if ($this->httpClient) {

            $this->httpClient->logOut();

            echo "[ICT] Disconnected\n";
        }
    }
}
