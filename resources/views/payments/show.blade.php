<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice {{ $invoice->number }} — FreelanceFlow</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    {{-- Stripe.js — always load from Stripe's CDN, never self-host --}}
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        {{-- Invoice summary card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-lg font-bold text-gray-900">FreelanceFlow</p>
                    <p class="text-sm text-gray-500">Invoice {{ $invoice->number }}</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-gray-900">{{ $invoice->formatted_total }}</p>
                    @if ($invoice->due_at)
                        <p class="text-xs text-gray-400 mt-0.5">
                            Due {{ $invoice->due_at->format('M d, Y') }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="border-t border-gray-100 pt-3">
                <p class="text-sm font-medium text-gray-700">{{ $invoice->client->name }}</p>
                @if ($invoice->client->company)
                    <p class="text-xs text-gray-400">{{ $invoice->client->company }}</p>
                @endif
            </div>
        </div>

        {{-- Payment form card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Payment details</h2>

            <form id="payment-form">
                {{-- Stripe Elements mounts here --}}
                <div id="payment-element" class="mb-4"></div>

                {{-- Error messages --}}
                <div id="payment-errors" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

                <button
                    id="submit-btn"
                    type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed
                           text-white font-medium py-2.5 px-4 rounded-lg transition-colors"
                >
                    <span id="btn-text">Pay {{ $invoice->formatted_total }}</span>
                    <span id="btn-spinner" class="hidden">Processing...</span>
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-gray-400">
                Secured by
                <span class="font-medium text-gray-500">Stripe</span>
                · Your card details are never stored on our servers
            </p>
        </div>

    </div>

    <script>
        const stripe = Stripe('{{ $stripePublicKey }}');

        const elements = stripe.elements({
            clientSecret: '{{ $clientSecret }}',
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#6366f1',
                    fontFamily: 'Inter, sans-serif',
                    borderRadius: '8px',
                },
            },
        });

        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        const form        = document.getElementById('payment-form');
        const submitBtn   = document.getElementById('submit-btn');
        const btnText     = document.getElementById('btn-text');
        const btnSpinner  = document.getElementById('btn-spinner');
        const errorDiv    = document.getElementById('payment-errors');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Disable the button to prevent double submission
            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');
            errorDiv.classList.add('hidden');

            const { error } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: '{{ route('invoices.pay.success', $invoice) }}',
                },
            });

            // Only runs if there is an immediate error
            // (successful payments redirect automatically)
            if (error) {
                errorDiv.textContent = error.message;
                errorDiv.classList.remove('hidden');

                submitBtn.disabled = false;
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
            }
        });
    </script>

</body>
</html>