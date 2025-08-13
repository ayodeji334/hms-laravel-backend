<?php

namespace App\Imports;

use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row): ?Product
    {
        try {
            $name = trim($row['name_of_medicines'] ?? '');
            $dosageForm = trim($row['dosages'] ?? '');
            $dosageStrength = trim($row['strengths'] ?? '');

            if (!$name) {
                Log::warning('Missing product name in row:', $row);
                return null;
            }

            $finalName = $name;
            if (Product::where('brand_name', $finalName)->exists()) {
                $finalName = "$name ($dosageStrength)";
                if (Product::where('brand_name', $finalName)->exists()) {
                    $finalName = "$name ($dosageForm)";
                    if (Product::where('brand_name', $finalName)->exists()) {
                        $finalName = "$name ($dosageForm - $dosageStrength)";
                    }
                }
            }

            return new Product([
                'brand_name' => $finalName,
                'generic_name' => $finalName,
                'dosage_form' => $dosageForm,
                'dosage_strength' => $dosageStrength,
                'sales_price' => 0,
                'unit_price' => 0,
                'purchase_price' => 0,
                'added_by_id' => 50,
                'tracking_code' => strtoupper(Str::random(12))
            ]);
        } catch (Exception $e) {
            Log::error('Error processing row: ' . $e->getMessage(), $row);
            return null;
        }
    }
}
