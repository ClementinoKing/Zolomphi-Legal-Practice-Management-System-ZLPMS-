<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #20232b; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f2f5; }
        .toolbar { width: min(210mm, calc(100% - 32px)); margin: 20px auto 12px; display: flex; justify-content: flex-end; gap: 10px; }
        .toolbar a, .toolbar button { border: 1px solid #d7d9e0; border-radius: 7px; background: white; color: #191e5e; padding: 10px 15px; font: inherit; text-decoration: none; cursor: pointer; }
        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto 24px; padding: 22mm 20mm 18mm; background: white; box-shadow: 0 4px 24px #1112; }
        .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 28px; border-bottom: 2px solid #191e5e; padding-bottom: 18px; }
        .logo { display: block; width: min(78mm, 100%); max-height: 28mm; object-fit: contain; object-position: left top; }
        .firm { min-width: 0; color: #555c6a; font-size: 10pt; line-height: 1.55; }
        .firm-name { margin: 0 0 3px; color: #191e5e; font-family: Georgia, serif; font-size: 17pt; font-weight: 700; }
        .firm p { margin: 0; }
        .invoice-heading { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; margin: 28px 0 20px; }
        h1 { margin: 0; color: #191e5e; font-family: Georgia, serif; font-size: 26pt; letter-spacing: .08em; }
        .invoice-meta { text-align: right; font-size: 10.5pt; line-height: 1.7; }
        .invoice-meta strong { display: inline-block; min-width: 82px; text-align: left; }
        .draft { display: inline-block; margin-left: 8px; border: 1px solid #b7791f; border-radius: 99px; padding: 2px 8px; color: #8a5814; font-size: 8pt; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; vertical-align: 3px; }
        .bill-to { margin: 20px 0 22px; line-height: 1.55; font-size: 11pt; }
        .eyebrow { margin: 0 0 5px; color: #676e7b; font-size: 8.5pt; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .client-name { margin: 0; font-weight: 700; }
        .matter { margin: 20px 0 14px; padding: 12px 14px; border-left: 3px solid #191e5e; background: #f7f8fb; line-height: 1.5; }
        .matter p { margin: 0; }
        .references { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; font-size: 9.5pt; }
        .reference { border-top: 1px solid #d9dce4; padding-top: 7px; }
        .reference strong { color: #191e5e; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 10pt; }
        thead { display: table-header-group; }
        th { padding: 10px 9px; background: #191e5e; color: white; text-align: left; font-size: 8.5pt; letter-spacing: .07em; text-transform: uppercase; }
        th:last-child, td.amount { text-align: right; }
        td { padding: 10px 9px; border-bottom: 1px solid #dfe2e9; vertical-align: top; }
        td.line-number { width: 32px; color: #6b7280; }
        td.amount { width: 34%; white-space: nowrap; }
        .description { white-space: pre-line; overflow-wrap: anywhere; }
        .totals { width: min(100%, 92mm); margin: 18px 0 0 auto; font-size: 10pt; }
        .total-row { display: flex; justify-content: space-between; gap: 18px; padding: 7px 0; border-bottom: 1px solid #e4e6eb; }
        .total-row strong { white-space: nowrap; }
        .total-row.grand { border-top: 2px solid #191e5e; border-bottom: 0; padding-top: 11px; color: #191e5e; font-size: 13pt; font-weight: 700; }
        .section { margin-top: 26px; break-inside: avoid; }
        .section h2 { margin: 0 0 8px; color: #191e5e; font-family: Georgia, serif; font-size: 13pt; }
        .section p { margin: 3px 0; font-size: 9.5pt; line-height: 1.5; }
        .bank { display: grid; grid-template-columns: 1fr 1fr; column-gap: 24px; }
        .bank strong { color: #373d49; }
        .notes { margin-top: 20px; font-size: 9.5pt; line-height: 1.55; }
        .notes p { margin: 5px 0; }
        .closing { margin-top: 24px; padding-top: 12px; border-top: 1px solid #cdd0d8; color: #555c6a; font-size: 9pt; line-height: 1.5; }
        .closing h2 { margin: 0 0 5px; color: #191e5e; font-family: Georgia, serif; font-size: 12pt; }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: auto; margin: 0; padding: 17mm 18mm 16mm; box-shadow: none; }
            a { color: inherit; text-decoration: none; }
            tr, .total-row, .references { break-inside: avoid; }
        }
        @media screen and (max-width: 760px) {
            .sheet { width: 100%; min-height: 100vh; padding: 28px 22px; }
            .header { flex-direction: column; }
            .invoice-heading { align-items: flex-start; flex-direction: column; }
            .invoice-meta { text-align: left; }
            .bank, .references { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Invoice actions">
        <a href="{{ route('invoices.index') }}">Back to invoices</a>
        <button type="button" onclick="window.print()">Print / Save PDF</button>
    </nav>

    <main class="sheet">
        <header class="header">
            <div>
                <img class="logo" src="{{ $logoUrl }}" alt="{{ $firm?->firm_name ?? 'Zolomphi and Company' }}">
            </div>
            <div class="firm">
                <p class="firm-name">{{ $firm?->firm_name ?? 'Zolomphi and Company' }}</p>
                @foreach ([$firm?->address_line1, $firm?->address_line2, trim(implode(', ', array_filter([$firm?->city, $firm?->state, $firm?->postal_code]))), $firm?->country] as $addressLine)
                    @if (filled($addressLine))<p>{{ $addressLine }}</p>@endif
                @endforeach
                @if (filled($firm?->phone))<p>{{ $firm->phone }}</p>@endif
                @if (filled($firm?->email))<p>{{ $firm->email }}</p>@endif
                @if (filled($firm?->website))<p>{{ $firm->website }}</p>@endif
                @if ($showTaxId)<p>Tax ID: {{ $firm->tax_id }}</p>@endif
            </div>
        </header>

        <section class="invoice-heading">
            <h1>INVOICE @if ($invoice->status === 'draft')<span class="draft">Draft</span>@endif</h1>
            <div class="invoice-meta">
                <div><strong>Invoice No:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>Date:</strong> {{ $invoice->issued_at?->format('j F, Y') ?? '—' }}</div>
                <div><strong>Due date:</strong> {{ $invoice->due_at?->format('j F, Y') ?? '—' }}</div>
            </div>
        </section>

        <section class="bill-to">
            <p class="eyebrow">Bill to</p>
            <p class="client-name">{{ $client->organization_name ?? $client->full_name ?? $client->client_number }}</p>
            @foreach ([$client->address_line1, $client->address_line2, trim(implode(', ', array_filter([$client->city, $client->state, $client->postal_code]))), $client->country] as $addressLine)
                @if (filled($addressLine))<div>{{ $addressLine }}</div>@endif
            @endforeach
            @if (filled($client->contact_person))<div>Attn. {{ $client->contact_person }}</div>@endif
            @if (filled($client->phone))<div>{{ $client->phone }}</div>@endif
            @if (filled($client->email))<div>{{ $client->email }}</div>@endif
        </section>

        @if ($showMatterReference)
            <section class="matter">
                <p class="eyebrow">Matter</p>
                <p><strong>{{ $matter->matter_number }}</strong> · {{ $matter->title }}</p>
            </section>
        @endif

        @if (filled($invoice->our_reference) || filled($invoice->your_reference))
            <section class="references" aria-label="Invoice references">
                @if (filled($invoice->our_reference))<div class="reference"><strong>Our Ref:</strong> {{ $invoice->our_reference }}</div>@endif
                @if (filled($invoice->your_reference))<div class="reference"><strong>Your Ref:</strong> {{ $invoice->your_reference }}</div>@endif
            </section>
        @endif

        @if (filled($settings['template']['header_note']))<p class="notes">{{ $settings['template']['header_note'] }}</p>@endif

        <table>
            <thead><tr><th>#</th><th>Description</th><th>Total fee ({{ $invoice->currency }})</th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $index => $item)
                    <tr>
                        <td class="line-number">{{ $index + 1 }}</td>
                        <td class="description">{{ $item->description }}</td>
                        <td class="amount">{{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals" aria-label="Invoice totals">
            <div class="total-row"><span>Subtotal</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</strong></div>
            @foreach ($taxes as $tax)
                <div class="total-row"><span>{{ $tax['label'] }} @if ((float) $tax['rate'] > 0)({{ rtrim(rtrim(number_format((float) $tax['rate'], 2), '0'), '.') }}%)@endif</span><strong>{{ $invoice->currency }} {{ number_format((float) $tax['amount'], 2) }}</strong></div>
            @endforeach
            <div class="total-row grand"><span>Total</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</strong></div>
            @if ((float) $invoice->paid_amount > 0)<div class="total-row"><span>Paid</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</strong></div>@endif
            <div class="total-row"><span>Balance due</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
        </section>

        @php($payment = $settings['payment'])
        @if (filled($payment['instructions']) || filled($payment['bank_name']) || filled($payment['account_name']) || filled($payment['account_number']) || filled($payment['account_branch']) || filled($payment['swift_code']))
            <section class="section">
                <h2>Payment instructions</h2>
                @if (filled($payment['instructions']))<p>{{ $payment['instructions'] }}</p>@endif
                <div class="bank">
                    @foreach ([['Bank name', 'bank_name'], ['Account name', 'account_name'], ['Account number', 'account_number'], ['Account branch', 'account_branch'], ['SWIFT code', 'swift_code']] as [$label, $key])
                        @if (filled($payment[$key]))<p><strong>{{ $label }}:</strong> {{ $payment[$key] }}</p>@endif
                    @endforeach
                </div>
            </section>
        @endif

        @if (filled($invoice->notes) || filled($invoice->terms))
            <section class="notes">
                @if (filled($invoice->notes))<p>{{ $invoice->notes }}</p>@endif
                @if (filled($invoice->terms))<p><strong>Terms:</strong> {{ $invoice->terms }}</p>@endif
            </section>
        @endif

        <footer class="closing">
            <h2>Invoice contact</h2>
            <p><strong>{{ $firm?->invoice_contact_name ?: ($firm?->firm_name ?? 'The firm') }}</strong></p>
            @if (filled($firm?->phone))<p>Phone: {{ $firm->phone }}</p>@endif
            @if (filled($firm?->email))<p>Email: {{ $firm->email }}</p>@endif
            @if (filled($firm?->website))<p>Website: {{ $firm->website }}</p>@endif
            @if (filled($settings['template']['footer_note']))<p>{{ $settings['template']['footer_note'] }}</p>@endif
        </footer>
    </main>
</body>
</html>
