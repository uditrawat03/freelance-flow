<x-mail::message>

    # Payment Reminder

    Hi {{ $client->name }},

    This is a friendly reminder that invoice **{{ $invoice->number }}** is due soon.

    <x-mail::panel>
        **Invoice:** {{ $invoice->number }}
        **Amount due:** {{ $invoice->formatted_total }}
        **Due date:** {{ $invoice->due_at->format('F j, Y') }}
    </x-mail::panel>

    If you have already made payment, please disregard this message.
    Otherwise, you can pay securely using the button below.

    <x-mail::button :url="$paymentUrl" color="primary">
        Pay {{ $invoice->formatted_total }} Now
    </x-mail::button>

    If you have any questions about this invoice, simply reply to this email.

    Thanks,
    **FreelanceFlow**

</x-mail::message>