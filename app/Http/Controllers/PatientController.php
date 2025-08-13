<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\UserTypes;
use App\Imports\PatientImport;
use App\Models\FamilyRelationship;
use App\Models\OrganisationAndHmo;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Wallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Throwable;

class PatientController extends Controller
{
    protected $fileAttachmentService;
    protected $labRequestService;

    public function create(Request $request)
    {
        $request->validate([
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'matriculation_number' => 'nullable|string|unique:patients',
            'insurance_number' => 'required_with:insurance_provider',
            'insurance_provider' => 'nullable|integer|exists:organisation_and_hmos,id',
            'middlename' => 'nullable|string',
            'room_number' => 'exclude_unless:type,STUDENT|string',
            'contact_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'phone_number' => 'nullable|string|unique:patients',
            'state_of_origin' => 'nullable|string',
            'lga' => 'nullable|string',
            'occupation' => 'nullable|string',
            'level' => 'nullable|exclude_unless:type,STUDENT|numeric',
            'age' => 'nullable|numeric|max:100',
            'hall_of_residence' => 'nullable|exclude_unless:type,STUDENT|string',
            'nationality' => 'required|string',
            'type' => 'required|in:STUDENT,STAFF,OTHERS,STAFF_FAMILY',
            'marital_status' => 'required|in:SINGLE,MARRIED',
            'gender' => 'required|string',
            'date_of_birth' => 'nullable|date',
            'religion' => 'required|string',
            'department' => 'nullable|exclude_unless:type,STUDENT,STAFF|string',
            'title' => 'required|string',
            'tribe' => 'required|string',
            'billing_responsibility' => 'nullable|boolean',
            'family_member' => 'nullable|integer',
            'family_member_relationship' => 'nullable|required_with:family_member|in:FATHER,MOTHER,SPOUSE,CHILD,SIBLINGS',
            'staff_number' => 'exclude_unless:type,STAFF|string|unique:patients,staff_number',
            'payment_reference' => 'required|string|exists:payments,transaction_reference',
            'next_of_kin' => 'nullable|array',
            'email' => 'nullable|email|unique:patients,email',
        ]);

        DB::beginTransaction();

        try {
            $staff = Auth::user();
            $insuranceProvider = null;
            $patientDependent = null;

            // Check the insurance number already exist
            if (!empty($request['insurance_provider'])) {
                $patientWithSameInsuranceNumber = Patient::where('organisation_hmo_id',  $request['insurance_provider'])
                    ->where('insurance_number', $request['insurance_number'])->first();

                if ($patientWithSameInsuranceNumber) {
                    return response()->json(['message' => 'The insurance number already assigned to another patient'], 400);
                }
            }

            // Check if there is a family member dependency
            if (!empty($request['family_member'])) {
                $patientDependent = Patient::find($request['family_member']);
                if (!$patientDependent) {
                    return response()->json(['message' => 'The family member detail provided does not match any record'], 400);
                }
            }

            $payment = Payment::where("transaction_reference", $request['payment_reference'])->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'The payment detail does not exist. Please try again later',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Ensure it's completed
            if ($payment->status !== PaymentStatus::COMPLETED->value) {
                return response()->json([
                    'message' => 'The payment has not been confirmed yet.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($payment->type !== "ACCOUNT") {
                return response()->json([
                    'message' => 'The payment is not for account registration.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($payment->is_used || $payment->patient_id !== null) {
                return response()->json([
                    'message' => "This payment reference has already been used for another patient",
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $newPatient = new Patient();
            $newPatient->department = $request['department'];
            $newPatient->firstname = strtolower(trim($request['firstname']));
            $newPatient->middlename = strtolower(trim($request['middlename']));
            $newPatient->lastname = strtolower(trim($request['lastname']));
            $newPatient->email = strtolower(trim($request['email'])) ?? null;
            $newPatient->contact_address = strtolower(trim($request['contact_address']));
            $newPatient->phone_number = trim($request['phone_number']);
            $newPatient->religion = $request['religion'];
            $newPatient->staff_number = $request['staff_number'] ?? null;
            $newPatient->state_of_origin = trim($request['state_of_origin']);
            $newPatient->lga = trim($request['lga']);
            $newPatient->organisationHmo()->associate($insuranceProvider);
            $newPatient->type = $request['type'];
            $newPatient->title = $request['title'];
            $newPatient->tribe = $request['tribe'];
            $newPatient->hall_of_residence = $request['hall_of_residence'] ?? null;
            $newPatient->room_number = $request['room_number'] ?? null;
            $newPatient->insurance_number = $request['insurance_number'] ?? null;
            $newPatient->insurance_provider_id = ($insuranceProvider !== null)
                ? $insuranceProvider->id
                : null;
            $newPatient->age = trim($request['age']);
            $newPatient->matriculation_number = $request['matriculation_number'];
            $newPatient->level = $request['level'];
            $newPatient->gender = $request['gender'];
            $newPatient->nationality = trim($request['nationality']);
            $newPatient->marital_status = $request['marital_status'];
            $newPatient->occupation = trim($request['occupation']);
            $newPatient->permanent_address = strtolower(trim($request['permanent_address']));
            $newPatient->next_of_kin_firstname = strtolower(trim($request['next_of_kin']['firstname']));
            $newPatient->next_of_kin_lastname = strtolower(trim($request['next_of_kin']['lastname']));
            $newPatient->next_of_kin_contact_address = strtolower(trim($request['next_of_kin']['contact_address']));
            $newPatient->next_of_kin_phone_number = trim($request['next_of_kin']['phone_number']);
            $newPatient->next_of_kin_relationship = trim($request['next_of_kin']['relationship']);
            $newPatient->password = $request['phone_number'];
            $newPatient->patient_reg_no = $this->generatePatientId($request['type'], $request['level']);
            $newPatient->created_by_id = $staff->id;
            $newPatient->save();

            if (!empty($patientDependent)) {
                $familyRelationship = new FamilyRelationship();
                $familyRelationship->sponsor_id = $patientDependent->id ?? null;
                $familyRelationship->billing_responsibility = $request['billing_responsibility'];
                $familyRelationship->patient_id = $newPatient->id;
                $familyRelationship->relationship_type = $request['family_member_relationship'];
                $familyRelationship->save();
            }

            // Create a wallet for the new patient
            $wallet = new Wallet();
            $wallet->patient_id = $newPatient->id;
            $wallet->save();
            $payment->patient_id = $newPatient->id;
            $payment->payable_id = $newPatient->id;
            $payment->payable_type = Patient::class;
            $payment->is_used = true;
            $payment->save();

            DB::commit();

            return response()->json([
                'message' => 'Account created successfully',
                'status' => 'success',
                'success' => true
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'matriculation_number' => [
                'nullable',
                'string',
                Rule::unique('patients', 'matriculation_number')->ignore($id),
            ],
            'insurance_number' => 'required_with:insurance_provider',
            'insurance_provider' => 'nullable|integer',
            'middlename' => 'nullable|string',
            'room_number' => 'exclude_unless:type,STUDENT|string',
            'contact_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'phone_number' => [
                'nullable',
                'string',
                Rule::unique('patients', 'phone_number')->ignore($id),
            ],
            'state_of_origin' => 'nullable|string',
            'lga' => 'nullable|string',
            'occupation' => 'nullable|string',
            'level' => 'nullable|exclude_unless:type,STUDENT|numeric',
            'age' => 'nullable|numeric',
            'hall_of_residence' => 'nullable|exclude_unless:type,STUDENT|string',
            'nationality' => 'required|string',
            'type' => 'required|in:STUDENT,STAFF,OTHERS,STAFF_FAMILY',
            'marital_status' => 'required|in:SINGLE,MARRIED,DIVORCED,WIDOWED',
            'gender' => 'required|string',
            'date_of_birth' => 'nullable|date',
            'religion' => 'required|string',
            'department' => 'nullable|exclude_unless:type,STUDENT,STAFF|string',
            'title' => 'required|string',
            'tribe' => 'required|string',
            'billing_responsibility' => 'nullable|boolean',
            'family_member' => 'nullable|integer',
            'family_member_relationship' => 'nullable|required_with:family_member|in:OTHER,SPOUSE,SIBLINGS,CHILD,FATHER,MOTHER',
            'staff_number' => [
                'exclude_unless:type,STAFF',
                'string',
                Rule::unique('patients', 'staff_number')->ignore($id),
            ],
            // 'payment_reference' => 'required|string',
            'next_of_kin' => 'nullable|array',
            'email' => [
                'nullable',
                'email',
                Rule::unique('patients', 'email')->ignore($id),
            ],
        ]);

        try {
            $staff = Auth::user();

            $patient = Patient::find($id);

            if (!$patient) {
                return response()->json([
                    'message' => 'The patient account detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $insuranceProvider = null;
            $patientDependent = null;

            if ($patient->type !== $request['type']) {
                $patient->patient_reg_no = $this->generatePatientId($request['type'], $request['level'] ?? null);
            }

            if ($patient->type === 'STUDENT' && $request['type'] === 'STUDENT' && $patient->level !== $request['level']) {
                $patient->patient_reg_no = $this->generatePatientId($request['type'], $request['level']);
            }

            if (!empty($request['insurance_provider'])) {
                $insuranceProvider = OrganisationAndHmo::find($request['insurance_provider']);
                if (!$insuranceProvider) {
                    return response()->json([
                        'message' => 'The insurance provider detail does not match any record',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            //Check the insurance number already exist
            if (!empty($request['insurance_provider']) && !empty($request['insurance_number'])) {
                $patientWithSameInsuranceNumber = Patient::where('organisation_hmo_id', $request['insurance_provider'])
                    ->where('insurance_number', $request['insurance_number'])
                    ->where('id', '!=', $patient->id)
                    ->first();

                if ($patientWithSameInsuranceNumber) {
                    return response()->json([
                        'message' => 'The insurance number is already assigned to another patient.',
                        'success' => false,
                        'status' => 'error'
                    ], 400);
                }
            }

            if (!empty($request['family_member'])) {
                $patientDependent = Patient::find($request['family_member']);
                if (!$patientDependent) {
                    return response()->json([
                        'message' => 'The family member detail provided does not match any record',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            DB::beginTransaction();

            $patient->fill([
                'firstname' => strtolower(trim($request['firstname'] ?? $patient->firstname)),
                'middlename' => strtolower(trim($request['middlename'] ?? $patient->middlename)),
                'lastname' => strtolower(trim($request['lastname'] ?? $patient->lastname)),
                'email' => strtolower(trim($request['email'] ?? $patient->email)),
                'contact_address' => strtolower(trim($request['contact_address'] ?? $patient->contact_address)),
                'phone_number' => trim($request['phone_number'] ?? $patient->phone_number),
                'insurance_number' => $insuranceProvider ? trim($request['insurance_number']) : $patient->insurance_number,
                'organisation_hmo_id' => $insuranceProvider?->id ?? $patient->insurance_provider_id,
                'religion' => $request['religion'] ?? $patient->religion,
                'state_of_origin' => trim($request['state_of_origin'] ?? $patient->state_of_origin),
                'title' => $request['title'] ?? $patient->title,
                'lga' => trim($request['lga'] ?? $patient->lga),
                'type' => $request['type'] ?? $patient->type,
                'tribe' => trim($request['tribe'] ?? $patient->tribe),
                'department' => $request['type'] === 'OTHERS' ? null : $request["department"],
                'age' => trim($request['age'] ?? $patient->age),
                'hall_of_residence' => trim($request['hall_of_residence'] ?? $patient->hall_of_residence),
                'level' => $request['level'] ?? $patient->level,
                'gender' => $request['gender'],
                'staff_number' => $request['staff_number'] ?? $patient->staff_number,
                'nationality' => trim($request['nationality']),
                'room_number' => trim($request['room_number']),
                'marital_status' => $request['marital_status'],
                'matriculation_number' => $request['matriculation_number'] ?? null,
                'occupation' => trim($request['occupation']),
                'permanent_address' => strtolower(trim($request['permanent_address'] ?? $patient->permanent_address)),
                'next_of_kin_firstname' => strtolower(trim($request['next_of_kin']['firstname'] ?? $patient->next_of_kin_firstname)),
                'next_of_kin_lastname' => strtolower(trim($request['next_of_kin']['lastname'] ?? $patient->next_of_kin_lastname)),
                'next_of_kin_contact_address' => strtolower(trim($request['next_of_kin']['contact_address'] ?? $patient->next_of_kin_contact_address)),
                'next_of_kin_phone_number' => trim($request['next_of_kin']['phone_number'] ?? $patient->next_of_kin_phone_number),
                'next_of_kin_relationship' => trim($request['next_of_kin']['relationship'] ?? $patient->next_of_kin_relationship),
                'last_updated_by_id' => $staff->id,
            ]);

            $patient->save();

            // if ($patientDependent) {
            //     $existingRelationship = FamilyRelationship::where(function ($query) use ($patient, $patientDependent) {
            //         $query->where([
            //             ['sponsor_id', $patientDependent->id],
            //             ['patient_id', $patient->id],
            //         ])->orWhere([
            //             ['sponsor_id', $patient->id],
            //             ['patient_id', $patientDependent->id],
            //         ]);
            //     })->first();

            //     $existingSponsor = FamilyRelationship::where('patient_id', $patient->id)
            //         ->where('billing_responsibility', true)
            //         ->first();

            //     if ($existingRelationship) {
            //         if (!$existingRelationship->billing_responsibility && $request['billing_responsibility']) {
            //             if ($existingSponsor && $existingSponsor->sponsor_id !== $patientDependent->id) {
            //                 $existingSponsor->billing_responsibility = false;
            //                 $existingSponsor->save();
            //             }

            //             $existingRelationship->billing_responsibility = true;
            //         } else {
            //             $existingRelationship->billing_responsibility = false;
            //         }

            //         $existingRelationship->relationship_type = $request['family_member_relationship'];
            //         $existingRelationship->save();
            //     } else {
            //         if ($existingSponsor) {
            //             $existingSponsor->billing_responsibility = false;
            //             $existingSponsor->save();
            //         }

            //         FamilyRelationship::create([
            //             'sponsor_id' => $patientDependent->id,
            //             'patient_id' => $patient->id,
            //             'billing_responsibility' => $request['billing_responsibility'],
            //             'relationship_type' => $request['family_member_relationship'],
            //         ]);
            //     }
            // }

            if ($patientDependent && $patient->id !== $patientDependent->id) {
                // Check existing bidirectional relationship
                $existingRelationship = FamilyRelationship::where(function ($query) use ($patient, $patientDependent) {
                    $query->where([
                        ['sponsor_id', $patientDependent->id],
                        ['patient_id', $patient->id],
                    ])->orWhere([
                        ['sponsor_id', $patient->id],
                        ['patient_id', $patientDependent->id],
                    ]);
                })->first();

                // Check current sponsor responsible for billing
                $existingSponsor = FamilyRelationship::where('patient_id', $patient->id)
                    ->where('billing_responsibility', true)
                    ->first();

                $isBillingResponsible = filter_var($request['billing_responsibility'], FILTER_VALIDATE_BOOLEAN);

                // If changing the billing sponsor, reset previous one
                if ($isBillingResponsible && $existingSponsor && $existingSponsor->sponsor_id !== $patientDependent->id) {
                    $existingSponsor->billing_responsibility = false;
                    $existingSponsor->save();
                }

                if ($existingRelationship) {
                    $existingRelationship->relationship_type = $request['family_member_relationship'];
                    $existingRelationship->billing_responsibility = $isBillingResponsible;
                    $existingRelationship->save();
                } else {
                    FamilyRelationship::create([
                        'sponsor_id' => $patientDependent->id,
                        'patient_id' => $patient->id,
                        'billing_responsibility' => $isBillingResponsible,
                        'relationship_type' => $request['family_member_relationship'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Patient detail updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update patient', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadPatients(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            $import = new PatientImport();
            Excel::import($import, $request->file('file'));

            $response = [
                'success' => true,
                'status' => 'success',
                'message' => 'Patients imported successfully.',
                'duplicates' => $import->duplicates
            ];

            if (!empty($import->duplicates)) {
                $response['message'] = 'Patient imported with some duplicates found.';
            }

            return response($response, 200);
        } catch (BadRequestException $e) {
            return response()->json([
                'status' => "error",
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'status' => "error",
                'error' => $e->getMessage(),
                'success' => false,
                'message' => 'An error occurred while trying to creating the patients account'
            ], 500);
        }
    }

    private function generateOtherId(): string
    {
        try {
            $latestPatient = Patient::where('type', '!=', UserTypes::STUDENT->value)
                ->orderByDesc('id') // More reliable than ordering by patient_reg_no
                ->first();

            $nextIdNumber = 1;

            if ($latestPatient) {
                // Extract numeric part of patient_reg_no
                $regNo = preg_replace('/\D/', '', $latestPatient->patient_reg_no); // Remove non-digits
                if (is_numeric($regNo)) {
                    $nextIdNumber = (int) $regNo + 1;
                }
            }

            return str_pad($nextIdNumber, 6, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            Log::error('Failed to generate non-student ID', ['error' => $e->getMessage()]);
            throw new RuntimeException('Something went wrong while generating ID.');
        }
    }

    protected function generateStudentId(int $level): string
    {
        try {
            $prefix = '';
            $currentYear = Carbon::now()->year;
            $baseYear = 2024;
            $yearDifference = $currentYear - $baseYear;
            $startingLetter = chr(ord('M') + $yearDifference);

            if ($level === 100) {
                $prefix = $startingLetter;
            } else {
                $levelDifference = ($level - 100) / 100;
                $prefix = chr(ord($startingLetter) - $levelDifference);
            }

            // Fetch the latest patient with matching prefix
            $latestStudent = Patient::where('patient_reg_no', 'like', "{$prefix}-%")
                ->where('type', UserTypes::STUDENT->value)
                ->orderByDesc('patient_reg_no')
                ->first();

            $nextIdNumber = 1;

            if ($latestStudent) {
                $parts = explode('-', $latestStudent->patient_reg_no);
                if (count($parts) === 2) {
                    $latestIdNumber = (int) $parts[1];
                    $nextIdNumber = $latestIdNumber + 1;
                }
            }

            return $prefix . '-' . str_pad($nextIdNumber, 5, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            Log::info($e->getMessage() . 'nullo');
            Log::error('Failed to generate student ID', ['error' => $e->getMessage()]);
            throw new RuntimeException('Something went wrong while generating student ID.');
        }
    }

    private function generatePatientId(string $userType, ?string $level = null): string
    {
        try {
            $userType === UserTypes::STUDENT->value
                ?
                $patientId = $this->generateStudentId((int) $level)
                :
                $patientId = $this->generateOtherId();

            Log::info($patientId);

            // Check if the patient ID already exists in the database
            $existingPatient = Patient::where('patient_reg_no', $patientId)
                ->where('type', $userType)
                ->first();

            if ($existingPatient) {
                // Recursively generate a new one if it already exists
                return $this->generatePatientId($userType, $level);
            }

            return $patientId;
        } catch (Throwable $th) {
            Log::info($th->getMessage() . 'hey there');
            throw $th;
        }
    }

    public function findAll(Request $request)
    {
        try {
            $queryBuilder = Patient::with(['wallet'])->orderBy("created_at", "desc");
            $query = $request->get('q', '');
            $type = $request->get('type', '');

            if (!empty($type)) {
                $queryBuilder->where('type', $type);
            }

            // Apply search filter if provided
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('firstname', 'like', "%$query%")
                        ->orWhere('lastname', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%")
                        ->orWhere('patient_reg_no', 'like', "%$query%");
                });
            }

            // Paginate results
            $patients = $queryBuilder->paginate(8);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Patient records retrieved successfully',
                'data' => $patients
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage() . 'heytd');

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving patient records',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function findOne($id)
    {
        try {
            $patient = Patient::with([
                'wallet',
                'sponsorOf.sponsor:id,patient_reg_no,firstname,lastname',
                'familyMembers',
                'organisationHmo',
                'vitalSigns',
                'physicalExaminations',
                'visitations.assignedDoctor',
                'prescriptions.requestedBy',
                'prescriptions.items.product',
                'treatments.createdBy',
                'labRequests.addedBy',
                'labRequests.testResult.addedBy:id,firstname,lastname,gender,staff_number,phone_number',
                'labRequests.testResult.resultCarriedOutBy:id,firstname,lastname,gender,staff_number,phone_number',
                'surgicalOperations.surgeon',
            ])->find($id);

            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Patient detail not found.'
                ], 400);
            }

            $registrationPayment = Payment::where('patient_id', $id)
                ->where('type', 'ACCOUNT')
                ->where('status', PaymentStatus::COMPLETED->value)
                ->first();

            // Get one of the patient's family members
            $familyRelationship = FamilyRelationship::where('patient_id', $id)
                ->orWhere('sponsor_id', $id)
                ->first();

            $relatedPerson = null;

            if ($familyRelationship) {
                $relatedPersonId = $familyRelationship->patient_id == $id
                    ? $familyRelationship->sponsor_id
                    : $familyRelationship->patient_id;

                $relatedPerson = Patient::select('id', 'firstname', 'lastname', 'patient_reg_no', 'gender', 'phone_number')
                    ->find($relatedPersonId);
            }

            return response()->json([
                'message' => 'Patient account fetched successfully.',
                'status' => 'success',
                'success' => true,
                'data' => [
                    ...$patient->toArray(),
                    'registration_payment_reference' => $registrationPayment->transaction_reference ?? null,
                    'sponsor_family_member' => $relatedPerson,
                ],
            ], 200);

            // return response()->json([
            //     'message' => 'Patient account fetched successfully.',
            //     'status' => 'success',
            //     'success' => true,
            //     'data' => [
            //         ...$patient->toArray(),
            //         'registration_payment_reference' => $registrationPayment->transaction_reference ?? null,
            //     ],
            // ], 200);
        } catch (Exception $e) {
            Log::error('Fetch patient error: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while trying to fetch the patient account.',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function getOne($id)
    {
        try {
            $patient = Patient::with([
                'wallet',
                'sponsorOf.sponsor:id,patient_reg_no,firstname,lastname',
                'familyMembers.patient:id,patient_reg_no,firstname,lastname',
                'organisationHmo',
                'vitalSigns',
                'physicalExaminations',
                'visitations.assignedDoctor',
                'prescriptions.requestedBy',
                'prescriptions.items.product',
                'treatments.createdBy',
                'labRequests.addedBy',
                'labRequests.testResult.addedBy:id,firstname,lastname,gender,staff_number,phone_number',
                'labRequests.testResult.resultCarriedOutBy:id,firstname,lastname,gender,staff_number,phone_number',
                'surgicalOperations.surgeon',
            ])->find($id);

            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Patient detail not found.'
                ], 400);
            }

            $registrationPayment = Payment::where('patient_id', $id)
                ->where('type', 'ACCOUNT')
                ->where('status', PaymentStatus::COMPLETED->value)
                ->first();

            $patientArray = $patient->toArray();
            unset($patientArray['sponsor_of']);
            unset($patientArray['family_members']);

            return response()->json([
                'message' => 'Patient account fetched successfully.',
                'status' => 'success',
                'success' => true,
                'data' => [
                    ...$patientArray,
                    'registration_payment_reference' => $registrationPayment->transaction_reference ?? null,
                    'siblings' => count($patient->sponsorOf) > 0 ? $patient->sponsorOf : $patient->familyMembers,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Fetch patient error: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while trying to fetch the patient account.',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }


    // public function findOne($id)
    // {
    //     try {
    //         $patient = Patient::with([
    //             "wallet",
    //             "sponsorOf.patient:id,patient_reg_no,firstname,lastname",
    //             "familyMembers",
    //             "organisationHmo",
    //             "vitalSigns",
    //             "visitations.assignedDoctor",
    //             'prescriptions.requestedBy',
    //             "physicalExaminations",
    //             'prescriptions.items.product',
    //             'treatments.createdBy',
    //             "labRequests.addedBy",
    //             "labRequests.testResult.addedBy:id,firstname,lastname,gender,staff_number,phone_number",
    //             "labRequests.testResult.resultCarriedOutBy:id,firstname,lastname,gender,staff_number,phone_number",
    //             'surgicalOperations.surgeon'
    //         ])->find($id);

    //         if (!$patient) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'success' => false,
    //                 'message' => "patient detail not found."
    //             ], 400);
    //         }

    //         return response()->json([
    //             'message' => 'patient Account fetched successfully.',
    //             'status' => 'success',
    //             'success' => true,
    //             'data' => $patient
    //         ], 200);
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage());
    //         return response()->json([
    //             'message' => 'An error occurred while trying to fetching the patient account',
    //             'status' => 'error',
    //             'success' => false
    //         ], 500);
    //     }
    // }

    public function delete($id)
    {
        try {
            $patient = Patient::find($id);

            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => "patient detail not found."
                ], 400);
            }

            $patient->delete();

            return response()->json([
                'message' => 'patient Account deleted successfully.',
                'status' => 'success',
                'success' => true
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            // Handle any other exception
            return response()->json([
                'message' => 'An error occurred while trying to delete the patient account',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }
}
