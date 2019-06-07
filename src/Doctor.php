<?php

namespace Reliqui\Ambulatory;

use RRule\RRule;

class Doctor extends AmbulatoryModel
{
    use HasUuid, HasSlug;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reliqui_doctors';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'working_hours_rule',
    ];

    /**
     * Get the fields for generating the slug.
     *
     * @var array
     */
    protected static $slugFieldsFrom = ['full_name'];

    /**
     * The specializations the doctor belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function specializations()
    {
        return $this->belongsToMany(Specialization::class, 'reliqui_doctors_specializations', 'doctor_id', 'specialization_id');
    }

    /**
     * Doctor account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Doctor schedules.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'doctor_id');
    }

    /**
     * Gett all the appointments for the doctor.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function appointments()
    {
        return $this->hasManyThrough(Booking::class, Schedule::class);
    }

    /**
     * Get the default working hours of doctor.
     *
     * @return array
     */
    public function getWorkingHours()
    {
        $rfc = new RRule($this->working_hours_rule);

        $rule = $rfc->getRule();

        return collect(explode(',', $rule['BYDAY']))->map(function ($day) use ($rule) {
            return [
                'type' => 'wday',
                'intervals' => [
                    'from' => date_format($rule['DTSTART'], 'H:i'),
                    'to' => date_format($rule['UNTIL'], 'H:i'),
                ],
                'wday' => $day,
            ];
        })->toArray();
    }
}