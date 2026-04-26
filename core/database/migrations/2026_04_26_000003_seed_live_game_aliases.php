<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_GAMES = [
        'greedy' => 'Greedy',
        'amazing_fish' => 'Amazing Fish',
        'big_battle' => 'Big Battle',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('games')) {
            return;
        }

        $template = DB::table('games')->where('alias', 'teen_patti')->first();
        $now = now();

        foreach (self::NEW_GAMES as $alias => $name) {
            $payload = $this->buildPayload($template, $alias, $name, $now);

            $existing = DB::table('games')->where('alias', $alias)->first();
            if ($existing) {
                DB::table('games')->where('id', $existing->id)->update($payload);
                continue;
            }

            $payload['created_at'] = $now;
            DB::table('games')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('games')) {
            return;
        }

        DB::table('games')->whereIn('alias', array_keys(self::NEW_GAMES))->delete();
    }

    private function buildPayload(?object $template, string $alias, string $name, $now): array
    {
        if ($template) {
            return [
                'name'              => $name,
                'alias'             => $alias,
                'image'             => $template->image,
                'status'            => $template->status,
                'trending'          => $template->trending,
                'featured'          => $template->featured,
                'win'               => $template->win,
                'max_limit'         => $template->max_limit,
                'min_limit'         => $template->min_limit,
                'invest_back'       => $template->invest_back,
                'probable_win'      => $template->probable_win,
                'probable_win_demo' => $template->probable_win_demo,
                'type'              => $template->type,
                'level'             => $template->level,
                'instruction'       => $template->instruction,
                'short_desc'        => $template->short_desc,
                'updated_at'        => $now,
            ];
        }

        return [
            'name'              => $name,
            'alias'             => $alias,
            'image'             => 'teen_patti.png',
            'status'            => 1,
            'trending'          => 0,
            'featured'          => 0,
            'win'               => 95,
            'max_limit'         => 10000,
            'min_limit'         => 10,
            'invest_back'       => 1,
            'probable_win'      => 45,
            'probable_win_demo' => 90,
            'type'              => null,
            'level'             => null,
            'instruction'       => 'Play and place bets based on round outcomes.',
            'short_desc'        => $name . ' live game',
            'updated_at'        => $now,
        ];
    }
};
