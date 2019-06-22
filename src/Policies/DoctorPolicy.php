<?php

namespace Reliqui\Ambulatory\Policies;

use Reliqui\Ambulatory\User;
use Reliqui\Ambulatory\Doctor;
use Illuminate\Auth\Access\HandlesAuthorization;

class DoctorPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user may update doctor profile.
     *
     * @param  User  $user
     * @param  Doctor  $doctor
     * @return bool
     */
    public function update(User $user, Doctor $doctor)
    {
        return $user->id === $doctor->user_id;
    }
}