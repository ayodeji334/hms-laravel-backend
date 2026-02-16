<table>
    <tr>
        <td><strong>Printed By</strong></td>
        <td>{{ $account['name'] ?? 'user' }}</td>
    </tr>
    <tr>
        <td><strong>Date Printed</strong></td>
        <td>{{ $date_printed }}</td>
    </tr>
    <tr>
        <td><strong>Statement Period</strong></td>
        <td>{{ $period['from'] }} to {{ $period['to'] }}</td>
    </tr>
    {{-- <tr>
        <td><strong>Total Debit</strong></td>
        <td>{{ $summary['total_debit'] }}</td>
    </tr>
    <tr>
        <td><strong>Total Credit</strong></td>
        <td>{{ $summary['total_credit'] }}</td>
    </tr> --}}
</table>

<h3>Summary</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <td><strong>Successful (COMPLETED)</strong></td>
        <td>₦{{ number_format($summary['successful_total']) }}</td>
    </tr>
    <tr>
        <td><strong>Failed (FAILED)</strong></td>
        <td>₦{{ number_format($summary['failed_total']) }}</td>
    </tr>
    <tr>
        <td><strong>Others</strong></td>
        <td>₦{{ number_format($summary['others_total']) }}</td>
    </tr>
</table>

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th><strong>Transaction Reference</strong></th>
            <th><strong>Customer Name</strong></th>
            <th><strong>Amount Payable (₦)</strong></th>
            <th><strong>Amount Paid (₦)</strong></th>
            <th><strong>Transfer Charges (₦)</strong></th>
            <th><strong>Status</strong></th>
            <th><strong>Payment Method</strong></th>
            <th><strong>HMO Name</strong></th>
            <th><strong>Created By</strong></th>
            <th><strong>Confirmed By</strong></th>
            <th><strong>Created At</strong></th>
        </tr>
    </thead>
    <tbody>
        @foreach($transactions as $t)
            <tr>
                <td style="font-size: 20px;font-weight:bold">{{ $t->transaction_reference }}</td>
                <td>{{ $t->customer_name }}</td>
                <td>₦{{ number_format($t->amount_payable )}}</td>
                <td>₦{{ number_format($t->amount) }}</td>
                <td>₦{{ number_format($t->transfer_charges) }}</td>
                <td>{{ $t->status }}</td>
                <td>{{ $t->payment_method }}</td>
                <td>{{ optional($t->organisation)->name }}</td>
                <td>{{ optional($t->addedBy)->name }}</td>
                <td>{{ optional($t->confirmedBy)->name }}</td>
                <td>{{ $t->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
