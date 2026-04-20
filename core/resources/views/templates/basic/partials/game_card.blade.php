@foreach ($games as $game)
    <div class="col-xl-3 col-lg-4 col-sm-6 mb-30 wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.3s">
        <div class="game-card">
            <div class="game-card__thumb position-relative">
                <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}" alt="image">
                @if (in_array($game->alias, liveGameAliases()))
                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">@lang('LIVE')</span>
                @endif
            </div>
            <div class="game-card__content">
                <h4 class="game-name">{{ __($game->name) }}</h4>
                <div class="d-flex justify-content-between gap-2 w-100 mt-2">
                    <a class="cmn-btn play_btn w-100 btn-sm text-center" href="{{ route('user.play.game', $game->alias) }}">
                        @lang('Play Now')
                    </a>
                    <a class="cmn-btn-two play_btn w-100 btn-sm text-center"
                        href="{{ route('user.play.game', [$game->alias, 'demo']) }}">
                        @lang('Demo')
                    </a>
                </div>
            </div>
        </div>
    </div>
@endforeach
