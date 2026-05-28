<?php

require_once __DIR__ . '/ICTController.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ScheduleManager.php';
require_once __DIR__ . '/CertificateManager.php';

class IncomingCommandHandler
{
    private $mqtt;

    private $ict;

    private $scheduleManager;

    private $certificateManager;

    public function __construct($mqtt, $ict)
    {
        $this->mqtt = $mqtt;
        $this->ict = $ict;
        $this->scheduleManager = new ScheduleManager();
        $this->certificateManager = new CertificateManager();
    }


    public function process($json)
    {
        if (empty($json['Commands'])) {

            echo "[COMMAND] No commands found\n";

            return;
        }

        foreach ($json['Commands'] as $cmd) {

            $commandType = $cmd['CommandType'] ?? '';

            $commandId = $cmd['CommandId'] ?? '';

            Logger::info(
                "Command Received: {$commandType}",
                'incoming.log'
            );
            echo "[COMMAND RECEIVED] {$commandType}\n";

            $success = false;

            switch ($commandType) {

                case 'smartpump/command#startpump':

                    $pumpNo =
                        $cmd['Parameter']['PumpNumber'] ?? 1;

                    $success =
                        $this->ict->startPump($pumpNo);

                    break;

                case 'smartpump/command#stoppump':

                    $pumpNo =
                        $cmd['Parameter']['PumpNumber'] ?? 1;

                    $success =
                        $this->ict->stopPump($pumpNo);

                    break;

                case 'smartpump/command#inhibitpump':

                    $inhibit =
                        $cmd['Parameter']['Inhibit']
                        ?? false;


                    if ($inhibit === true) {

                        $success =
                            $this->ict->enableInhibit();
                    } else {

                        $success =
                            $this->ict->disableInhibit();
                    }

                    break;

                case 'smartpump/command#muteunmutealarm':

                    $mute =
                        $cmd['Parameter']['Mute']
                        ?? false;

                    if ($mute === true) {

                        $success =
                            $this->ict->cancelAlarm();
                    } else {

                        $success =
                            $this->ict->resetAlarmMute();
                    }

                    break;

                case 'smartpump/command#setschedulestartconfig':

                    $schedules = $cmd['Parameter']['Schedules'] ?? [];

                    $success = $this->scheduleManager->saveStartSchedules($schedules);

                    echo "[SCHEDULE] Start Schedule Saved\n";

                    print_r($schedules);
                    break;

                case 'smartpump/command#setscheduleinhibitconfig':

                    $schedules =
                        $cmd['Parameter']['Schedules']
                        ?? [];

                    $success =
                        $this->scheduleManager
                        ->saveInhibitSchedules(
                            $schedules
                        );

                    break;

                case 'smartpump/command#getschedulestartconfig':

                    $schedules =
                        $this->scheduleManager
                        ->getStartSchedules();

                    // return ack payload

                    $responsePayload = [

                        "SenderId" =>
                        config('device.sender_id'),

                        "SensorId" =>
                        config('device.sensor_id'),

                        "Resourcepath" =>
                        config('device.resource_path'),

                        "EventId" =>
                        $commandId,

                        "EventType" =>
                        "smartpump/ack#getschedulestartconfig",

                        "Parameters" => [

                            "Schedules" =>
                            $schedules
                        ]
                    ];

                    $this->mqtt->publish(
                        $responsePayload
                    );

                    echo "[SCHEDULE] Returned Start Schedules\n";

                    continue 2;

                case 'smartpump/command#getscheduleinhibitconfig':

                    $schedules =
                        $this->scheduleManager
                        ->getInhibitSchedules();

                    $responsePayload = [

                        "SenderId" =>
                        config('device.sender_id'),

                        "SensorId" =>
                        config('device.sensor_id'),

                        "Resourcepath" =>
                        config('device.resource_path'),

                        "EventId" =>
                        $commandId,

                        "EventType" =>
                        "smartpump/ack#getscheduleinhibitconfig",

                        "Parameters" => [

                            "Schedules" =>
                            $schedules
                        ]
                    ];

                    $this->mqtt->publish(
                        $responsePayload
                    );

                    echo "[SCHEDULE] Returned Inhibit Schedules\n";

                    continue 2;

                case 'smartpump/command#renewcacert':

                    $certificate =

                        $cmd['Parameter']['CACertificate']
                        ?? '';

                    $success =

                        $this->certificateManager
                        ->renewCACertificate(
                            $certificate
                        );

                    echo "[CERT] CA Certificate Renewed\n";

                    break;
                case 'smartpump/command#renewclientcert':

                    $certificate =

                        $cmd['Parameter']['ClientCertificate']
                        ?? '';

                    $privateKey =

                        $cmd['Parameter']['ClientKey']
                        ?? '';

                    $success =

                        $this->certificateManager
                        ->renewClientCertificate(

                            $certificate,

                            $privateKey
                        );

                    echo "[CERT] Client Certificate Renewed\n";

                    break;

                default:

                    echo "[COMMAND] Unknown command\n";

                    $success = false;

                    break;
            }



            $ackType = str_replace(
                'command',
                'ack',
                $commandType
            );


            $ackPayload = [

                "SenderId" =>
                config('device.sender_id'),

                "SensorId" =>
                config('device.sensor_id'),

                "Resourcepath" =>
                config('device.resource_path'),

                "EventId" => $commandId,

                "EventType" => $ackType,

                "Parameters" => [

                    "Time" => date('Y-m-d H:i:s'),

                    "SensorStatus" => "online",

                    "Success" => $success,

                    "Message" => $success
                        ? "Processed Successfully"
                        : "Processing Failed"
                ]
            ];


            $this->mqtt->publish($ackPayload);

            echo "[ACK SENT] {$commandId}\n";
            Logger::info(
                "ACK Sent: {$commandId}",
                'outgoing.log'
            );
        }
    }
}
