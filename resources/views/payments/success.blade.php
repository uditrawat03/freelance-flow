<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment Received — FreelanceFlow</title>
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-gray-50 font-sans antialiased flex items-center justify-center">
    <div class="text-center max-w-sm">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment received</h1>
        <p class="text-gray-500 mb-1">Invoice {{ $invoice->number }}</p>
        <p class="text-3xl font-bold text-gray-900 mb-6">{{ $invoice->formatted_total }}</p>
        <p class="text-sm text-gray-400">
            Thank you for your payment. A receipt has been sent to your email.
        </p>
    </div>
</body>

</html>