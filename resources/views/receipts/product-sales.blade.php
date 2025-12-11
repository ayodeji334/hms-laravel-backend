<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Sales Receipt</title>
     <style>
        body {
            font-family: monospace, sans-serif;
            font-size: 8px;
            margin: 0;
            padding: 0;
        }
        .receipt {
            width: 58mm;
            padding: 5px;
            margin: auto
        }
        .header, .footer {
            text-align: center;
            margin-bottom: 5px;
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
            padding: 2px 0;
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="header">
        <h2>Baptist Hospital, Ejigbo</h2>
        <p>Ejigbo, Osun State, Nigeria</p>
        {{-- <p>Email: fsmith@example.com | Emergency: 08056816916</p> --}}
    </div>

    <h3>Pharmacy Sales Receipt</h3>

    <p><strong>Invoice ID:</strong> {{ $invoice_id }}</p>
    <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($created_at)->format('F d, Y h:i A') }}</p>
    <p><strong>Customer:</strong> {{ $customer_name }}</p>
    <p><strong>Served By:</strong> {{ $sold_by['full_name'] ?? 'N/A' }}</p>

    <div class="section-title">Items Purchased</div>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Dosage</th>
                <th>Quantity</th>
                <th>Unit Price (₦)</th>
                <th>Total (₦)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sales_items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['product']['brand_name'] ?? '' }}</td>
                    <td>{{ $item['product']['dosage_strength'] ?? '' }} {{ $item['product']['dosage_type'] ?? '' }}</td>
                    <td>{{ $item['quantity_sold'] }}</td>
                    <td>&#8358;{{ number_format($item['product']['unit_price'], 2) }}</td>
                    <td>&#8358;{{ number_format($item['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Summary</div>
    <p><strong>Total Amount:</strong> ₦{{ number_format($total_price, 2) }}</p>
    @if (!empty($payment))
        <p><strong>Amount Paid:</strong> ₦{{ number_format($payment['amount'], 2) }}</p>
        <p><strong>Payment Method:</strong> {{ $payment['payment_method'] }}</p>
        <p><strong>Transaction Ref:</strong> {{ $payment['transaction_reference'] }}</p>
        <p><strong>Payment Status:</strong> {{ $payment['status'] }}</p>
    @endif

    <div class="footer">
        <p>Thank you for your patronage!</p>
    </div>
</div>

</body>
</html>
