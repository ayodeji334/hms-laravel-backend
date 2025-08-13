<?php

namespace App\Imports;

use App\Enums\UserTypes;
use App\Models\Patient;
use App\Models\Wallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\PersistRelations;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Throwable;

class PatientImport implements ToModel, WithHeadingRow, WithLimit, PersistRelations
{
    public $duplicates = [];
    protected array $generatedIds = [];

    protected static int $studentSeed = 0;
    protected static int $otherSeed = 0;


    public function __construct()
    {
        // Get the latest numbers once
        $latestStudent = Patient::where('type', UserTypes::STUDENT->value)
            ->orderByDesc('id')->first();

        $latestOther = Patient::where('type', '!=', UserTypes::STUDENT->value)
            ->orderByDesc('id')->first();

        static::$studentSeed = $this->extractNumericRegNo($latestStudent?->patient_reg_no) ?? 0;
        static::$otherSeed = $this->extractNumericRegNo($latestOther?->patient_reg_no) ?? 0;
    }


    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */

    public function model(array $row)
    {
        if (empty($row['phone_number']) || empty($row['first_name']) || empty($row['last_name'])) {
            return null;
        }

        if (!array_key_exists('phone_number', $row)) {
            throw new BadRequestException('The "phone number" column is missing in the uploaded file.');
        }

        if (!array_key_exists('patient_number', $row)) {
            throw new BadRequestException('The "patient number" column is missing in the uploaded file.');
        }

        if (!array_key_exists('staff_number', $row)) {
            throw new BadRequestException('The "staff number" column is missing in the uploaded file.');
        }

        if (!array_key_exists('type', $row)) {
            throw new BadRequestException('The patient "type" column is missing in the uploaded file.');
        }


        $userType = !empty($row['type']) ? strtoupper(trim($row['type'])) : 'OTHERS';
        $level = isset($row['level']) ? (int) $row['level'] : null;

        // Determine the patient_reg_no
        $patientRegNo = !empty($row['patient_number'])
            ? $row['patient_number']
            : $this->generatePatientId($userType, $level);

        // Prevent duplicates
        if (Patient::where('patient_reg_no', $patientRegNo)->exists()) {
            Log::info('generated patient no alredy exist: ' . $patientRegNo);
            $this->duplicates[] = [
                'firstname' => $row['first_name'],
                'lastname' => $row['last_name'],
                'patient_reg_no' => $patientRegNo,
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
            ];
            return null;
        }

        $patient = new Patient([
            'firstname' => $row['first_name'],
            'lastname' => $row['last_name'],
            'email' => $row['email'] ?? null,
            'phone_number' => $row['phone_number'],
            'password' => $row['phone_number'],
            'patient_reg_no' => $patientRegNo ?? Str::random(6),
            'lga' => $row['local_government_area'],
            'state_of_origin' => $row['state_of_origin'],
            'religion' => $row['religion'],
            'staff_number' => !empty($row['staff_number']) ? $row['staff_number'] : null,
            'gender' => $row['gender'],
            'type' => $userType ?? "OTHERS",
            'nationality' => $row['nationality'],
            'next_of_kin_firstname' => $row['next_of_kin_firstname'],
            'next_of_kin_lastname' => $row['next_of_kin_lastname'],
            'next_of_kin_contact_address' => $row['next_of_kin_contact_address'],
            'next_of_kin_phone_number' => $row['next_of_kin_phone_number'],
        ]);

        $patient->setRelation('wallet', new Wallet([
            'outstanding_balance' => 0,
            'deposit_balance' => 0
        ]));

        return $patient;
    }


    public function limit(): int
    {
        return 30;
    }

    private function extractNumericRegNo(?string $regNo): ?int
    {
        if (!$regNo) return null;

        if (str_contains($regNo, '-')) {
            // Student ID like M-00001
            [, $number] = explode('-', $regNo);
            return (int) $number;
        }

        return (int) preg_replace('/\D/', '', $regNo);
    }

    // public function generatePatientId(string $userType, ?int $level = null): string
    // {
    //     do {
    //         $newId = $userType === UserTypes::STUDENT->value
    //             ? $this->generateStudentId($level)
    //             : $this->generateOtherId();
    //     } while (
    //         in_array($newId, $this->generatedIds) ||
    //         Patient::where('patient_reg_no', $newId)->exists()
    //     );

    //     $this->generatedIds[] = $newId;
    //     return $newId;
    // }
    public function generatePatientId(string $userType, ?int $level = null): string
    {
        if ($userType === UserTypes::STUDENT->value) {
            static::$studentSeed++;
            $prefix = $this->getStudentPrefix($level);
            return "{$prefix}-" . str_pad(static::$studentSeed, 5, '0', STR_PAD_LEFT);
        }

        static::$otherSeed++;
        return str_pad(static::$otherSeed, 6, '0', STR_PAD_LEFT);
    }

    private function getStudentPrefix(?int $level): string
    {
        $baseYear = 2024;
        $yearDiff = Carbon::now()->year - $baseYear;
        $letter = chr(ord('M') + $yearDiff);

        if ($level === 100) return $letter;

        $diff = ($level - 100) / 100;
        return chr(ord($letter) - $diff);
    }


