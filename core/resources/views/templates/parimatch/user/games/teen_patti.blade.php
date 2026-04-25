@extends('Template::layouts.master')
@section('content')

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

            <!-- ===== FANTASY DEALER LADY ===== -->
            <div class="tp-dealer-section">
                <div class="tp-dealer-avatar" id="tpDealerAvatar">
                    <div class="dealer-lady">
                        <div class="lady-hair">
                            <div class="hair-strand left-strand"></div>
                            <div class="hair-strand right-strand"></div>
                        </div>
                        <div class="lady-tiara"><div class="tiara-gem"></div></div>
                        <div class="lady-face">
                            <div class="lady-eye left-eye"><div class="eye-pupil"></div></div>
                            <div class="lady-eye right-eye"><div class="eye-pupil"></div></div>
                            <div class="lady-bindi"></div>
                            <div class="lady-nose"></div>
                            <div class="lady-lips"></div>
                        </div>
                        <div class="lady-earring left-earring"></div>
                        <div class="lady-earring right-earring"></div>
                        <div class="lady-neck"><div class="lady-necklace"></div></div>
                        <div class="lady-dress">
                            <div class="dress-detail"></div>
                            <div class="lady-dupatta"></div>
                        </div>
                        <div class="lady-hand left-hand"></div>
                        <div class="lady-hand right-hand"></div>
                    </div>
                    <div class="dealer-glow-ring"></div>
                    <div class="tp-dealer-name">Dealer Riya</div>
                </div>
                <div class="tp-dealer-status" id="tpDealerStatus">Waiting...</div>
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
                <button class="tp-btn-chart"><i class="fas fa-chart-line"></i></button>
            </div>

            <div class="tp-footer">
                <div class="tp-chips-row">
                    <div class="tp-chip-btn c-400 selected" data-value="400"><span>400</span></div>
                    <div class="tp-chip-btn c-2k" data-value="2000"><span>2K</span></div>
                    <div class="tp-chip-btn c-4k" data-value="4000"><span>4K</span></div>
                    <div class="tp-chip-btn c-20k" data-value="20000"><span>20K</span></div>
                    <div class="tp-chip-btn c-40k" data-value="40000"><span>40K</span></div>
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
                    <button class="tp-btn-repeat" id="tpBtnRepeat">@lang('Repeat')</button>
                </div>
            </div>

            <div class="tp-overlay" id="tpStopOverlay">
                <img src="{{ asset('assets/templates/parimatch/images/stop_betting.png') }}" class="tp-stop-char" alt="stop">
                <div class="tp-stop-sign">
                    <span>@lang('Stop Betting!')</span>
                </div>
            </div>

            <div class="tp-winner-modal" id="tpWinnerModal">
                <div class="tp-avatar-pop">
                    <div class="tp-avatar-pop-frame">
                        <img src="" id="tpWinnerImg" alt="winner">
                    </div>
                    <div class="tp-avatar-pop-sign">
                        <div class="tp-pop-round" id="tpWinnerRoundTitle">@lang('Round Result')</div>
                        <div class="tp-pop-message" id="tpWinnerStatusMsg">@lang('Winner Popup')</div>
                    </div>
                </div>
            </div>

            <!-- ===== ROUND SUMMARY PANEL ===== -->
            <div class="tp-round-summary" id="tpRoundSummary">
                <div class="tp-summary-title" id="tpSummaryTitle">Round Summary</div>
                <div class="tp-summary-hands" id="tpSummaryHands">
                    <!-- Dynamically filled -->
                </div>
                <div class="tp-summary-next">
                    Next round in <span class="countdown-num" id="tpNextRoundCountdown">7</span>
                </div>
            </div>

        </div>
    </div>
</div>

<form id="game" method="post" style="display:none">@csrf</form>
@endsection

@php
    $tpCssVersion = @filemtime(public_path('assets/global/css/game/teen-patti.css')) ?: time();
    $tpPremiumCssVersion = @filemtime(public_path('assets/global/css/game/teen-patti-premium.css')) ?: time();
    $tpJsVersion = @filemtime(public_path('assets/global/js/game/teenPatti.js')) ?: time();
@endphp

@push('style-lib')
    <link href="{{ asset('assets/global/css/game/teen-patti.css') }}?v={{ $tpCssVersion }}" rel="stylesheet">
    <link href="{{ asset('assets/global/css/game/teen-patti-premium.css') }}?v={{ $tpPremiumCssVersion }}" rel="stylesheet">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/soundControl.js') }}"></script>
    <script src="{{ asset('assets/global/js/game/teenPatti.js') }}?v={{ $tpJsVersion }}"></script>
@endpush

@push('script')
<script>
"use strict";
let imagePath = "{{ asset(activeTemplate(true) . 'images/cards/') }}";
let avatarPath = "{{ asset('assets/templates/parimatch/images/') }}";
let cardBackImage = "{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}";
let investUrl = "{{ route('user.play.invest', ['teen_patti', @$isDemo]) }}";
let syncUrl = "{{ route('user.play.teen_patti.global.sync', [@$isDemo]) }}";
let gameEndUrl = "{{ route('user.play.end', ['teen_patti', @$isDemo]) }}";
let tpAudioAssetPath = "{{ asset('assets/audio') }}";
let currentUserId = "{{ auth()->id() }}";
</script>
@endpush
