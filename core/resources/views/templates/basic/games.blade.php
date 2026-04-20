@extends('Template::layouts.frontend')
@section('content')

    <section class="pt-120 pb-120 section--bg">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h2 class="mb-0">@lang('All Games')</h2>
                <a href="{{ route('live.games') }}" class="cmn-btn btn-sm">@lang('Live Games')</a>
            </div>

            <div class="mb-4">
                <h4 class="mb-2">@lang('Teen Patti')</h4>
                <p class="text-muted mb-0">@lang('Your live Teen Patti table with continuous rounds and wallet sync.')</p>
            </div>
            @if ($games->count())
                <div class="row justify-content-center mb-none-30">
                    @include('Template::partials.game_card', ['games' => $games])
                </div>
            @else
                <p class="text-muted">@lang('Teen Patti is not available right now.')</p>
            @endif
        </div>
    </section>

    @if ($sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif
@endsection
