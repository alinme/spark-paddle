<?php

namespace Spark\Contracts\Actions;

interface GeneratesCheckoutSessions
{
    /**
     * Generate a checkout session for a new subscription.
     *
     * @param  \Laravel\Paddle\Billable  $billable
     * @param  string  $plan
     * @return \Laravel\Paddle\Checkout
     */
    public function generateCheckoutSession($billable, $plan, array $options = []);
}
