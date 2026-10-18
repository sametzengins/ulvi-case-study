@extends('demo.layout')

@section('title', 'Paid')

@section('body')
<div class="card mx-auto mt-12 flex max-w-xl flex-col gap-3">
    <h1 class="font-medium">Paid</h1>
    <table class="w-full text-left">
        <thead class="muted"><tr><th class="py-1 pr-4 font-normal">delivery</th><th class="py-1 pr-4 font-normal">event</th><th class="py-1 font-normal">result</th></tr></thead>
        <tbody>
        @foreach ($deliveries as $delivery)
            <tr class="border-t border-[#e3e3e0] dark:border-[#3E3E3A]">
                <td class="py-1 pr-4">#{{ $delivery['attempt'] }}</td>
                <td class="py-1 pr-4">{{ $delivery['type'] }}</td>
                <td class="py-1">{{ $delivery['duplicate'] ? 'duplicate, ignored' : 'stored, queued for processing' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
