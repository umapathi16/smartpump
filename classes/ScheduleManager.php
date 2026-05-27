<?php

class ScheduleManager
{
    private $startScheduleFile;

    private $inhibitScheduleFile;

    public function __construct()
    {
        $this->startScheduleFile =
            __DIR__
            . '/../storage/schedules/start_schedule.json';

        $this->inhibitScheduleFile =
            __DIR__
            . '/../storage/schedules/inhibit_schedule.json';
    }

    /*
    |--------------------------------------------------------------------------
    | Save Start Schedules
    |--------------------------------------------------------------------------
    */

    public function saveStartSchedules($schedules): bool
    {
        return file_put_contents(
            $this->startScheduleFile,
            json_encode(
                $schedules,
                JSON_PRETTY_PRINT
            )
        ) !== false;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Start Schedules
    |--------------------------------------------------------------------------
    */

    public function getStartSchedules(): array
    {
        if (!file_exists($this->startScheduleFile)) {

            return [];
        }

        $content =
            file_get_contents(
                $this->startScheduleFile
            );

        return json_decode($content, true)
            ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Save Inhibit Schedules
    |--------------------------------------------------------------------------
    */

    public function saveInhibitSchedules($schedules): bool
    {
        return file_put_contents(
            $this->inhibitScheduleFile,
            json_encode(
                $schedules,
                JSON_PRETTY_PRINT
            )
        ) !== false;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Inhibit Schedules
    |--------------------------------------------------------------------------
    */

    public function getInhibitSchedules(): array
    {
        if (!file_exists($this->inhibitScheduleFile)) {

            return [];
        }

        $content =
            file_get_contents(
                $this->inhibitScheduleFile
            );

        return json_decode($content, true)
            ?? [];
    }
}
