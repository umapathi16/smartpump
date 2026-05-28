<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

/*
|--------------------------------------------------------------------------
| Load MQTT Config
|--------------------------------------------------------------------------
*/

$config =
    require __DIR__ . '/../config/mqtt.php';

/*
|--------------------------------------------------------------------------
| Get Command
|--------------------------------------------------------------------------
*/

$command = $argv[1] ?? null;

if (!$command) {

    echo "\nUsage:\n\n";

    echo "php command_simulator.php startpump 1\n";
    echo "php command_simulator.php startpump 2\n";

    echo "php command_simulator.php stoppump 1\n";
    echo "php command_simulator.php stoppump 2\n";

    echo "php command_simulator.php inhibitpump on\n";
    echo "php command_simulator.php inhibitpump off\n";

    echo "php command_simulator.php muteunmutealarm on\n";
    echo "php command_simulator.php muteunmutealarm off\n";

    echo "php command_simulator.php setschedulestartconfig\n";
    echo "php command_simulator.php getschedulestartconfig\n";

    echo "php command_simulator.php setscheduleinhibitconfig\n";
    echo "php command_simulator.php getscheduleinhibitconfig\n";



    exit;
}

/*
|--------------------------------------------------------------------------
| Build SmartFM Command Type
|--------------------------------------------------------------------------
*/

$commandType =
    "smartpump/command#{$command}";

/*
|--------------------------------------------------------------------------
| MQTT Client
|--------------------------------------------------------------------------
*/

$client = new MqttClient(
    $config['host'],
    $config['port'],
    'command-simulator'
);

/*
|--------------------------------------------------------------------------
| TLS Settings
|--------------------------------------------------------------------------
*/

$connectionSettings =
    (new ConnectionSettings)

    ->setUseTls(true)

    ->setTlsSelfSignedAllowed(true)

    ->setTlsCertificateAuthorityFile(
        $config['ca_cert']
    )

    ->setTlsClientCertificateFile(
        $config['client_cert']
    )

    ->setTlsClientCertificateKeyFile(
        $config['client_key']
    )

    ->setKeepAliveInterval(
        $config['keepalive']
    );

/*
|--------------------------------------------------------------------------
| Connect
|--------------------------------------------------------------------------
*/

echo "[SIMULATOR] Connecting...\n";

$client->connect(
    $connectionSettings,
    true
);

echo "[SIMULATOR] Connected\n";

/*
|--------------------------------------------------------------------------
| Dynamic Parameters
|--------------------------------------------------------------------------
*/

$parameter = [];

switch ($command) {

    /*
    |--------------------------------------------------------------------------
    | Pump Start / Stop
    |--------------------------------------------------------------------------
    */

    case 'startpump':

    case 'stoppump':

        $pumpNo =
            $argv[2] ?? 1;

        $parameter = [

            "PumpNumber" =>
            (int) $pumpNo,

            "Mode" => 1
        ];

        break;

    /*
    |--------------------------------------------------------------------------
    | Inhibit
    |--------------------------------------------------------------------------
    */

    case 'inhibitpump':

        $mode =
            strtolower(
                $argv[2] ?? 'on'
            );

        $parameter = [

            "Inhibit" =>
            $mode === 'on'
        ];

        break;

    case 'muteunmutealarm':

        $mode =
            strtolower(
                $argv[2] ?? 'on'
            );

        $parameter = [

            "Mute" =>
            $mode === 'on'
        ];

        break;

    /*
    |--------------------------------------------------------------------------
    | Set Schedule Start Config
    |--------------------------------------------------------------------------
    */

    case 'setschedulestartconfig':

        $parameter = [

            "Schedules" => [

                [

                    "StartDate" => date('Y-m-d'),

                    "EndDate" => date('Y-m-d'),

                    "StartTime" => date(
                        'H:i:s',
                        strtotime('+30 seconds')
                    ),

                    "EndTime" => date(
                        'H:i:s',
                        strtotime('+2 minutes')
                    ),

                    "PumpMode" => 1,

                    "StartStandbyIfDutyTrip" => true
                ]
            ]
        ];

        break;

    /*
    |--------------------------------------------------------------------------
    | Get Schedule Start Config
    |--------------------------------------------------------------------------
    */

    case 'getschedulestartconfig':

        $parameter = [];

        break;

    /*
    |--------------------------------------------------------------------------
    | Set Schedule Inhibit Config
    |--------------------------------------------------------------------------
    */

    case 'setscheduleinhibitconfig':

        $parameter = [

            "Schedules" => [

                [

                    "StartDate" => date('Y-m-d'),

                    "EndDate" => date('Y-m-d'),

                    "StartTime" => date(
                        'H:i:s',
                        strtotime('+30 seconds')
                    ),

                    "EndTime" => date(
                        'H:i:s',
                        strtotime('+2 minutes')
                    ),

                    "PumpMode" => 1,

                    "StartPumpIfRoofTankLowLow" => true,

                    "MuteTransferPumpAlarm" => true,

                    "RunPumpToMakeRoofTankHighBeforeReleaseAlarm" => true
                ]
            ]
        ];

        break;

    /*
    |--------------------------------------------------------------------------
    | Get Schedule Inhibit Config
    |--------------------------------------------------------------------------
    */

    case 'getscheduleinhibitconfig':

        $parameter = [];

        break;

    default:

        echo "[SIMULATOR] Unknown command\n";

        exit;
}

/*
|--------------------------------------------------------------------------
| Payload
|--------------------------------------------------------------------------
*/

$payload = [

    "SensorId" =>
    "SmartPump-822438-BLK438B-TransferPump.Pump1",

    "Time" =>
    date('Y-m-d H:i:s'),

    "Commands" => [

        [

            "CommandId" =>
            uniqid('CMD-'),

            "CommandType" =>
            $commandType,

            "Parameter" =>
            $parameter
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| Publish Command
|--------------------------------------------------------------------------
*/

echo "[SIMULATOR] Publishing: {$commandType}\n";

$client->publish(
    $config['subscribe_topic'],
    json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
    ),
    0
);

echo "[SIMULATOR] Command Published\n";

/*
|--------------------------------------------------------------------------
| Disconnect
|--------------------------------------------------------------------------
*/

$client->disconnect();

echo "[SIMULATOR] Finished\n";
