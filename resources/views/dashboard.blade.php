@extends('layouts.app')

@section('title', 'Dashboard — FreelanceFlow')

@section('content')
    <h1 class="text-2xl font-semibold">Dashboard</h1>
    <p class="mt-2 text-gray-500">Welcome back, {{ auth()->user()->name }}.</p>
@endsection