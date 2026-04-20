@extends('admin.layouts.app')

@push('style')
<style>
/* ── Game Assignment Page ─────────────────────────────────────── */
.game-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
}

.game-card {
    position: relative;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    transition: transform .2s, box-shadow .2s;
    border: 2px solid transparent;
}

.game-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,.12);
}

.game-card.is-enabled {
    border-color: #4ade80;
    background: linear-gradient(135deg,#f0fff4,#fff);
}

.game-card.is-disabled {
    opacity: .65;
    filter: grayscale(.5);
}

.game-card__thumb {
    width: 100%;
    height: 130px;
    object-fit: cover;
    display: block;
}

.game-card__thumb-placeholder {
    width: 100%;
    height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: #fff;
}

.game-card__body {
    padding: .85rem 1rem;
}

.game-card__name {
    font-size: .88rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: .15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.game-card__alias {
    font-size: .72rem;
    color: #94a3b8;
    margin-bottom: .65rem;
    font-family: monospace;
}

.game-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Toggle switch */
.tgl-wrap {
    display: flex;
    align-items: center;
    gap: .45rem;
    cursor: pointer;
    user-select: none;
}

.tgl-wrap input[type=checkbox] { display: none; }

.tgl-track {
    width: 42px;
    height: 22px;
    border-radius: 11px;
    background: #e2e8f0;
    position: relative;
    transition: background .25s;
    flex-shrink: 0;
}

.tgl-track::after {
    content: '';
    position: absolute;
    top: 3px; left: 3px;
    width: 16px; height: 16px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
    transition: left .25s;
}

input:checked + .tgl-track {
    background: #22c55e;
}

input:checked + .tgl-track::after {
    left: 23px;
}

.tgl-label {
    font-size: .78rem;
    font-weight: 600;
    color: #64748b;
}

input:checked ~ .tgl-label {
    color: #16a34a;
}

/* Status pill on card corner */
.game-status-pill {
    position: absolute;
    top: 10px; right: 10px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    pointer-events: none;
}

.game-status-pill.on  { background:#22c55e; color:#fff; }
.game-status-pill.off { background:#e2e8f0; color:#64748b; }

/* Select all bar */
.select-bar {
    background: linear-gradient(135deg,#1e3a5f,#0f2847);
    border-radius: 12px;
    padding: .9rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    color: #fff;
}

.select-bar h6 { margin: 0; font-weight: 600; font-size: .9rem; }
.select-bar small { opacity: .7; display: block; font-size: .75rem; }

.btn-select { font-size: .78rem; padding: 5px 14px; border-radius: 8px; margin-left: 6px; }
.btn-select-all { background:#4ade80; color:#0f2847; border:none; }
.btn-deselect-all { background: rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.25); }
</style>
@endpush

@section('panel')

{{-- Header --}}
<div class="row mb-4">
    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4 class="mb-1">
                <i class="las la-gamepad text-primary me-1"></i>
                Game Access
            </h4>
            <p class="text-muted mb-0">
                Tenant: <strong>{{ $tenant->name }}</strong>
                <span class="ms-2 badge {{ $tenant->balance_mode === 'internal' ? 'bg--success' : 'bg--warning' }}">
                    {{ strtoupper($tenant->balance_mode) }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tenants.index') }}" class="btn btn-sm btn-outline--secondary">
                <i class="las la-arrow-left"></i> Back
            </a>
            <a href="{{ route('admin.tenants.edit', $tenant->id) }}" class="btn btn-sm btn-outline--primary">
                <i class="las la-edit"></i> Edit Tenant
            </a>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.tenants.games.update', $tenant->id) }}" id="gamesForm">
@csrf

{{-- Select all / Deselect all bar --}}
<div class="select-bar">
    <div>
        <h6><i class="las la-sliders-h me-1"></i>Manage Game Access</h6>
        <small id="enabledCount">Loading…</small>
    </div>
    <div>
        <button type="button" class="btn btn-select btn-select-all" onclick="selectAll()">
            <i class="las la-check-double"></i> Enable All
        </button>
        <button type="button" class="btn btn-select btn-deselect-all" onclick="deselectAll()">
            <i class="las la-times"></i> Disable All
        </button>
    </div>
</div>

{{-- Game Cards Grid --}}
<div class="game-grid" id="gameGrid">
    @forelse($allGames as $game)
        @php
            $tg      = $assigned->get($game->alias);
            // If no row exists yet, default to enabled
            $isOn    = $tg ? (bool) $tg->enabled : true;
            $imgFile = $game->alias . '.png';
            $imgPath = public_path('assets/images/game/' . $imgFile);
            $hasImg  = file_exists($imgPath);

            $emoji = '🃏';
        @endphp

        <div class="game-card {{ $isOn ? 'is-enabled' : 'is-disabled' }}" data-card>
            {{-- Thumbnail --}}
            @if($hasImg)
                <img src="{{ asset('assets/images/game/' . $imgFile) }}"
                     alt="{{ $game->name }}" class="game-card__thumb" loading="lazy">
            @else
                <div class="game-card__thumb-placeholder">{{ $emoji }}</div>
            @endif

            {{-- Status pill --}}
            <span class="game-status-pill {{ $isOn ? 'on' : 'off' }}" data-pill>
                {{ $isOn ? 'ON' : 'OFF' }}
            </span>

            <div class="game-card__body">
                <div class="game-card__name" title="{{ $game->name }}">{{ $game->name }}</div>
                <div class="game-card__alias">{{ $game->alias }}</div>

                <div class="game-card__footer">
                    <label class="tgl-wrap" title="Toggle {{ $game->name }}">
                        <input type="checkbox"
                               name="enabled[]"
                               value="{{ $game->alias }}"
                               {{ $isOn ? 'checked' : '' }}
                               onchange="syncCard(this)">
                        <span class="tgl-track"></span>
                        <span class="tgl-label">{{ $isOn ? 'Enabled' : 'Disabled' }}</span>
                    </label>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-5">
            No active games found in the platform.
        </div>
    @endforelse
</div>

{{-- Save button --}}
<div class="mt-4 d-flex justify-content-end">
    <button type="submit" class="btn btn--primary px-5">
        <i class="las la-save me-1"></i> Save Game Access
    </button>
</div>

</form>

@endsection

@push('script')
<script>
function syncCard(checkbox) {
    const card = checkbox.closest('[data-card]');
    const pill = card.querySelector('[data-pill]');
    const label = checkbox.nextElementSibling.nextElementSibling; // .tgl-label

    if (checkbox.checked) {
        card.classList.add('is-enabled');
        card.classList.remove('is-disabled');
        pill.textContent = 'ON';
        pill.className = 'game-status-pill on';
        label.textContent = 'Enabled';
        label.style.color = '#16a34a';
    } else {
        card.classList.remove('is-enabled');
        card.classList.add('is-disabled');
        pill.textContent = 'OFF';
        pill.className = 'game-status-pill off';
        label.textContent = 'Disabled';
        label.style.color = '';
    }

    updateCount();
}

function selectAll() {
    document.querySelectorAll('[data-card] input[type=checkbox]').forEach(cb => {
        cb.checked = true;
        syncCard(cb);
    });
}

function deselectAll() {
    document.querySelectorAll('[data-card] input[type=checkbox]').forEach(cb => {
        cb.checked = false;
        syncCard(cb);
    });
}

function updateCount() {
    const total   = document.querySelectorAll('[data-card]').length;
    const enabled = document.querySelectorAll('[data-card] input:checked').length;
    document.getElementById('enabledCount').textContent =
        enabled + ' of ' + total + ' games enabled for this tenant';
}

document.addEventListener('DOMContentLoaded', updateCount);
</script>
@endpush
