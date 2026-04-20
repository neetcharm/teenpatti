@foreach ($games as $game)
    <div class="pm-game-card">
        <div class="position-relative">
            <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}" alt="{{ __($game->name) }}">
            @if (in_array($game->alias, liveGameAliases()))
                <span class="badge bg-danger position-absolute top-0 end-0 m-2" style="z-index: 10;">@lang('LIVE')</span>
            @endif
        </div>
        <div class="pm-game-title">
            {{ __($game->name) }}
        </div>
        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center align-items-center" style="background: rgba(0,0,0,0.7); opacity: 0; transition: 0.3s; z-index: 5;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
            <a href="{{ route('user.play.game', $game->alias) }}" class="btn btn--base btn-sm w-75 mb-2">@lang('Play')</a>
            <a href="{{ route('user.play.game', [$game->alias, 'demo']) }}" class="btn btn-outline-light btn-sm w-75">@lang('Demo')</a>
        </div>
    </div>
@endforeach
