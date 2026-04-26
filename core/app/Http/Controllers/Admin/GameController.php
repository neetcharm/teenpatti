<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameLog;

use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index()
    {
        $pageTitle = "Games";
        $games     = Game::whereIn('alias', liveGameAliases())->searchable(['name'])->orderBy('id', 'desc')->get();
        return view('admin.game.index', compact('pageTitle', 'games'));
    }

    public function edit($id)
    {
        $game      = Game::whereIn('alias', liveGameAliases())->findOrFail($id);
        $pageTitle = "Update " . $game->name;
        $bonuses   = null;

        return view('admin.game.game_edit', compact('pageTitle', 'game', 'bonuses'));
    }

    public function update(Request $request, $id)
    {
        $game = Game::whereIn('alias', liveGameAliases())->findOrFail($id);
        $probabilityRules = [
            'probable'      => 'required|integer|min:0|max:100',
            'probable_demo' => 'required|integer|min:0|max:100',
        ];

        $request->validate(array_merge([
            'name'          => 'required',
            'min'           => 'required|numeric',
            'max'           => 'required|numeric',
            'instruction'   => 'required',
            'win'           => 'sometimes|required|numeric',
            'invest_back'   => 'sometimes|required',
            'trending'      => 'sometimes|required',
            'featured'      => 'sometimes|required',
            'level.*'       => 'sometimes|required',
            'chance.*'      => 'sometimes|required|numeric',
            'auto_bet_delay'=> 'nullable|integer|min:5|max:120',
            'image'         => ['nullable', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ], $probabilityRules), [
            'level.0.required'  => 'Level 1 field is required',
            'level.1.required'  => 'Level 2 field is required',
            'level.2.required'  => 'Level 3 field is required',
            'chance.0.required' => 'No win chance field required',
            'chance.1.required' => 'Double win chance field is required',
            'chance.2.required' => 'Single win chance field is required',
            'chance.3.required' => 'Triple win field is required',
            'chance.*.numeric'  => 'Chance field must be a number',
        ]);
        $winChance     = $request->probable;
        $winChanceDemo = $request->probable_demo;

        if (isset($request->chance)) {

            if (array_sum($request->chance) != 100) {
                $notify[] = ['error', 'The sum of winning chance must be equal of 100'];
                return back()->withNotify($notify);
            }

            $winChance     = $request->chance;
            $winChanceDemo = $request->chance;
        }

        $game->name              = $request->name;
        $game->min_limit         = $request->min;
        $game->max_limit         = $request->max;
        $game->probable_win      = $winChance;
        $game->probable_win_demo = $winChanceDemo;
        $game->invest_back       = $request->invest_back ? Status::YES : Status::NO;
        $game->trending          = $request->trending ? Status::YES : Status::NO;
        $game->featured          = $request->featured ? Status::YES : Status::NO;
        $game->instruction       = $request->instruction;
        $game->short_desc        = $request->short_desc;
        $game->level             = $request->level;
        $game->win               = $request->win;
        $autoBetDelay = $request->filled('auto_bet_delay') ? (int) $request->auto_bet_delay : gameAutoBetDelay($game);
        $this->setAutoBetDelayMeta($game, $autoBetDelay);

        $oldImage = $game->image;

        if ($request->hasFile('image')) {
            try {
                $game->image = fileUploader($request->image, getFilePath('game'), getFileSize('game'), $oldImage);
            } catch (\Exception $e) {
                $notify[] = ['error', 'Could not upload the Image.'];
                return back()->withNotify($notify);
            }
        }

        $game->save();

        $notify[] = ['success', 'Game updated successfully'];
        return back()->withNotify($notify);
    }

    public function gameLog(Request $request)
    {
        $pageTitle = "Game Logs";
        $logs      = GameLog::teenPatti()->where('status', Status::ENABLE)->searchable(['user:username'])->filter(['win_status'])->with('user', 'game')->latest('id')->paginate(getPaginate());
        return view('admin.game.log', compact('pageTitle', 'logs'));
    }

    public function chanceCreate(Request $request, $alias = null)
    {
        abort_unless(in_array($alias, liveGameAliases(), true), 404);

        $notify[] = ['info', 'Chance bonus configuration is not applicable for Teen Patti.'];
        return back()->withNotify($notify);
    }

    public function status($id)
    {
        $game = Game::whereIn('alias', liveGameAliases())->findOrFail($id);

        if ($game->status == Status::ENABLE) {
            $game->status = Status::DISABLE;
            $notify[]     = ['success', $game->name . ' disabled successfully'];
        } else {
            $game->status = Status::ENABLE;
            $notify[]     = ['success', $game->name . ' enabled successfully'];
        }

        $game->save();
        return back()->withNotify($notify);
    }

    private function setAutoBetDelayMeta(Game $game, int $delay): void
    {
        $delay = max(5, min($delay, 120));
        $meta  = gameMeta($game);
        $rawType = $game->type;

        if (!$meta && is_string($rawType) && trim($rawType) !== '') {
            $meta['legacy_type'] = $rawType;
        }

        $meta['auto_bet_delay'] = $delay;
        $game->type             = json_encode($meta);
    }
}
