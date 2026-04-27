@extends('Template::layouts.master')
@section('content')
<script>
document.body.classList.add('tp-game-page');
</script>

<div class="row align-items-center justify-content-center min-vh-100 p-0 m-0">
    <div class="col-12 col-md-8 col-lg-5 p-0">
        <div class="tp-game-wrapper" id="tpGameWrapper">

            <div class="tp-header">
                <a href="{{ route('user.home') }}" class="tp-btn-icon"><i class="fas fa-times"></i></a>
                <div class="tp-logo">
                    <div class="tp-logo-icon"><i class="fas fa-crown"></i></div>
                    <div class="tp-logo-text">Royal<br>Teen Patti</div>
                </div>
                <div class="tp-header-right">
                    <button class="tp-btn-icon audioBtn"><i class="fas fa-volume-up"></i></button>
                    <button class="tp-btn-icon" onclick="window.location.reload()"><i class="fas fa-sync-alt"></i></button>
                    <button class="tp-btn-icon"><i class="fas fa-cog"></i></button>
                </div>
            </div>

            <div class="tp-timer-section">
                <div id="tpPhaseBadge" class="tp-phase-badge phase-betting">@lang('Place Your Bets')</div>
                <div class="tp-timer-p">
                    <div class="tp-timer-clock"><i class="far fa-clock"></i></div>
                    <div class="tp-timer-num" id="tpTimer">20</div>
                </div>
            </div>

            <!-- ===== CARD DEALER ANCHOR (invisible origin for flying cards) ===== -->
            <div class="tp-deal-anchor" id="tpDealerAvatar" aria-hidden="true">
                <div class="tp-shuffle-deck" id="tpShuffleDeck">
                    <div class="tp-shuffle-card shuffle-1"></div>
                    <div class="tp-shuffle-card shuffle-2"></div>
                    <div class="tp-shuffle-card shuffle-3"></div>
                </div>
            </div>

            <!-- Narration Bar -->
            <div class="tp-narration-bar" id="tpNarrationBar">
                <div class="tp-narration-icon"><i class="fas fa-comment-dots"></i></div>
                <div class="tp-narration-text" id="tpNarrationText"></div>
            </div>

            <div class="tp-play-area">
                <div class="tp-columns">

                    <div class="tp-col" id="tpColSilver" data-choose="silver">
                        <div class="tp-char-frame silver-border">
                            <div class="tp-char-label">S</div>
                            <img src="{{ asset('assets/templates/parimatch/images/silver_character.png') }}" alt="Silver">
                        </div>
                        <div class="tp-side-name">@lang('Silver')</div>
                        <div class="tp-cards-row" id="tpCardsSilver">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                        </div>
                        <div class="tp-hand-rank" id="tpRankSilver">--</div>
                        <div class="tp-bet-slot silver-bg" id="tpSlotSilver">
                            <div class="tp-chips-pile" id="tpPileSilver"></div>
                            <div class="tp-bet-info">
                                <div>All: <span id="tpAllSilver">0</span></div>
                                <div class="gold-text">You: <span id="tpYouSilver">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-col" id="tpColGold" data-choose="gold">
                        <div class="tp-char-frame gold-border">
                            <div class="tp-char-label">G</div>
                            <img src="{{ asset('assets/templates/parimatch/images/gold_character.png') }}" alt="Gold">
                        </div>
                        <div class="tp-side-name">@lang('Gold')</div>
                        <div class="tp-cards-row" id="tpCardsGold">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                        </div>
                        <div class="tp-hand-rank" id="tpRankGold">--</div>
                        <div class="tp-bet-slot gold-bg" id="tpSlotGold">
                            <div class="tp-chips-pile" id="tpPileGold"></div>
                            <div class="tp-bet-info">
                                <div>All: <span id="tpAllGold">0</span></div>
                                <div class="gold-text">You: <span id="tpYouGold">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-col" id="tpColDiamond" data-choose="diamond">
                        <div class="tp-char-frame diamond-border">
                            <div class="tp-char-label">D</div>
                            <img src="{{ asset('assets/templates/parimatch/images/diamond_character.png') }}" alt="Diamond">
                        </div>
                        <div class="tp-side-name">@lang('Diamond')</div>
                        <div class="tp-cards-row" id="tpCardsDiamond">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                        </div>
                        <div class="tp-hand-rank" id="tpRankDiamond">--</div>
                        <div class="tp-bet-slot diamond-bg" id="tpSlotDiamond">
                            <div class="tp-chips-pile" id="tpPileDiamond"></div>
                            <div class="tp-bet-info">
                                <div>All: <span id="tpAllDiamond">0</span></div>
                                <div class="gold-text">You: <span id="tpYouDiamond">0</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tp-history-bar">
                <span class="tp-hist-label">Result</span>
                <div class="tp-hist-items" id="tpHistory"></div>
            </div>

            <div class="tp-footer">
                @php
                    $teenPattiChipValues = array_values($teenPattiChipValues ?? \App\Models\Tenant::DEFAULT_TEEN_PATTI_CHIPS);
                    $chipLabel = static function ($amount) {
                        $amount = (int) $amount;
                        return $amount >= 1000 && $amount % 1000 === 0 ? ((int) ($amount / 1000)) . 'K' : number_format($amount);
                    };
                @endphp
                <div class="tp-chips-row">
                    @foreach($teenPattiChipValues as $index => $chipAmount)
                        <div class="tp-chip-btn chip-color-{{ $index % 5 }} {{ $index === 0 ? 'selected' : '' }}" data-value="{{ (int) $chipAmount }}">
                            <span>{{ $chipLabel($chipAmount) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="tp-bottom-actions">
                    <div class="tp-bal-p">
                        <div class="tp-bal-icon"><i class="fas fa-coins"></i></div>
                        <div class="tp-bal-val bal">{{ showAmount($balance, currencyFormat: false) }}</div>
                    </div>
                    <div class="tp-win-p">
                        <span class="tp-win-label">WIN</span>
                        <span class="tp-win-val" id="tpWinVal">0</span>
                    </div>
                    <button class="tp-btn-repeat tp-btn-repeat--icon" id="tpBtnRepeat" type="button" title="@lang('Repeat last bet')" aria-label="@lang('Repeat last bet')">
                        <i class="fas fa-redo-alt" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="tp-overlay" id="tpStopOverlay">
                <img src="{{ asset('assets/templates/parimatch/images/dealer_avatar_body.png') }}" class="tp-stop-char tp-stop-avatar" alt="Betting Stopped Avatar">
                <div class="tp-stop-sign">
                    <span>@lang('Stop Betting!')</span>
                </div>
            </div>

            <!-- ===== WIN CELEBRATION MODAL ===== -->
            <div class="tp-win-modal" id="tpWinnerModal" aria-hidden="true">
                <div class="tp-win-modal__backdrop"></div>
                <div class="tp-win-modal__confetti" id="tpWinConfetti" aria-hidden="true"></div>
                <div class="tp-win-modal__card" role="dialog" aria-live="polite">
                    <div class="tp-win-modal__rays" aria-hidden="true"></div>
                    <div class="tp-win-modal__ring" aria-hidden="true"></div>
                    <div class="tp-win-modal__crown" aria-hidden="true"><i class="fas fa-crown"></i></div>
                    <div class="tp-win-modal__title" id="tpWinnerRoundTitle">@lang('You Win')</div>
                    <div class="tp-win-modal__amount" id="tpWinAmount">
                        <span class="tp-win-modal__currency">+</span>
                        <span class="tp-win-modal__amount-value" id="tpWinAmountValue">0</span>
                    </div>
                    <div class="tp-win-modal__message" id="tpWinnerStatusMsg">@lang('Congratulations!')</div>
                    <img src="" id="tpWinnerImg" alt="" class="tp-win-modal__avatar" aria-hidden="true">
                </div>
            </div>

        </div>
    </div>
</div>

<form id="game" method="post" style="display:none">@csrf</form>
@endsection

@php
    $tpCssVersion = @filemtime(public_path('assets/global/css/game/teen-patti.css')) ?: time();
    $tpJsVersion = @filemtime(public_path('assets/global/js/game/teenPatti.js')) ?: time();
@endphp

@push('style-lib')
    <link href="{{ asset('assets/global/css/game/teen-patti.css') }}?v={{ $tpCssVersion }}" rel="stylesheet">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/soundControl.js') }}"></script>
@endpush

@push('script')
<script>
"use strict";
document.body.classList.add('tp-game-page');
let imagePath = "{{ asset(activeTemplate(true) . 'images/cards/') }}";
let tpCardDeckPaths = [
    "{{ asset(activeTemplate(true) . 'images/cards/') }}",
    "{{ asset('assets/templates/parimatch/images/cards/') }}",
    "{{ asset('assets/templates/sunfyre/images/cards/') }}",
    "{{ asset('assets/templates/basic/images/cards/') }}"
];
let avatarPath = "{{ asset('assets/templates/parimatch/images/') }}";
let cardBackImage = "{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}";
let investUrl = "{{ route('user.play.invest', ['teen_patti', @$isDemo]) }}";
let syncUrl = "{{ route('user.play.teen_patti.global.sync', [@$isDemo]) }}";
let gameEndUrl = "{{ route('user.play.end', ['teen_patti', @$isDemo]) }}";
let tpAudioAssetPath = "{{ asset('assets/audio') }}";
let currentUserId = "{{ auth()->id() }}";
let tpChipValues = @json(array_values($teenPattiChipValues ?? \App\Models\Tenant::DEFAULT_TEEN_PATTI_CHIPS));
</script>
<script src="{{ asset('assets/global/js/game/teenPatti.js') }}?v={{ $tpJsVersion }}"></script>
@endpush
