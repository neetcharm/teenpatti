# Teen Patti Game - PRD

## Original Problem Statement
> In Teen Patti game, the Repeat button was not working. Requirement:
> - Configure the repeat action as per user's last bet amount and table.
> - One click on Repeat → place bets automatically as per last round's bet.
> - Two clicks on Repeat → place bets with 2x chips of last round.
> - Convert the Repeat button to an icon-based button (not full "Repeat" text) so it fits nicely in the total balance row.

## Stack
- PHP / Laravel (Blade views)
- Client-side game logic in `/app/assets/global/js/game/teenPatti.js`
- CSS in `/app/assets/global/css/game/teen-patti.css`

## What was implemented (Jan 2026)
### Repeat Button Fix
- Introduced a persistent `lastRoundBets` state (silver/gold/diamond) that is captured the moment the round changes in `updateUI` (i.e. right before `latestMyBets` is overwritten with the new round's data). This is what the previous logic was missing — it was only ever looking at current-round bets, which reset to 0 on every new round.
- Persisted `lastRoundBets` in `localStorage` keyed by user id (`tp_last_round_bets_<uid>`) so the repeat action survives page refreshes and tab switches.
- Loaded the persisted value during game boot (`loadLastRoundBetsFromStorage()` before the first sync).
- Rewrote the `#tpBtnRepeat` click handler to place each side's bet with the exact last-round amount in a single API call (via a new optional `overrideAmount` parameter on `placeGlobalBet`).
- Natural stacking: each click places 1× of the last-round bet. Two clicks therefore place 2× total, matching the spec.

### Icon-based Repeat Button
- Replaced the `<button>Repeat</button>` in all three templates (basic, parimatch, sunfyre) with a compact circular icon button using `fa-redo-alt`, with proper `title` / `aria-label` for accessibility.
- Added `.tp-btn-repeat--icon` styles (38×38 pill/round, gold gradient, hover/active states, responsive 34×34 on small screens) so the button fits cleanly next to the balance + WIN pills in the bottom actions row.

## Files Modified
- `/app/assets/global/js/game/teenPatti.js`
- `/app/assets/global/css/game/teen-patti.css`
- `/app/core/resources/views/templates/basic/user/games/teen_patti.blade.php`
- `/app/core/resources/views/templates/parimatch/user/games/teen_patti.blade.php`
- `/app/core/resources/views/templates/sunfyre/user/games/teen_patti.blade.php`

## Backlog / Next
- Optional: Add a small tooltip/label showing the exact amount that will be placed on hover of the repeat icon.
- Optional: Disable the repeat button visually (greyed) when there are no last-round bets and during the hold phase.
- Optional: Server-side "last round bets" endpoint so repeat works even on a fresh device / cleared browser storage.
