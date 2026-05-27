<?php

class EventPublisher
{
    private $mqtt;

    public function __construct($mqtt)
    {
        $this->mqtt = $mqtt;
    }

    /*
    |--------------------------------------------------------------------------
    | Publish Event
    |--------------------------------------------------------------------------
    */

    public function publish(
        $eventType,
        $parameters = []
    ) {

        $payload = [

            "SenderId" =>
                config('device.sender_id'),

            "SensorId" =>
                config('device.sensor_id'),

            "Resourcepath" =>
                config('device.resource_path'),

            "EventId" =>
                uniqid('EVT-'),

            "EventType" =>
                $eventType,

            "Parameters" => array_merge(

                [

                    "Time" =>
                        date('Y-m-d H:i:s'),

                    "Severity" => 6,

                    "SensorStatus" =>
                        "online"
                ],

                $parameters
            )
        ];

        $this->mqtt->publish(
            $payload
        );

        echo "[MQTT EVENT PUBLISHED] {$eventType}\n";
    }
}