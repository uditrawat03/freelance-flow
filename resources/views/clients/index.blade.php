{{-- resources/views/clients/index.blade.php --}}
<!DOCTYPE html>
<html>
<body>
    <h1>FreelanceFlow — Clients</h1>

    @foreach ($clients as $client)
        <div>
            <strong>{{ $client['name'] }}</strong>
            — {{ $client['email'] }}
        </div>
    @endforeach

</body>
</html>