@extends('Template::layouts.frontend')
@section('content')

    <section class="games-section pt-100 pb-50">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h2 class="mb-0">@lang('All Games')</h2>
                <a href="{{ route('live.games') }}" class="btn btn--gradient btn--sm">@lang('Live Games')</a>
            </div>

            <div class="mb-4">
                <h4 class="mb-2">@lang('Teen Patti')</h4>
                <p class="text-muted mb-0">@lang('Your live Teen Patti table with continuous rounds and wallet sync.')</p>
            </div>
            @if ($games->count())
                <div class="games-section-wrapper">
                    @include('Template::partials.game', ['games' => $games])
                </div>
            @else
                <p class="text-muted">@lang('Teen Patti is not available right now.')</p>
            @endif
        </div>
    </section>

    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif
@endsection
