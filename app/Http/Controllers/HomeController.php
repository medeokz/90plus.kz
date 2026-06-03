<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\CompactMatchesService;
use App\Services\LeagueStandingsService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(
        LeagueStandingsService $standingsService,
        CompactMatchesService $compactMatchesService
    ): View
    {
        $baseQuery = Article::published()->latest('published_at');

        $sliderItems = (clone $baseQuery)->take(5)->get();
        $articles = (clone $baseQuery)->paginate(15);
        $leagues = $standingsService->getAll();
        $compactMatches = $compactMatchesService->getData();

        return view('home', compact(
            'articles',
            'sliderItems',
            'leagues',
            'compactMatches'
        ));
    }
}
