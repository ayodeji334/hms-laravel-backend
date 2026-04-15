<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 9px;
            line-height: 1.3;
            width: 58mm;
            margin: 0 auto;
            padding: 5px;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .logo-container {
            text-align: center;
            margin-bottom: 4px;
        }
        .logo-container img {
            max-width: 35mm;
            height: auto;
            filter: grayscale(100%);
        }

        .header h2 { font-size: 10px; margin: 2px 0; text-transform: uppercase; }
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
            font-size: 10px;
        }
        .label { width: 45%; font-weight: bold; }
        .value { width: 55%; text-align: right; font-weight: bold; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .items-table th {
            font-size: 9px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding: 2px 0;
            text-align: left;
        }
        .items-table th.right { text-align: right; }
        .items-table td {
            font-size: 9px;
            padding: 3px 0;
            vertical-align: top;
            border-bottom: 1px dotted #aaa;
        }
        .items-table td.right { text-align: right; }

        .footer {
            margin-top: 10px;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header text-center">
        <h2>Baptist Hosptial, Ejigbo</h2>
        <p>Oba Moyepe Way, Ejigbo. Isale Osolo Ejigbo, Osun State, Nigeria</p>
    </div>

    <div class="divider"></div>
    
    <div class="receipt-title">PAYMENT RECEIPT</div>

    {{-- DATE + PATIENT — shared across all payments --}}
    <table class="meta-table">
        {{-- <tr>
            <td class="label">Date:</td>
            <td class="value">{{ \Carbon\Carbon::parse($summary['payment_date'])->format('d/m/Y h:i A') }}</td>
        </tr> --}}
        <tr>
            <td class="label">Patient Name:</td>
            <td class="value">{{ $patient['firstname'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Reg No:</td>
            <td class="value">{{ $patient['patient_reg_no'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Pay Method:</td>
            <td class="value">{{ $payments[0]['payment_method'] ?? 'N/A' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- TRANSACTIONS LIST --}}
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>
                    <div class="bold">{{ $payment['type'] ?? 'Payment' }}</div>
                    <div style="font-size:8px; color:#444">
                        Ref: {{ $payment['transaction_reference'] ?? 'N/A' }}
                    </div>
                    @if(!empty($payment['status']))
                        <div style="font-size:8px;">
                            {{ strtoupper($payment['status']) }}
                        </div>
                    @endif
                </td>
                <td class="right">
                    N{{ number_format($payment['amount'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    {{-- TOTALS --}}
    <table class="meta-table">
        <tr>
            <td class="label">Total Payable:</td>
            <td class="value">N{{ number_format($summary['total_amount_payable'], 2) }}</td>
        </tr>
        <tr style="font-size: 11px;">
            <td class="label">TOTAL PAID:</td>
            <td class="value bold">N{{ number_format($summary['total_amount'], 2) }}</td>
        </tr>
        @if($summary['total_refund'] > 0)
            <tr>
                <td class="label">Total Refunded:</td>
                <td class="value">N{{ number_format($summary['total_refund'], 2) }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    {{-- PROCESSED BY — use first payment's staff, they're the same session --}}
    <div class="section" style="font-size: 8px;">
        <div>
            <strong>Processed By:</strong>
            {{ $payments[0]['added_by']['name'] ?? 'System' }}
        </div>
        @if (!empty($payments[0]['confirmed_by']))
            <div>
                <strong>Confirmed By:</strong>
                {{ $payments[0]['confirmed_by']['name'] }}
            </div>
        @endif
    </div>

    <div class="footer text-center">
        <p class="bold">Thank you for your payment!</p>
        <p>Baptist Hosptial, Ejigbo - {{ date('Y') }}</p>
    </div>
</body>
</html>