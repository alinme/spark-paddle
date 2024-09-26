<?php

namespace Spark\Http\Controllers;

class UpdatePaymentMethodController
{
    use RetrievesBillableModels;

    /**
     * Generate a Paddle pay link that allows the billable to update their payment information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke()
    {
        return response()->json([
            'transaction_id' => $this->billable()->subscription()->paymentMethodUpdateTransaction()['id'],
        ]);
    }
}
