<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/IncomingCommandHandler.php';
require_once __DIR__ . '/Logger.php';

use PhpMqtt\Client\MqttClient as BaseMqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MQTTClient
{
    private $mqtt;

    private $config;

    private $commandHandler;

    private $ict;

    public function __construct($ict)
    {
        $this->ict = $ict;

        $this->config =
            require __DIR__ . '/../config/mqtt.php';

        $this->commandHandler =
            new IncomingCommandHandler(
                $this,
                $this->ict
            );
    }

    //   Connect to MQTT Broker

    public function connect()
    {
        echo "[MQTT] Connecting...\n";

        $this->mqtt = new BaseMqttClient(
            $this->config['host'],
            $this->config['port'],
            $this->config['client_id']
        );

        $connectionSettings =
            (new ConnectionSettings)

            ->setUseTls(true)

            ->setTlsSelfSignedAllowed(true)

            ->setTlsCertificateAuthorityFile(
                $this->config['ca_cert']
            )

            ->setTlsClientCertificateFile(
                $this->config['client_cert']
            )

            ->setTlsClientCertificateKeyFile(
                $this->config['client_key']
            )

            ->setKeepAliveInterval(
                $this->config['keepalive']
            );

        $this->mqtt->connect(
            $connectionSettings,
            true
        );

        Logger::info(
            'MQTT Connected Successfully'
        );
        echo "[MQTT] Connected successfully\n";
    }


    public function subscribe()
    {
        $topic =
            $this->config['subscribe_topic'];

        $this->mqtt->subscribe(
            $topic,
            function ($topic, $message) {

                echo "[MQTT] Incoming Message\n";

                echo "Topic:\n{$topic}\n\n";

                echo "Payload:\n{$message}\n";

                echo "=====================================\n";

                $json = json_decode($message, true);
                // print_r($json);
                if (!$json) {

                    echo "[MQTT] Invalid JSON payload\n";

                    return;
                }

                $this->commandHandler
                    ->process($json);
            },
            0
        );

        echo "[MQTT] Subscribed to topic: {$topic}\n";
    }


    public function publish($payload)
    {
        $topic =
            $this->config['publish_topic'];

        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
        );

        $this->mqtt->publish(
            $topic,
            $payloadJson,
            0
        );

        echo "[MQTT] Message Published\n";
    }

    public function loop()
    {
        $this->mqtt->loopOnce(
            true,
            100
        );
    }

    public function disconnect()
    {
        $this->mqtt->disconnect();

        echo "[MQTT] Disconnected\n";
    }
}
