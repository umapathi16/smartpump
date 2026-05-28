<?php

class ScheduleEngine
{
    private $ict;

    private $scheduleManager;


    private $scheduleStarted = [];

    public function __construct($ict, $scheduleManager)
    {

        $this->ict = $ict;

        $this->scheduleManager = $scheduleManager;
    }

    public function process()
    {
        // Load Schedules

        $schedules = $this->scheduleManager->getStartSchedules();

        $currentDateTime = strtotime(date('Y-m-d H:i:s'));

        foreach ($schedules as $schedule) {

            // Unique Runtime Key

            $scheduleKey = md5(json_encode($schedule));

            $startDateTime = strtotime($schedule['StartDate'] . ' ' . $schedule['StartTime']);

            $endDateTime = strtotime($schedule['EndDate'] . ' ' . $schedule['EndTime']);

            // Validate schedule range

            $isScheduleActive =  $currentDateTime >= $startDateTime && $currentDateTime <= $endDateTime;

            if ($isScheduleActive) {

                // Prevent Duplicate Starts

                if (!isset($this->scheduleStarted[$scheduleKey])) {

                    echo "\n";

                    echo "[SCHEDULE START] ";

                    echo "PumpMode: "
                        . $schedule['PumpMode']
                        . "\n";


                    switch ($schedule['PumpMode']) {

                        // Alternative Pump
                        case 0:

                            echo "[SCHEDULE] Alternative Pump Start\n";

                            $this->ict->startPump(1);

                            break;

                        // Pump1 Only

                        case 1:

                            echo "[SCHEDULE] Starting Pump1\n";

                            $this->ict->startPump(1);

                            break;

                        // Pump1 + Pump2 start

                        case 2:

                            echo "[SCHEDULE] Starting Pump1 + Pump2\n";

                            $this->ict->startPump(1);

                            $this->ict->startPump(2);

                            break;

                        default:

                            echo "[SCHEDULE] Invalid PumpMode\n";

                            break;
                    }

                    // Mark Schedule Started

                    $this->scheduleStarted[$scheduleKey] =
                        true;
                }
            } else {

                // Schedule ENDED

                if (isset($this->scheduleStarted[$scheduleKey])) {

                    echo "\n";

                    echo "[SCHEDULE END] ";

                    echo "PumpMode: "
                        . $schedule['PumpMode']
                        . "\n";


                    // Stop Pumps

                    switch ($schedule['PumpMode']) {

                        case 0:

                            $this->ict->stopPump(1);

                            break;

                        case 1:

                            $this->ict->stopPump(1);

                            break;

                        case 2:

                            $this->ict->stopPump(1);

                            $this->ict->stopPump(2);

                            break;
                    }

                    //   Clear scheduleKey once schedule ends

                    unset(
                        $this->scheduleStarted[$scheduleKey]
                    );
                }
            }
        }
    }
}
