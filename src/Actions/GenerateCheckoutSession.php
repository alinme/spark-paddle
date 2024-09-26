<?php

namespace Spark\Actions;

use Spark\Contracts\Actions\GeneratesCheckoutSessions;
use Spark\Spark;

class GenerateCheckoutSession implements GeneratesCheckoutSessions
{
    /**
     * {@inheritDoc}
     */
    public function generateCheckoutSession($billable, $plan, array $options = [])
    {
        $type = $billable->sparkConfiguration('type');

        if (Spark::chargesPerSeat($type)) {
            $session = $billable->subscribe([$plan => Spark::seatCount($type, $billable)]);
        } else {
            $session = $billable->subscribe($plan);
        }

        return $session;
    }
}
