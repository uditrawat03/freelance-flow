<x-mail::message>

    # Monthly Revenue Report
    ## {{ $workspace->name }} · {{ $month->format('F Y') }}

    Here is your revenue summary for {{ $month->format('F Y') }}.

    <x-mail::panel>
        **Total revenue collected:** ₹{{ number_format($report['total_revenue'], 2) }}
        **Invoices paid:** {{ $report['invoices_paid'] }}
        **Outstanding invoices:** {{ $report['invoices_outstanding'] }}
        **Outstanding amount:** ₹{{ number_format($report['outstanding_amount'], 2) }}
    </x-mail::panel>

    <x-mail::button :url="config('app.url') . '/dashboard'" color="primary">
        View Dashboard
    </x-mail::button>

    Thanks,
    **FreelanceFlow**

</x-mail::message>