<?php

require_once __DIR__ . '/EventConfig.php';
class EventPublisher
{
    private $mqtt;

    public function __construct($mqtt)
    {
        $this->mqtt = $mqtt;
    }


    public function publish($eventType, $parameters = [])
    {
        $config = EventConfig::get($eventType); 
        $senderId =

            $parameters['SenderId']
            ??

            config('device.sender_id');

        $sensorId =

            $parameters['SensorId']
            ??

            config('device.sensor_id');

        $resourcePath =

            $parameters['Resourcepath']
            ??

            config('device.resource_path');

        

        unset(

            $parameters['SenderId'],

            $parameters['SensorId'],

            $parameters['Resourcepath']
        );

        $payload = [

            "SenderId" =>
            $senderId,

            "SensorId" =>
            $sensorId,

            "Resourcepath" =>
            $resourcePath,

            "EventId" =>
            uniqid('EVT-'),

            "EventType" =>
            $eventType,

            "Parameters" => array_merge(

                [

                    "Time" =>
                    date('Y-m-d H:i:s'),

                    "Severity" =>
                    $config['Severity'],

                    "SensorStatus" =>
                    "online",

                    "FaultCode" =>
                    $config['FaultCode'],
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
