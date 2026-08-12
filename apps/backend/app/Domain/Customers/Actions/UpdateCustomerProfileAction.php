<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Name/email/locale live on the User (see docs/DATABASE_SCHEMA.md);
 * preferences/notification_preferences live on Customer. One request
 * updates both, in a single transaction.
 */
class UpdateCustomerProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        DB::transaction(function () use ($customer, $data) {
            $userFields = array_intersect_key($data, array_flip(['name', 'email', 'locale']));
            if ($userFields !== []) {
                $customer->user->update($userFields);
            }

            $customerFields = array_intersect_key($data, array_flip(['preferences', 'notification_preferences']));
            if ($customerFields !== []) {
                $customer->update($customerFields);
            }
        });

        return $customer->fresh(['user']);
    }
}
