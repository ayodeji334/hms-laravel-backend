<?php

namespace Database\Factories;

use App\Models\OrganisationAndHmo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganisationAndHmoPayment>
 */
class OrganisationAndHmoPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalDue = $this->faker->randomFloat(2, 1000, 100000);
        $amountPaid = $this->faker->randomFloat(2, 500, $totalDue);
        $outstandingBalance = $totalDue - $amountPaid;

        return [
            'hmo_id' => OrganisationAndHmo::inRandomOrder()->value('id') ?? OrganisationAndHmo::factory(),
            'added_by_id' => User::inRandomOrder()->value('id') ?? User::factory(),
            'last_updated_by_id' => User::inRandomOrder()->value('id') ?? User::factory(),
            'total_due' => (string)$totalDue,
            'amount_paid' => (string)$amountPaid,
            'outstanding_balance' => (string)$outstandingBalance,
            'transaction_reference' => strtoupper(Str::random(10)),
            'payment_date' => $this->faker->date(),
            'history' => json_encode([
                [
                    'action' => 'ADD',
                    'performed_by' => $this->faker->name(),
                    'date' => now()->toDateTimeString(),
                ]
            ]),
            'payment_method' => $this->faker->randomElement(['CASH', 'TRANSFER']),
        ];
    }
}
