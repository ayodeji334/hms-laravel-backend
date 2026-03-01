<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Result</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; padding: 1px; }
        .section-title {
            background: #1e3a8a;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }
         .section-title-2 {
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 6px; }
        .label { font-weight: bold; background: #f3f4f6; width: 25%; }
        .mt-20 { margin-top: 20px; }
        .mt-30 { margin-top: 30px; }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
     <div class="header-content">
               <div style="width: 200px; height: 40px; margin: auto;padding: 5px;overflow: hidden;">
                <img src="{{ public_path('images/logo.png') }}" alt="Laravel Logo" style="width: 100%; height: 100%;" />
            </div>
    <h2>BAPTIST MEDICAL CENTRE, SAKI</h2>
    <p>Laboratory Test Result</p>
</div>

{{-- PATIENT INFORMATION --}}
<div class="mt-20">
    <div class="section-title">Patient Information</div>
    <table>
        <tr>
            <td class="label">Date:</td>
            <td>{{ Carbon\Carbon::parse($result->result_date)->format('d M Y') }}</td>
            <td class="label">Time:</td>
            <td>{{ Carbon\Carbon::parse($result->result_date)->format('h:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Patient No:</td>
            <td>{{ $result->patient->patient_reg_no }}</td>
            <td class="label">Test Name:</td>
            <td>{{ $result->test->name }}</td>
        </tr>
        <tr>
            <td class="label">Full Name:</td>
            <td>{{ $result->patient->fullname }}</td>
            <td class="label">Age / Sex:</td>
            <td>{{ $result->patient->age }} / {{ $result->patient->gender }}</td>
        </tr>
        <tr>
            <td class="label">Address:</td>
            <td colspan="3">{{ $result->patient->contact_address }}</td>
        </tr>
    </table>
</div>

<div class="mt-20">
    <div class="section-title">Result Summary</div>

  {{-- TOP-LEVEL INPUT FIELDS --}}
@if(!empty($result->result_details['input_fields']))
<div class="mt-20">
    <div class="section-title-2">General Result</div>
    <table>
        @foreach($result->result_details['input_fields'] as $field)
            <tr>
                <td class="label">{{ $field['fieldName'] ?? 'Field' }}</td>
                <td>{{ $field['value'] ?? 'N/A' }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endif

{{-- CATEGORIES --}}
@if(!empty($result->result_details['categories']))
    @foreach($result->result_details['categories'] as $category)
        <div class="mt-20">
            <div class="section-title-2">{{ $category['name'] ?? 'Category' }}</div>

            {{-- CATEGORY INPUT FIELDS --}}
            @if(!empty($category['input_fields']))
                <table style="margin-bottom:20px;">
                    @foreach($category['input_fields'] as $field)
                        <tr>
                            <td class="label">{{ $field['fieldName'] ?? 'Field' }}</td>
                            <td>{{ $field['value'] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            {{-- CATEGORY TABLES --}}
            @if(!empty($category['tables']))
                @foreach($category['tables'] as $table)
                    <table style="margin-bottom:20px;">
                        <thead>
                            <tr>
                                @forelse($table['columns'] as $column)
                                    <th>{{ $column['header'] ?? 'Parameter' }}</th>
                                @empty
                                    <th>Parameter</th>
                                @endforelse
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($table['rows'] as $row)
                                <tr>
                                    @forelse($row['values'] as $value)
                                        <td>{{ $value ?? '-' }}</td>
                                    @empty
                                        <td>-</td>
                                    @endforelse
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($table['columns'] ?? []) }}">No data available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endforeach
            @endif
        </div>
    @endforeach
@endif

{{-- FALLBACK MESSAGE --}}
@if(empty($result->result_details['input_fields']) && empty($result->result_details['categories']))
    <p style="margin-top:20px; text-align:center; font-style:italic;">
        No test result details available.
    </p>
@endif

</div>

{{-- FOOTER --}}
<div class="mt-30">
    <table>
        <tr>
            <td>
                <strong>Result Carried Out By:</strong><br>
                {{ $result->result_carried_out_by->full_name ?? 'N/A' }}
            </td>
            <td>
                <strong>Verified By:</strong><br>
                ________________________
            </td>
        </tr>
    </table>

    <p style="margin-top:20px; font-size:11px;">
        This is a computer-generated laboratory result.
        Please contact the hospital for clarifications if needed.
    </p>
</div>

</body>
</html>