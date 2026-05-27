<?php

return [

    'host' => '192.210.214.23',

    'port' => 8883,

    'client_id' => 'smartpump-client',

    'subscribe_topic' => 'VirtualTopic/mqtt/command/SmartPump-822438-BLK438B',

    'publish_topic' => 'VirtualTopic/mqtt/smartpump/incoming',

    'keepalive' => 60,

    'ca_cert' => __DIR__ . '/../certs/ca.crt',

    'client_cert' => __DIR__ . '/../certs/server.crt',

    'client_key' => __DIR__ . '/../certs/server.key',

];
