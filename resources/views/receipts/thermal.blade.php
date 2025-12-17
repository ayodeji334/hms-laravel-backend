<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 10px;
            line-height: 1.3;
            width: 58mm;
            margin: 0 auto;
            padding: 5px;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        /* Logo Styling */
        .logo-container {
            text-align: center;
            margin-bottom: 4px;
        }
        .logo-container img {
            max-width: 35mm;
            height: auto;
            filter: grayscale(100%); /* Best for thermal printers */
        }

        .header h2 { font-size: 11px; margin: 2px 0; text-transform: uppercase; }
        .header p { font-size: 8px; margin: 0; line-height: 1.1; }

        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .receipt-title {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 5px 0;
            text-decoration: underline;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
            font-size: 9px;
        }
        .label { width: 45%; font-weight: bold; }
        .value { width: 55%; text-align: right; }

        .footer {
            margin-top: 10px;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header text-center">
        <div class="logo-container">
            <img src="https://emr.bmcsaki.org/assets/logo-D6XpxHrV.png" alt="Hospital Logo">
        </div>
        <h2>Baptist Medical Center, Saki</h2>
        <p>No. 1, Dr. V. O. Fatunla Street</p>
        <p>Ajegunle, Saki West LGA, Oyo State</p>
    </div>

    <div class="divider"></div>
    
    <div class="receipt-title">PAYMENT RECEIPT</div>

    <table class="meta-table">
        <tr>
            <td class="label">Date:</td>
            <td class="value">{{ \Illuminate\Support\Carbon::parse($payment['created_at'])->format('d/m/Y h:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Ref:</td>
            <td class="value">{{ $payment['transaction_reference'] }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="label">Patient Name:</td>
            <td class="value">{{ $payment['patient']['name'] ?? $payment['customer_name'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Reg No:</td>
            <td class="value">{{ $payment['patient']['patient_reg_no'] ?? $payment['patient_reg_no'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Pay Method:</td>
            <td class="value">{{ $payment['payment_method'] }}</td>
        </tr>
        <tr>
            <td class="label">Service Type:</td>
            <td class="value">{{ $payment['type'] }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td class="label">Payable:</td>
            <td class="value">N{{ number_format($payment['amount_payable'], 2) }}</td>
        </tr>
        <tr style="font-size: 11px;">
            <td class="label">AMOUNT PAID:</td>
            <td class="value bold">N{{ number_format($payment['amount'], 2) }}</td>
        </tr>
        @if (!empty($payment['refund_amount']) && $payment['refund_amount'] > 0)
            <tr>
                <td class="label">Refunded:</td>
                <td class="value">N{{ number_format($payment['refund_amount'], 2) }}</td>
            </tr>
        @endif
        @if (!empty($payment['status']))
            <tr>
                <td class="label">Status:</td>
                <td class="value bold">{{ strtoupper($payment['status']) }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <div class="section" style="font-size: 8px;">
        <div><strong>Processed By:</strong> {{ $payment['added_by']['name'] ?? 'System' }}</div>
        @if (!empty($payment['confirmed_by']))
            <div><strong>Confirmed By:</strong> {{ $payment['confirmed_by']['name'] }}</div>
        @endif
    </div>

    <div class="footer text-center">
        <p class="bold">Thank you for your payment!</p>
        <p>Baptist Medical Center, Saki - {{ date('Y') }}</p>
    </div>
</body>
</html>