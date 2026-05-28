<?php

class EventConfig
{
    public static function get($eventType): array {

        $events = [

            // System Reading

            'Waterpump/system#reading' => [

                'Severity' => 6,

                'FaultCode' => ''
            ],

            //    Pump Reading

            'Waterpump/pump#reading' => [

                'Severity' => 6,

                'FaultCode' => ''
            ],

            // Pump Started

            'Waterpump/pump#started' => [

                'Severity' => 6,

                'FaultCode' => ''
            ],

            //  Pump Stopped

            'Waterpump/pump#stopped' => [

                'Severity' => 6,

                'FaultCode' => ''
            ],

            //    Inhibit Activated

            'Waterpump/system#inhibitactivated' => [

                'Severity' => 4,

                'FaultCode' => 'SHWPOE101001'
            ],

            //    Pump Trip Failure

            'Waterpump/pump#tripfailure' => [

                'Severity' => 2,

                'FaultCode' => 'SHWPOE101002'
            ],

            // Power Failure

            'Waterpump/system#powerfailure' => [

                'Severity' => 2,

                'FaultCode' =>
                'SH-WP-OE-101003'
            ],

            // Emergency Alarm

            'Waterpump/system#emergencyalarm' => [

                'Severity' => 2,

                'FaultCode' => 'SH-WP-OE-101004'
            ],

            //  Overflow

            'Waterpump/system#overflow' => [

                'Severity' => 3,

                'FaultCode' => 'SH-WP-OE-101005'
            ],

            //    Critically Low

            'Waterpump/system#criticallylow' => [

                'Severity' => 3,

                'FaultCode' => 'SH-WP-OE-101006'
            ],

            // Clear Event

            'Waterpump/system#clear' => [

                'Severity' => 5,

                'FaultCode' => ''
            ]
        ];

        return $events[$eventType]
            ?? [

                'Severity' => 6,

                'FaultCode' => ''
            ];
    }
}
