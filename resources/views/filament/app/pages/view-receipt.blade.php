{{--
    Payment receipt (App\Filament\App\Pages\ViewReceipt). All values are
    formatted in the page class and escaped here ({{ }}). Inline, scoped styles
    only: panels load no app.css. On screen the receipt is a paper card using
    Filament's colour variables (light + dark); in print only the receipt is
    shown, black on white, on one A4 page.
--}}
<x-filament-panels::page>
    <style>
        .rst-rcpt { --rc-ink: var(--gray-950); --rc-muted: var(--gray-500); --rc-line: var(--gray-200); --rc-soft: var(--gray-50); --rc-paper: #fff; --rc-brand: #1F4D3B;
            max-width: 52rem; width: 100%; margin-inline: auto; background: var(--rc-paper); color: var(--rc-ink); border: 1px solid var(--rc-line);
            border-radius: 1rem; box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 8px 24px -12px rgb(0 0 0 / .12); overflow: hidden; font-size: .875rem; line-height: 1.45; }
        .dark .rst-rcpt { --rc-ink: var(--gray-50); --rc-muted: var(--gray-400); --rc-line: rgb(255 255 255 / .1); --rc-soft: rgb(255 255 255 / .03); --rc-paper: var(--gray-900); --rc-brand: #7FB89F; }
        .rst-rcpt__bar { height: .375rem; background: linear-gradient(90deg, #1F4D3B 0%, #1F4D3B 80%, #E7A33E 80%); }
        .rst-rcpt__inner { padding: 2rem; }
        .rst-rcpt__top { display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between; align-items: flex-start; padding-bottom: 1.5rem; border-bottom: 1px solid var(--rc-line); }
        .rst-rcpt__brand { height: 2.25rem; color: var(--rc-brand); }
        .rst-rcpt__issuer { margin-top: .5rem; color: var(--rc-muted); font-size: .8125rem; }
        .rst-rcpt__title { text-align: end; }
        .rst-rcpt__heading { font-size: 1.375rem; font-weight: 700; letter-spacing: -.01em; }
        .rst-rcpt__number { margin-top: .125rem; color: var(--rc-muted); font-variant-numeric: tabular-nums; }
        .rst-rcpt__badge { display: inline-flex; align-items: center; gap: .375rem; margin-top: .625rem; padding: .1875rem .625rem; border-radius: 999px;
            font-size: .75rem; font-weight: 600; color: var(--success-700); background: var(--success-50); border: 1px solid var(--success-200); }
        .dark .rst-rcpt__badge { color: var(--success-400); background: rgb(34 197 94 / .1); border-color: rgb(34 197 94 / .25); }
        .rst-rcpt__badge svg { width: .875rem; height: .875rem; }
        .rst-rcpt__grid { display: grid; gap: 1.5rem 2rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); padding-block: 1.5rem; }
        .rst-rcpt__label { font-size: .6875rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--rc-muted); margin-bottom: .5rem; }
        .rst-rcpt__party { display: flex; flex-direction: column; gap: .125rem; }
        .rst-rcpt__party strong { font-weight: 600; font-size: .9375rem; }
        .rst-rcpt__party span { color: var(--rc-muted); }
        .rst-rcpt__facts { display: grid; grid-template-columns: auto 1fr; gap: .375rem 1rem; margin: 0; }
        .rst-rcpt__facts dt { color: var(--rc-muted); }
        .rst-rcpt__facts dd { margin: 0; text-align: end; font-weight: 500; font-variant-numeric: tabular-nums; word-break: break-word; }
        .rst-rcpt__table { width: 100%; border-collapse: collapse; }
        .rst-rcpt__table th { font-size: .6875rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--rc-muted);
            text-align: start; padding: .625rem .75rem; background: var(--rc-soft); border-block: 1px solid var(--rc-line); }
        .rst-rcpt__table td { padding: .875rem .75rem; border-bottom: 1px solid var(--rc-line); vertical-align: top; }
        .rst-rcpt__table .rst-num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .rst-rcpt__line { font-weight: 500; }
        .rst-rcpt__detail { margin-top: .125rem; color: var(--rc-muted); font-size: .8125rem; }
        .rst-rcpt__total { display: flex; justify-content: flex-end; padding-top: 1.25rem; }
        .rst-rcpt__total-box { min-width: min(100%, 18rem); display: flex; justify-content: space-between; align-items: baseline; gap: 1.5rem;
            padding: .875rem 1rem; border-radius: .625rem; background: var(--rc-soft); border: 1px solid var(--rc-line); }
        .rst-rcpt__total-box span { color: var(--rc-muted); font-weight: 500; }
        .rst-rcpt__total-box strong { font-size: 1.375rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .rst-rcpt__note { margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px dashed var(--rc-line); color: var(--rc-muted); font-size: .8125rem; }

        @media (max-width: 640px) {
            .rst-rcpt__inner { padding: 1.25rem; }
            .rst-rcpt__title { text-align: start; }
        }

        @media print {
            @page { size: A4; margin: 14mm; }
            html, body { background: #fff !important; }
            /* Only the receipt: hide the band, topbar, sidebar, page header and notifications. */
            body > :not(.fi-layout), .fi-sidebar, .fi-sidebar-close-overlay, .fi-header, .fi-layout-sidebar-toggle-btn-ctn { display: none !important; }
            .fi-layout, .fi-main-ctn, .fi-main, .fi-page, .fi-page-main, .fi-page-content, .fi-page-header-main-ctn {
                display: block !important; position: static !important; width: auto !important; max-width: none !important;
                height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
            .rst-rcpt, .dark .rst-rcpt { --rc-ink: #111; --rc-muted: #555; --rc-line: #d9d9d9; --rc-soft: #f6f6f4; --rc-paper: #fff; --rc-brand: #1F4D3B;
                max-width: none; border: 0; border-radius: 0; box-shadow: none; font-size: 10.5pt; }
            .rst-rcpt__inner { padding: 1.25rem 0 0; }
            .rst-rcpt, .rst-rcpt * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .rst-rcpt__badge, .dark .rst-rcpt__badge { color: #15803d; background: #f0fdf4; border-color: #bbf7d0; }
            .rst-rcpt__table tr, .rst-rcpt__total, .rst-rcpt__note { break-inside: avoid; }
        }
    </style>

    <article class="rst-rcpt" aria-labelledby="rst-rcpt-heading">
        <div class="rst-rcpt__bar"></div>

        <div class="rst-rcpt__inner">
            <header class="rst-rcpt__top">
                <div>
                    <div class="rst-rcpt__brand">
                        @include('filament.brand-logo', ['name' => $issuer['name']])
                    </div>
                    @if ($issuer['email'])
                        <div class="rst-rcpt__issuer">{{ $issuer['email'] }}</div>
                    @endif
                </div>

                <div class="rst-rcpt__title">
                    <div id="rst-rcpt-heading" class="rst-rcpt__heading">{{ __('billing.receipt.heading') }}</div>
                    <div class="rst-rcpt__number">{{ __('billing.receipt.number') }}: {{ $number }}</div>
                    <span class="rst-rcpt__badge">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        {{ $status }} · {{ $paidAt }}
                    </span>
                </div>
            </header>

            <div class="rst-rcpt__grid">
                <section>
                    <div class="rst-rcpt__label">{{ __('billing.receipt.sections.billing') }}</div>
                    <div class="rst-rcpt__party">
                        <strong>{{ $billing['name'] ?? '—' }}</strong>
                        @if ($billing['tax'])
                            <span>{{ __('billing.receipt.tax_id') }}: {{ $billing['tax'] }}</span>
                        @endif
                        @if ($billing['address'])
                            <span>{{ $billing['address'] }}</span>
                        @endif
                        @if ($billing['email'])
                            <span>{{ $billing['email'] }}</span>
                        @endif
                    </div>
                </section>

                <section>
                    <div class="rst-rcpt__label">{{ __('billing.receipt.sections.payment') }}</div>
                    <dl class="rst-rcpt__facts">
                        <dt>{{ __('billing.receipt.paid_at') }}</dt>
                        <dd>{{ $paidAt }}</dd>
                        @if ($order)
                            <dt>{{ __('shop.payment_label') }}</dt>
                            <dd>{{ $order }}</dd>
                        @else
                            <dt>{{ __('billing.receipt.plan') }}</dt>
                            <dd>{{ $plan }}</dd>
                            <dt>{{ __('billing.receipt.period') }}</dt>
                            <dd>{{ $period }}</dd>
                        @endif
                        @if ($card)
                            <dt>{{ __('billing.receipt.card') }}</dt>
                            <dd>{{ $card }}</dd>
                        @endif
                        @if ($reference)
                            <dt>{{ __('billing.receipt.reference') }}</dt>
                            <dd>{{ $reference }}</dd>
                        @endif
                    </dl>
                </section>
            </div>

            <table class="rst-rcpt__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('billing.receipt.line_description') }}</th>
                        <th scope="col" class="rst-num">{{ __('billing.receipt.line_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>
                                <div class="rst-rcpt__line">{{ $line['label'] }}</div>
                                @if ($line['detail'])
                                    <div class="rst-rcpt__detail">{{ $line['detail'] }}</div>
                                @endif
                            </td>
                            <td class="rst-num">{{ $line['amount'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="rst-rcpt__total">
                <div class="rst-rcpt__total-box">
                    <span>{{ __('billing.receipt.total') }}</span>
                    <strong>{{ $total }}</strong>
                </div>
            </div>

            <p class="rst-rcpt__note">{{ __('billing.receipt.not_invoice') }}</p>
        </div>
    </article>
</x-filament-panels::page>
