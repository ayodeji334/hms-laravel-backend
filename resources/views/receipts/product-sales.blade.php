<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Receipt</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 10px;
            line-height: 1.2;
            width: 58mm;
            margin: 0 auto;
            padding: 5px;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .dashed-line {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        
        /* Logo Styling */
        .logo-container {
            text-align: center;
            margin-bottom: 5px;
        }
        .logo-container img {
            max-width: 40mm; /* Fits 58mm roll well */
            height: auto;
        }

        .header h2 { font-size: 12px; margin: 2px 0; }
        .header p { font-size: 8px; margin: 0; }

        .info-table, .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        
        .item-table th {
            border-bottom: 1px solid #000;
            text-align: left;
            font-size: 9px;
        }

        .item-row td {
            padding-top: 4px;
            vertical-align: top;
        }
        
        .summary {
            margin-top: 10px;
            width: 100%;
        }

        .footer {
            margin-top: 15px;
            font-size: 9px;
            font-style: italic;
        }
    </style>
</head>
<body>

     <div class="header text-center">
        <h2>Baptist Hosptial, Ejigbo</h2>
        <p>Oba Moyepe Way, Ejigbo. Isale Osolo Ejigbo, Osun State, Nigeria</p>
    </div>

    <div class="dashed-line"></div>

    <table class="info-table">
        <tr><td class="bold">Receipt:</td><td class="text-right">{{ $invoice_id }}</td></tr>
        <tr><td class="bold">Date:</td><td class="text-right">{{ \Carbon\Carbon::parse($created_at)->format('d/m/y H:i') }}</td></tr>
        <tr><td class="bold">Client:</td><td class="text-right">{{ $customer_name }}</td></tr>
    </table>

    <div class="dashed-line"></div>

    <table class="item-table">
        <thead>
            <tr>
                <th width="50%">Description</th>
                <th width="15%">Qty</th>
                <th width="35%" class="text-right">Total(N)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales_items as $item)
                <tr class="item-row">
                    <td colspan="3">
                        {{ $item['product']['brand_name'] ?? 'Item' }} 
                        <small>({{ $item['product']['dosage_strength'] ?? '' }} {{ $item['product']['dosage_type'] ?? '' }})</small>
                    </td>
                </tr>
                <tr>
                    <td>@ N{{ number_format($item['product']['unit_price'], 2) }}</td>
                    <td>x{{ $item['quantity_sold'] }}</td>
                    <td class="text-right">{{ number_format($item['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="dashed-line"></div>

    <table class="summary">
        <tr>
            <td class="bold">GRAND TOTAL:</td>
            <td class="text-right bold">N{{ number_format($total_price, 2) }}</td>
        </tr>
        @if (!empty($payment))
            <tr>
                <td>Paid Via ({{ $payment['payment_method'] }}):</td>
                <td class="text-right">N{{ number_format($payment['amount'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 8px;">Ref: {{ $payment['transaction_reference'] }}</td>
            </tr>
        @endif
    </table>

    <div class="dashed-line"></div>

    <div class="footer text-center">
        <p>Served By: {{ $sold_by['full_name'] ?? 'N/A' }}</p>
        <p class="bold">Thank you for your patronage!</p>
        <p>Keep your receipt</p>
    </div>

</body>
</html>