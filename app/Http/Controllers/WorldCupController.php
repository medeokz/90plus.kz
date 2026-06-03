<?php

namespace App\Http\Controllers;

use App\Services\WorldCupService;
use Illuminate\View\View;

class WorldCupController extends Controller
{
    public function show(WorldCupService $service, string $tab = 'tournament'): View
    {
        $allowed = ['tournament', 'schedule', 'results'];

        if (! in_array($tab, $allowed, true)) {
            abort(404);
        }

        $data = $service->getPageData($tab);

        return view('competitions.world-cup.show', $data);
    }
}
