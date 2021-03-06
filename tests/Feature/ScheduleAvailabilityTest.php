<?php

namespace Ambulatory\Tests\Feature;

use Carbon\Carbon;
use Ambulatory\Schedule;
use Illuminate\Support\Arr;
use Ambulatory\Availability;
use Ambulatory\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScheduleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guests_cannot_add_availabilities_to_schedule()
    {
        $schedule = factory(Schedule::class)->create();

        $this->postJson(route('ambulatory.schedules.availability', $schedule->id))->assertStatus(401);
    }

    /** @test */
    public function only_doctor_can_create_availability_to_the_schedule()
    {
        $schedule = factory(Schedule::class)->create();

        $this->signInAsPatient();
        $this->postJson(route('ambulatory.schedules.availability', $schedule->id))->assertStatus(403);

        $this->signInAsAdmin();
        $this->postJson(route('ambulatory.schedules.availability', $schedule->id))->assertStatus(403);
    }

    /** @test */
    public function a_doctor_cannot_create_availability_schedule_of_others()
    {
        $this->signInAsDoctor();

        $schedule = factory(Schedule::class)->create();

        $this->postJson(route('ambulatory.schedules.availability', $schedule->id), factory(Availability::class)->raw())
            ->assertStatus(403);

        $this->assertDatabaseMissing('ambulatory_availabilities', ['schedule_id' => $schedule->id]);
    }

    /** @test */
    public function a_doctor_can_create_availability_to_their_schedule()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $attributes = $this->dummyData($date))
            ->assertStatus(201);

        $this->assertDatabaseHas('ambulatory_availabilities', [
            'type' => 'date',
            'intervals' => json_encode($attributes['intervals']),
        ]);
    }

    /** @test */
    public function a_doctor_can_not_update_availability_schedule_of_others()
    {
        $this->signInAsDoctor();

        $availability = factory(Availability::class)->create();

        $this->patchJson(route('ambulatory.availabilities.update', $availability->id), factory(Availability::class)->raw())
        ->assertStatus(403)
        ->assertExactJson([
            'message' => 'This action is unauthorized.',
        ]);
    }

    /** @test */
    public function a_doctor_can_update_availability_to_their_schedule()
    {
        $availability = factory(Availability::class)->create();

        $date = $this->pickDateBetween($availability->schedule->start_date_time, $availability->schedule->end_date_time);

        $this
            ->actingAs($availability->schedule->doctor->user, 'ambulatory')
            ->patchJson(route('ambulatory.availabilities.update', $availability->id), $attributes = $this->dummyData($date, [
                'intervals' => [
                    [
                        'from' => '13:00',
                        'to' => '17:00',
                    ],
                ],
            ]))
            ->assertOk();

        $this->assertDatabaseHas('ambulatory_availabilities', [
            'type' => 'date',
            'intervals' => json_encode($attributes['intervals']),
        ]);
    }

    /** @test */
    public function availability_intervals_is_required()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this
            ->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $this->dummyData($date, [
                'intervals' => [],
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'intervals' => ['The intervals field is required.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function availability_intervals_is_an_array()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this
            ->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $this->dummyData($date, [
                'intervals' => 'not-an-array',
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'intervals' => ['The intervals must be an array.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function availability_intervals_from_time_is_required()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this
            ->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $this->dummyData($date, [
                'intervals' => [
                    [
                        'from' => '',
                        'to' => today()->format('H:i'),
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'intervals.0.from' => ['The intervals.0.from field is required.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function availability_intervals_to_time_is_required()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this
            ->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $this->dummyData($date, [
                'intervals' => [
                    [
                        'from' => today()->format('H:i'),
                        'to' => '',
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'intervals.0.to' => ['The intervals.0.to field is required.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function availability_date_is_required()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this
            ->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $this->dummyData($date, [
                'date' => '',
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'date' => ['The date field is required.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function availability_date_is_must_be_a_valid_date()
    {
        $schedule = factory(Schedule::class)->create();

        $date = $this->pickDateBetween($schedule->start_date_time, $schedule->end_date_time);

        $this
            ->actingAs($schedule->doctor->user, 'ambulatory')
            ->postJson(route('ambulatory.schedules.availability', $schedule->id), $this->dummyData($date, [
                'date' => 'not-a-date',
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'date' => ['The date is not a valid date.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /**
     * The dummy data of availability.
     *
     * @param  string  $availableDate
     * @param  array  $overrides
     * @return array
     */
    protected function dummyData($availableDate, $overrides = [])
    {
        $date = Carbon::parse($availableDate);

        $attributes = factory(Availability::class)->raw(array_merge([
            'intervals' => [
                [
                    'from' => $date->createFromTime(9, 00)->format('H:i'),
                    'to' => $date->createFromTime(17, 00)->format('H:i'),
                ],
            ],
            'date' => $date->format('Y-m-d'),
        ], $overrides));

        return Arr::except($attributes, ['schedule_id', 'type']);
    }
}
