<?php

namespace Database\Factories;

use App\Models\LabourRecord;
use App\Models\LabRequest;
use App\Models\OrganisationAndHmo;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $paymentMethods = ['CASH', 'TRANSFER', 'HMO', 'ORGANISATION', 'WALLET'];
        $statuses = ['CREATED', 'PENDING', 'COMPLETED'];
        $amountPayable = $this->faker->randomFloat(2, 500, 5000);
        $refundAmount = $this->faker->boolean(20) ? $this->faker->randomFloat(2, 0, $amountPayable) : null;
        $amountPaid = $refundAmount ? $amountPayable - $refundAmount : $amountPayable;

        $payableModels = [
            Treatment::class,
            Prescription::class,
            LabRequest::class,
            LabourRecord::class,
            OrganisationAndHmo::class,
        ];

        // Randomly select a payable model and get an instance
        $payableType = $this->faker->randomElement($payableModels);
        $payableInstance = $payableType::inRandomOrder()->first() ?? $payableType::factory()->create();

        // Generate unique transaction reference
        do {
            $transactionReference = strtoupper(Str::random(15));
        } while (Payment::where('transaction_reference', $transactionReference)->exists());

        $status = $this->faker->randomElement($statuses);

        // If status is CREATED, payment_method should be null
        $paymentMethod = $status === 'CREATED' ? null : $this->faker->randomElement($paymentMethods);

        // is_used should be true only when status is COMPLETED
        $isUsed = $status === 'COMPLETED';

        return [
            'amount_payable' => number_format($amountPayable, 2, '.', ''),
            'refund_amount' => $refundAmount ? number_format($refundAmount, 2, '.', '') : null,
            'amount' => number_format($amountPaid, 2, '.', ''),
            'transaction_reference' => $transactionReference,
            'reference' => $this->faker->boolean(80) ? strtoupper(Str::random(10)) : null,
            'bank_transfer_to' => $this->faker->boolean(50) ? $this->faker->bankAccountNumber : null,
            'is_confirmed' => $this->faker->boolean(70),
            'payment_method' => $paymentMethod,
            'type' => $this->faker->randomElement(['TREATMENT', 'CONSULTATION', 'MEDICATION']),
            'remark' => $this->faker->sentence(),
            'status' => $status,
            'history' => json_encode([
                [
                    'action' => 'ADD',
                    'performed_by' => User::inRandomOrder()->first()->toArray(),
                    'date' => $this->faker->dateTimeThisYear(),
                ],
            ]),
            'customer_name' => $this->faker->name,
            'patient_id' => Patient::inRandomOrder()->first()->id ?? Patient::factory()->create()->id,

            // Polymorphic relationship fields
            'payable_type' => $payableType,
            'payable_id' => $payableInstance->id,
            'is_used' => $isUsed,

            // Removed organisation_hmo_id
            'added_by_id' => User::inRandomOrder()->first()->id ?? User::factory()->create()->id,
            'confirmed_by_id' => $this->faker->boolean(70) ? User::inRandomOrder()->first()->id : null,
            'last_updated_by_id' => User::inRandomOrder()->first()->id ?? null,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Configure the model factory to ensure polymorphic relationships are properly set
     */
    public function configure()
    {
        return $this->afterCreating(function (Payment $payment) {
            // Additional logic if needed after payment creation
        });
    }
}
