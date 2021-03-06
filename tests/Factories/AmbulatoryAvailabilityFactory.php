<?php

use Ambulatory\Schedule;
use Ambulatory\Availability;
use Faker\Generator as Faker;

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Availability::class, function (Faker $faker) {
    // always set date to Monday next week
    $date = today()->parse('Monday next week');

    return [
        'schedule_id' => factory(Schedule::class),
        'type' => 'date',
        'intervals' => [
            [
                'from' => $date->setTime(9, 00)->format('H:i'),
                'to' => $date->setTime(11, 00)->format('H:i'),
            ],
            [
                'from' => $date->setTime(15, 00)->format('H:i'),
                'to' => $date->setTime(19, 00)->format('H:i'),
            ],
        ],
        'date' => $date->toDateString(),
    ];
});
