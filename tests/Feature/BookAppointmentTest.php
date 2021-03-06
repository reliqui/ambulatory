<?php

namespace Ambulatory\Tests\Feature;

use Ambulatory\Booking;
use Ambulatory\Schedule;
use Ambulatory\MedicalForm;
use Illuminate\Support\Arr;
use Ambulatory\Availability;
use Ambulatory\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_can_not_book_an_appointment()
    {
        $schedule = factory(Schedule::class)->create();

        $this->postJson(route('ambulatory.book.appointment', $schedule->id), [])->assertStatus(401);
    }

    /** @test */
    public function get_available_time_slots_from_default_availability_with_preferred_date_in_the_schedule()
    {
        $this->signInAsPatient();

        // the default availability is Monday to Friday next week,
        // from 9:00-17:00 with estimated service time 15 minutes.
        $schedule = factory(Schedule::class)->create();

        // Request schedule availability time slots for today (default).
        // expected no time slots available.
        $this->getJson(route('ambulatory.book.appointment', $schedule->id))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);

        // Request schedule availability time slots for monday next week.
        // expected the time slots available.
        $this->getJson(route('ambulatory.book.appointment', [
                $schedule->id,
                'date='.$date = today()->parse('Monday next week'),
            ]))
            ->assertOk()
            ->assertExactJson([
                'data' => $schedule->availabilitySlots($date),
            ]);
    }

    /** @test */
    public function get_available_time_slots_from_custom_availability_with_preferred_date_in_the_schedule()
    {
        $this->signInAsPatient();

        // the custom availability is Monday next week,
        // interval time 9:00-11:00 and 15:00-19:00 with default estimated service time 15 minutes.
        $customAvailability = factory(Availability::class)->create();

        // Request schedule availability time slots for today (default).
        // expected no time slots available.
        $this->getJson(route('ambulatory.book.appointment', $customAvailability->schedule->id))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);

        // Request schedule availability time slots for monday next week.
        // expected the time slots available.
        $this->getJson(route('ambulatory.book.appointment', [
                $customAvailability->schedule->id,
                'date='.$date = today()->parse('Monday next week'),
            ]))
            ->assertOk()
            ->assertExactJson([
                'data' => $customAvailability->schedule->availabilitySlots($date),
            ]);
    }

    /** @test */
    public function preferred_date_time_is_required()
    {
        $medicalForm = factory(MedicalForm::class)->create();

        $schedule = factory(Schedule::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => '',
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time field is required.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function preferred_date_time_must_be_a_valid_date()
    {
        $medicalForm = factory(MedicalForm::class)->create();

        $schedule = factory(Schedule::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => 'not-a-date',
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time is not a valid date.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function preferred_date_time_must_be_a_date_after_or_equal_to_the_start_date_of_schedule()
    {
        $medicalForm = factory(MedicalForm::class)->create();
        // Schedule start date is Monday next week.
        $schedule = factory(Schedule::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => today()->toDateTimeString(),
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time must be a date after or equal to '.today()->parse('Monday next week').'.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function preferred_date_time_must_be_a_date_before_or_equal_to_the_end_date_of_schedule()
    {
        $medicalForm = factory(MedicalForm::class)->create();
        // Schedule end date is Friday next week.
        $schedule = factory(Schedule::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => today()->parse('Saturday next week')->toDateTimeString(),
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time must be a date before or equal to '.today()->parse('Friday next week').'.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function preferred_date_time_may_be_not_available_with_the_default_availability_slot_of_schedule()
    {
        $medicalForm = factory(MedicalForm::class)->create();
        // the default availability is Monday to Friday next week,
        // interval time 9:00-17:00 with with estimated service time 15 minutes.
        $schedule = factory(Schedule::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => today()->parse('Monday next week')->setTime(10, 27)->toDateTimeString(), // change default time
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time is not available.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function preferred_date_time_may_be_not_available_with_the_custom_availability_slot_of_schedule()
    {
        $medicalForm = factory(MedicalForm::class)->create();
        // the custom availability is Monday next week,
        // interval time 9:00-11:00 and 15:00-19:00 with default estimated service time 15 minutes.
        $customAvailability = factory(Availability::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $customAvailability->schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => today()->parse('Monday next week')->setTime(13, 00)->toDateTimeString(),
            ]))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time is not available.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function included_medical_form_is_required()
    {
        $this->signInAsPatient();

        $schedule = factory(Schedule::class)->create();

        $this->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes(''))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'medical_form_id' => ['The medical form field is required.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function included_medical_form_should_belong_to_the_authenticated_user()
    {
        $this->signInAsPatient();

        $schedule = factory(Schedule::class)->create();

        $medicalForm = factory(MedicalForm::class)->create();

        $this->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'medical_form_id' => ['The selected medical form is invalid.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function user_can_not_book_a_schedule_when_the_preferred_date_time_is_already_booked()
    {
        $booking = factory(Booking::class)->create();

        $this
            ->actingAs($booking->medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $booking->schedule_id), $this->bookingAttributes($booking->medicalForm->id))
            ->assertStatus(422)
            ->assertExactJson([
                'errors' => [
                    'preferred_date_time' => ['The preferred date time has already been booked.'],
                ],
                'message' => 'The given data was invalid.',
            ]);
    }

    /** @test */
    public function user_can_book_an_appointment_with_the_default_availability_of_doctor()
    {
        $medicalForm = factory(MedicalForm::class)->create();
        // the default availability is Monday to Friday next week,
        // interval time 9:00-17:00 with default estimated service time 15 minutes.
        $schedule = factory(Schedule::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => today()->parse('Monday next week')->setTime(9, 30)->toDateTimeString(),
            ]))
            ->assertStatus(201);

        $this->assertDatabaseHas('ambulatory_bookings', [
            'is_active' => true,
            'schedule_id' => $schedule->id,
            'medical_form_id' => $medicalForm->id,
        ]);
    }

    /** @test */
    public function user_can_book_an_appointment_with_the_custom_availability_of_doctor()
    {
        $medicalForm = factory(MedicalForm::class)->create();
        // the custom availability is Monday next week,
        // interval time 9:00-11:00 and 15:00-19:00 with default estimated service time 15 minutes.
        $customAvailability = factory(Availability::class)->create();

        $this
            ->actingAs($medicalForm->user, 'ambulatory')
            ->postJson(route('ambulatory.book.appointment', $customAvailability->schedule->id), $this->bookingAttributes($medicalForm->id, [
                'preferred_date_time' => today()->parse('Monday next week')->setTime(15, 00)->toDateTimeString(),
            ]))
            ->assertStatus(201);

        $this->assertDatabaseHas('ambulatory_bookings', [
            'is_active' => true,
            'schedule_id' => $customAvailability->schedule->id,
            'medical_form_id' => $medicalForm->id,
        ]);
    }

    /**
     * Booking attributes.
     *
     * @param  string  $medicalForm
     * @param  array  $overrides
     * @return array
     */
    protected function bookingAttributes($medicalForm, $overrides = [])
    {
        $attributes = factory(Booking::class)->raw(array_merge([
            'medical_form_id' => $medicalForm,
        ], $overrides));

        return Arr::except($attributes, ['schedule_id']);
    }
}
