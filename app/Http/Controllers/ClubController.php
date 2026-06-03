<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Country;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'clubs') === 'countries' ? 'countries' : 'clubs';

        if ($tab === 'countries') {
            $countries = Country::query()
                ->withCount('clubs')
                ->orderBy('name')
                ->paginate(60)
                ->appends(['tab' => 'countries']);

            return view('clubs.index', compact('countries', 'tab'));
        }

        $clubs = Club::query()
            ->with('countryRecord')
            ->orderBy('name')
            ->paginate(60)
            ->appends(['tab' => 'clubs']);

        return view('clubs.index', compact('clubs', 'tab'));
    }

    public function show(Request $request, string $slug): View
    {
        $club = Club::query()
            ->with('countryRecord')
            ->where('slug', $slug)
            ->firstOrFail();

        $tab = match ($request->query('tab')) {
            'squad', 'results', 'schedule' => $request->query('tab'),
            default => 'info',
        };

        $players = $club->players()
            ->orderByRaw('CAST(club_player.number AS UNSIGNED)')
            ->orderBy('players.name')
            ->get();

        $recentTransfers = Transfer::query()
            ->with(['fromClub.countryRecord', 'toClub.countryRecord'])
            ->where(function ($q) use ($club) {
                $q->where('from_club_source_id', $club->source_club_id)
                    ->orWhere('to_club_source_id', $club->source_club_id);
            })
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $profile = $club->profile_data ?? [];

        return view('clubs.show', compact(
            'club',
            'players',
            'recentTransfers',
            'tab',
            'profile',
        ));
    }
}
