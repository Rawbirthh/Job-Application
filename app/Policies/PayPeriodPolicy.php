<?php

namespace App\Policies;

use App\Models\PayPeriod;
use App\Models\User;

class PayPeriodPolicy
{
    /**
     * Determine whether the actor may view any pay periods.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('pay_periods.view');
    }

    /**
     * Determine whether the actor may view the pay period.
     */
    public function view(User $actor, PayPeriod $payPeriod): bool
    {
        return $actor->hasPermission('pay_periods.view');
    }

    /**
     * Determine whether the actor may create new pay periods.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('pay_periods.manage');
    }

    /**
     * Determine whether the actor may update the pay period.
     */
    public function update(User $actor, PayPeriod $payPeriod): bool
    {
        return $actor->hasPermission('pay_periods.manage');
    }

    /**
     * Determine whether the actor may delete the pay period.
     */
    public function delete(User $actor, PayPeriod $payPeriod): bool
    {
        return $actor->hasPermission('pay_periods.manage');
    }
}
