<?php

class InhibitScheduleEngine
{
    private $ict;

    private $scheduleManager;


    private $inhibitStarted = [];

    private $pumpStartedByInhibit = [];

    public function __construct(
        $ict,
        $scheduleManager
    ) {

        $this->ict =
            $ict;

        $this->scheduleManager =
            $scheduleManager;
    }



    public function process($inputs)
    {

        // Load Inhibit Schedules

        $schedules = $this->scheduleManager->getInhibitSchedules();

        $currentDateTime = strtotime(date('Y-m-d H:i:s'));

        foreach ($schedules as $schedule) {

            $scheduleKey =
                md5(
                    json_encode($schedule)
                );

            $startDateTime = strtotime($schedule['StartDate'] . ' ' . $schedule['StartTime']);

            $endDateTime = strtotime($schedule['EndDate'] . ' ' . $schedule['EndTime']);

            // Validate Inhibit range
            $isScheduleActive = $currentDateTime >= $startDateTime && $currentDateTime <= $endDateTime;

            // Inhibit ACTIVE

            if ($isScheduleActive) {

                // Schedulekey for Prevent Duplicate Inhibit Start

                if (!isset($this->inhibitStarted[$scheduleKey])) {

                    echo "\n";
                    echo "[INHIBIT ACTIVE]\n";

                    // Enable Inhibit

                    $this->ict->enableInhibit();

                    // Mute Alarm

                    if ($schedule['MuteTransferPumpAlarm'] === true) {

                        echo "[INHIBIT] Alarm Muted\n";

                        $this->ict->cancelAlarm();
                    }

                    $this->inhibitStarted[$scheduleKey] =
                        true;
                }

                // check Roof Tank LOW LOW

                $isRoofTankLowLow = $inputs['ROOF_TANK_LOW_LOW']['status'] === 'ACTIVE';

                //  Start Pump If LOW LOW

                if ($schedule['StartPumpIfRoofTankLowLow'] === true && $isRoofTankLowLow) {
                    if (!isset($this->pumpStartedByInhibit[$scheduleKey])) {
                        echo "[INHIBIT] Roof Tank LOW LOW\n";
                        echo "[INHIBIT] Starting Pump\n";
                        $this->ict->startPump(1);
                        $this->pumpStartedByInhibit[$scheduleKey] =
                            true;
                    }
                }
            } else {
                // Schedule ENDED

                if (isset($this->inhibitStarted[$scheduleKey])) {

                    // Hold Until Roof Tank NORMAL

                    if ($schedule['RunPumpToMakeRoofTankHighBeforeReleaseAlarm'] === true) {

                        $isRoofTankNormal = $inputs['ROOF_TANK_NORMAL']['status'] === 'ACTIVE';
                        if (!$isRoofTankNormal) {
                            echo "[INHIBIT] Waiting Roof Tank NORMAL before release\n";
                            continue;
                        }
                    }

                    echo "\n";
                    echo "[INHIBIT RELEASED]\n";

                    $this->ict->disableInhibit();
                    // Stop Pump Only If Started By Inhibit

                    if (isset($this->pumpStartedByInhibit[$scheduleKey])) {

                        $this->ict->stopPump(1);

                        unset($this->pumpStartedByInhibit[$scheduleKey]);
                    }

                    unset($this->inhibitStarted[$scheduleKey]);
                }
            }
        }
    }
}