    // public function generatePatientId(string $userType, ?string $level = null): string
    // {
    //     try {
    //         $userType === UserTypes::STUDENT->value
    //             ?
    //             $patientId = $this->generateStudentId((int) $level)
    //             :
    //             $patientId = $this->generateOtherId();

    //         Log::info($patientId);

    //         // Check if the patient ID already exists in the database
    //         $existingPatient = Patient::where('patient_reg_no', $patientId)
    //             ->where('type', $userType)
    //             ->first();

    //         if ($existingPatient) {
    //             // Recursively generate a new one if it already exists
    //             return $this->generatePatientId($userType, $level);
    //         }

    //         return $patientId;
    //     } catch (Throwable $th) {
    //         Log::info($th->getMessage() . 'hey there');
    //         throw $th;
    //     }
    // }

    // private function generateOtherId(): string
    // {
    //     try {
    //         // Get the highest numeric patient_reg_no
    //         $latest = Patient::where('type', '!=', UserTypes::STUDENT->value)
    //             ->whereRaw("patient_reg_no REGEXP '^[0-9]+$'")
    //             ->orderByRaw("CAST(patient_reg_no AS UNSIGNED) DESC")
    //             ->value('patient_reg_no');

    //         $nextId = $latest ? ((int) $latest + 1) : 1;

    //         return str_pad($nextId, 6, '0', STR_PAD_LEFT);
    //     } catch (Exception $e) {
    //         Log::error('Failed to generate non-student ID', ['error' => $e->getMessage()]);
    //         throw new RuntimeException('Something went wrong while generating ID.');
    //     }
    // }

    // private function generateStudentId(int $level): string
    // {
    //     try {
    //         $currentYear = Carbon::now()->year;
    //         $baseYear = 2024;
    //         $yearDifference = $currentYear - $baseYear;
    //         $startingLetter = chr(ord('M') + $yearDifference);

    //         $prefix = $level === 100
    //             ? $startingLetter
    //             : chr(ord($startingLetter) - (($level - 100) / 100));

    //         $latest = Patient::where('type', UserTypes::STUDENT->value)
    //             ->where('patient_reg_no', 'like', "$prefix-%")
    //             ->orderByRaw("CAST(SUBSTRING_INDEX(patient_reg_no, '-', -1) AS UNSIGNED) DESC")
    //             ->value('patient_reg_no');

    //         $nextIdNumber = 1;
    //         if ($latest) {
    //             $parts = explode('-', $latest);
    //             $nextIdNumber = isset($parts[1]) ? ((int) $parts[1] + 1) : 1;
    //         }

    //         return $prefix . '-' . str_pad($nextIdNumber, 5, '0', STR_PAD_LEFT);
    //     } catch (Exception $e) {
    //         Log::error('Failed to generate student ID', ['error' => $e->getMessage()]);
    //         throw new RuntimeException('Something went wrong while generating student ID.');
    //     }
    // }


    // private function generateOtherId(): string
    // {
    //     try {
    //         $latestPatient = Patient::where('type', '!=', UserTypes::STUDENT->value)
    //             ->orderByDesc('id') // More reliable than ordering by patient_reg_no
    //             ->first();

    //         $nextIdNumber = 1;

    //         if ($latestPatient) {
    //             // Extract numeric part of patient_reg_no
    //             $regNo = preg_replace('/\D/', '', $latestPatient->patient_reg_no); // Remove non-digits
    //             if (is_numeric($regNo)) {
    //                 $nextIdNumber = (int) $regNo + 1;
    //             }
    //         }

    //         return str_pad($nextIdNumber, 6, '0', STR_PAD_LEFT);
    //     } catch (Exception $e) {
    //         Log::error('Failed to generate non-student ID', ['error' => $e->getMessage()]);
    //         throw new RuntimeException('Something went wrong while generating ID.');
    //     }
    // }

    // private function generateStudentId(int $level): string
    // {
    //     try {
    //         $prefix = '';
    //         $currentYear = Carbon::now()->year;
    //         $baseYear = 2024;
    //         $yearDifference = $currentYear - $baseYear;
    //         $startingLetter = chr(ord('M') + $yearDifference);

    //         if ($level === 100) {
    //             $prefix = $startingLetter;
    //         } else {
    //             $levelDifference = ($level - 100) / 100;
    //             $prefix = chr(ord($startingLetter) - $levelDifference);
    //         }

    //         // Fetch the latest patient with matching prefix
    //         $latestStudent = Patient::where('patient_reg_no', 'like', "{$prefix}-%")
    //             ->where('type', UserTypes::STUDENT->value)
    //             ->orderByDesc('patient_reg_no')
    //             ->first();

    //         $nextIdNumber = 1;

    //         if ($latestStudent) {
    //             $parts = explode('-', $latestStudent->patient_reg_no);
    //             if (count($parts) === 2) {
    //                 $latestIdNumber = (int) $parts[1];
    //                 $nextIdNumber = $latestIdNumber + 1;
    //             }
    //         }

    //         return $prefix . '-' . str_pad($nextIdNumber, 5, '0', STR_PAD_LEFT);
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage() . 'nullo');
    //         Log::error('Failed to generate student ID', ['error' => $e->getMessage()]);
    //         throw new RuntimeException('Something went wrong while generating student ID.');
    //     }
    // }
}
