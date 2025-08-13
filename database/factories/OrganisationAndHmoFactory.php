<?php

namespace Database\Factories;

use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganisationAndHmo>
 */
// class OrganisationAndHmoFactory extends Factory
// {
//     private static $usedNames = [];

//     public function definition(): array
//     {
//         $healthInsuranceOrganizations = [
//             "AMAN",
//             "ALLICO MULTI SHIELD - NHIA",
//             "AXAMANSARD",
//             "BASTION",
//             "BMC HEALTH SCHEME",
//             "BMC NUR, PRY & SEC. STUDENTS",
//             "BMC BORN BABIES",
//             "BMC PENSIONER",
//             "CLEARLINE - NHIA",
//             "CLEARLINE - PRIVATE",
//             "DEFENCE/ DHML",
//             "ENNYDARAS",
//             "GORAH",
//             "GREENSHIELD",
//             "HALL MARK",
//             "HEALTH CARE INT'L - NHIA",
//             "HEALTH CARE INT'L - PRIVATE",
//             "HEALTH PARTNERS LTD",
//             "HEALTH SPRING",
//             "HYGENIA - PRIVATE",
//             "HYGENIA - NHIA",
//             "IBEDC NEPA",
//             "INT'L HEALTH MGT - PRIVATE",
//             "INTEGRATED HEALTH CARE - NHIA",
//             "INT'L HEALTH MGT - NHIA",
//             "LIFE ACTION PLUS",
//             "LEADWAY",
//             "LIFE WORTH MEDICA (NYSC)",
//             "LIFE WORTH MEDICA - PRIVATE",
//             "MARINA MEDICAL - NHIA",
//             "MEDIPLAN - NHIS",
//             "NEM",
//             "NNPC - NHIS",
//             "NON SUCH MEDICARE (NYSC)",
//             "NOVO - NHIS",
//             "OCEANIC",
//             "OYSHIA",
//             "PHILIP HEALTH",
//             "POLICE - NHIS",
//             "PRINCETON - NHIA",
//             "RED CARE - NHIA",
//             "RELIANCE",
//             "RETAINERSHIP",
//             "RONSBERGER - NHIS",
//             "SONGHAI - NHIS",
//             "STERLING",
//             "SUNU HEALTH - NHIS",
//             "TOTAL HEALTH TRUST - NHIS",
//             "TOTAL HEALTH TRUST - LIBERTY BLUE",
//             "ULTIMATE HEALTH - NHIS",
//             "UNITED COMPREHENSIVE - NHIS",
//             "UNITED HEALTH - NHIS",
//             "VENUS - NYSC",
//             "WELLNESS - NHIS",
//             "ZENITH BANK",
//             "BAPTIST PASTORS",
//             "OTHERS"
//         ];

//         $availableNames = array_diff($healthInsuranceOrganizations, self::$usedNames);

//         if (empty($availableNames)) {
//             throw new Exception('No more unique names available for seeding.');
//         }

//         $name = $this->faker->randomElement($availableNames);
//         self::$usedNames[] = $name;

//         // Determine type based on namexa
//         $type = (stripos($name, 'NHIS') !== false || stripos($name, 'NHIA') !== false)
//             ? 'HMO'
//             : 'ORGANISATION';

//         return [
//             'name' => $name,
//             'email' => null,
//             'phone_number' => null,
//             'contact_address' => null,
//             'type' => $type,
//             'added_by_id' => 50,
//             'last_updated_by_id' => 50,
//         ];
//     }
// }

class OrganisationAndHmoFactory extends Factory
{
    private static $usedNames = [];

    public function definition(): array
    {
        if (count(self::$usedNames) >= 50) {
            throw new Exception('50 unique organisation names have already been used.');
        }

        $prefixes = [
            'Alpha',
            'Beta',
            'Delta',
            'Zenith',
            'Union',
            'Prime',
            'Metro',
            'Harmony',
            'Secure',
            'Platinum',
            'Reliance',
            'TrustCare',
            'Sterling',
            'Total',
            'Wellness',
            'Optima',
            'Marina',
            'Greenshield',
            'BlueLine',
            'CarePlus',
            'Nova',
            'Apex',
            'Cedar',
            'Glory',
            'Covenant',
            'Faith',
            'Trinity',
            'Divine'
        ];

        $suffixes = [
            'Health',
            'Medical',
            'Healthcare',
            'Insurance',
            'HMO',
            'Solutions',
            'Group',
            'Services',
            'Partners',
            'Consultants',
            'Limited',
            'Holdings',
            'Initiative',
            'Trust',
            'Care',
            'Coverage',
            'Aid',
            'Network'
        ];

        $locations = [
            'Abuja',
            'Lagos',
            'Ibadan',
            'Port Harcourt',
            'Kaduna',
            'Ilorin',
            'Enugu',
            'Benin',
            'Owerri',
            'Abeokuta'
        ];

        do {
            $prefix = $this->faker->randomElement($prefixes);
            $suffix = $this->faker->randomElement($suffixes);
            $location = $this->faker->optional(0.99)->randomElement($locations);
            $name = $location ? "$prefix $suffix - $location" : "$prefix $suffix";
        } while (in_array($name, self::$usedNames));

        self::$usedNames[] = $name;

        $type = stripos($name, 'HMO') !== false || stripos($name, 'Insurance') !== false
            ? 'HMO'
            : 'ORGANISATION';

        return [
            'name' => $name,
            'email' => null,
            'phone_number' => null,
            'contact_address' => null,
            'type' => $type,
            'added_by_id' => 50,
            'last_updated_by_id' => 50,
        ];
    }
}
