<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        body {
           font-family: monospace, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .receipt {
            width: 100%;
            padding: 5px;
            margin: 0 auto;
        }
        .header, .footer {
            text-align: center;
            margin-bottom: 5px;
        }
        .header h3, .header h4 {
            margin: 3px 0;
        }
        .header h6 {
            margin: 2px 0;
            font-size: 10px;
            font-weight: normal;
        }
        .section {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta td {
            padding: 3px 0;
            vertical-align: top;
        }
        .meta td:first-child {
            width: 90%;
        }
        hr {
            border: 0.5px dashed #ccc;
            margin: 8px 0;
        }
        .divider {
            margin: 10px 0;
            border-top: 1px dashed #000;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
<div class="receipt">
    <div class="header">
        <h3>Baptist Hospital, Ejigbo</h3>
        <h6>No. 1, Beaside Baptist Theology Seminary College</h6>
        <h6>Ejigbo, Osun state, Nigeria</h6>
        <h6><strong>Email: contact@baptisthospital.org<strong></h6>
        
        <div class="divider"></div>
        
        <h4>Payment Receipt</h4>
        <div><strong>Transaction Ref:</strong> {{ $payment['transaction_reference'] }}</div>
        <div><strong>Date :</strong>  {{ \Illuminate\Support\Carbon::parse($payment['created_at'])->format('Y-m-d h:i A') }}</div>
        
        <div class="divider"></div>
    </div>

    <div class="section">
        <table class="meta">
            <tr>
                <td class="label">Patient Name:</td>
                <td>{{ $payment['patient']['name'] ?? $payment['customer_name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Patient Reg No:</td>
                <td>{{ $payment['patient']['patient_reg_no'] ?? $payment['patient_reg_no'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method:</td>
                <td>{{ $payment['payment_method'] }}</td>
            </tr>
            <tr>
                <td class="label">Type:</td>
                <td>{{ $payment['type'] }}</td>
            </tr>
            <tr>
                <td class="label">Amount Paid:</td>
                <td>#{{ number_format($payment['amount'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Amount Payable:</td>
                <td>#{{ number_format($payment['amount_payable'], 2) }}</td>
            </tr>
            @if (!empty($payment['refund_amount']))
                <tr>
                    <td class="label">Refund:</td>
                    <td>{{ "na" }}{{ number_format($payment['refund_amount'], 2) }}</td>
                </tr>
            @endif
            @if (!empty($payment['status']))
                <tr>
                    <td class="label">Status:</td>
                    <td>{{ $payment['status'] ?? 'N/A' }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="divider"></div>

    <div class="section">
        <div><span class="label">Processed By:</span> {{ $payment['added_by']['name'] ?? 'System' }}</div>
        @if (!empty($payment['confirmed_by']))
            <div><span class="label">Confirmed By:</span> {{ $payment['confirmed_by']['name'] }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p>Thank you for your payment</p>
        <p><small>Bowen Hospital - {{ now()->year }}</small></p>
    </div>
</div>
</body>
</html>