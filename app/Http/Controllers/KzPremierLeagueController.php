<?php

namespace App\Http\Controllers;

use App\Services\KzPremierLeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class KzPremierLeagueController extends Controller
{
    private const TABS = ['tournament', 'schedule', 'results', 'stadiums', 'teams'];

    private const FEED_TABS = ['schedule', 'results'];

    public function show(KzPremierLeagueService $service, string $tab = 'tournament'): View
    {
        if (! in_array($tab, self::TABS, true)) {
            abort(404);
        }

        return view('competitions.kz-premier-liga.show', $service->getPageData($tab));
    }

    public function feed(KzPremierLeagueService $service, string $tab): JsonResponse
    {
        if (! in_array($tab, self::FEED_TABS, true)) {
            abort(404);
        }

        $data = $service->getPageData($tab);
        $fixturesByDate = $tab === 'schedule' ? $data['schedule'] : $data['results'];

        return response()->json([
            'html' => view('competitions.world-cup.partials.fixtures-list', [
                'fixturesByDate' => $fixturesByDate,
                'empty' => $tab === 'schedule'
                    ? 'Алдағы ойындар жоқ.'
                    : 'Аяқталған ойындар жоқ.',
            ])->render(),
            'played' => $data['overview']['played'],
            'stage' => $data['overview']['stage'],
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
