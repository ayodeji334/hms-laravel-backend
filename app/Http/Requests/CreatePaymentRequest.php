<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'is_patient' => 'required|boolean',
            'payment_type' => [
                'required',
                Rule::in(['SERVICE', 'DEPOSIT']),
            ],
            'payment_method' => [
                'required',
                Rule::in(['HMO', 'CASH', 'TRANSFER', 'ACCOUNT-BALANCE']),
            ],
            'patient_id' => [
                'sometimes',
                'exclude_if:is_patient,false',
                'integer',
            ],
            'service_id' => [
                'sometimes',
                'exclude_if:payment_type,DEPOSIT',
                'integer',
            ],
            'hmo_id' => [
                'sometimes',
                'exclude_if:payment_method,hmo',
                'integer',
            ],
            'amount' => [
                'sometimes',
                'exclude_if:payment_type,deposit',
                'numeric',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'customer_name' => [
                'sometimes',
                'exclude_if:is_patient,true',
                'string',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_patient.required' => 'Is the person a patient field is required',
            'is_patient.boolean' => 'Is the person a patient field should be true or false',

            'payment_type.required' => 'Payment type field is required',
            'payment_type.in' => 'Payment type contains invalid data',

            'payment_method.required' => 'Payment method field is required',
            'payment_method.in' => 'Payment method contains invalid data',

            'patient_id.exclude_if' => 'Patient Detail is required when is patient is true',
            'patient_id.integer' => 'Invalid Patient Detail',

            'service_id.exclude_if' => 'Service Detail is required when payment type is service',
            'service_id.integer' => 'Invalid Service Detail',

            'hmo_id.exclude_if' => 'Health Management Organization Detail field is required when payment method is HMO',
            'hmo_id.integer' => 'Health Management Organization Detail contains invalid data',

            'amount.exclude_if' => 'Amount is required when payment type is deposit',
            'amount.numeric' => 'Amount is not a valid number',
            'amount.regex' => 'Amount is not a valid monetary value',

            'customer_name.exclude_if' => 'Customer Name field is required when is patient is false',
            'customer_name.string' => 'Customer Name should be a string',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'Patient Detail',
            'service_id' => 'Service Detail',
            'hmo_id' => 'Health Management Organization Detail',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert string booleans to actual booleans if needed
        $this->merge([
            'is_patient' => filter_var($this->is_patient, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
