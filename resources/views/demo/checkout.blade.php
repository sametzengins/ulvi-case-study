@extends('demo.layout')

@section('title', 'Checkout')

@section('body')
<div class="card mx-auto mt-12 flex max-w-md flex-col gap-3">
    <h1 class="font-medium">Checkout</h1>
    <p class="text-base">{{ $session['description'] }}</p>
    <p class="text-2xl font-medium">{{ \Illuminate\Support\Number::currency($session['amount_total'] / 100, in: strtoupper($session['currency']), locale: 'tr') }}</p>
    <form method="post" action="{{ route('demo.checkout.pay', $session['id']) }}">
        @csrf
        <button class="btn-primary" @disabled($session['payment_status'] === 'paid')>Pay</button>
    </form>
</div>
@endsection
