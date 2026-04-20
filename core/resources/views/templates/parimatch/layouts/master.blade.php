@extends('Template::layouts.app')
@section('app')
    @include('Template::partials.user_header')

    @if (!request()->routeIs('home'))
        @include('Template::partials.breadcrumb')
    @endif

    <section class="py-100">
        <div class="container">
            @yield('content')
        </div>
    </section>


    @include('Template::partials.footer')
    @include('Template::partials.fantasy_animations')
@endsection


@push('script')
    <script>
        "use strict";
        $(document).on('click touchstart', function(e) {
            $('.win-loss-popup').removeClass('active');
        });
    </script>

    @if (request()->routeIs('user.play.game'))
        @php
            $alias = request()->route('alias');
            $isLiveGame = in_array($alias, liveGameAliases());
            $enableExternalAutoBet = in_array($alias, liveAutoBetAliases());
            $roundInterval = isset($game) ? gameAutoBetDelay($game) : 15;
        @endphp
        <script>
            window.liveGameConfig = {
                alias: @json($alias),
                isLiveGame: @json($isLiveGame),
                enableExternalAutoBet: @json($enableExternalAutoBet),
                roundInterval: @json($roundInterval),
                autoBetMusic: "coin.mp3",
                statsEndpoint: @json(route('live.stats', ['alias' => $alias])),
                currencyText: @json(gs('cur_text'))
            };
        </script>
        <script src="{{ asset('assets/global/js/game/liveBetFeatures.js') }}"></script>
    @endif
@endpush
