<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Services\FixtureStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class FixtureController extends Controller
{
    public function show(int $externalId, FixtureStatsService $statsService): View
    {
        $fixture = $statsService->findOrFetch($externalId);

        abort_unless($fixture, 404);

        return view('fixtures.show', compact('fixture'));
    }

    public function index(FixtureStatsService $statsService): View|JsonResponse
    {
        $statsService->refreshForIndex();

        if (request()->expectsJson()) {
            return response()->json([
                'fixtures' => $statsService->indexPayload(),
                'updated_at' => now()->toIso8601String(),
            ]);
        }

        return view('fixtures.index', [
            'fixtures' => $statsService->indexFixtures(),
        ]);
    }

    public function feed(FixtureStatsService $statsService): JsonResponse
    {
        $statsService->refreshForIndex(true);
        $fixtures = $statsService->indexFixtures();

        return response()->json([
            'html' => view('fixtures.partials.list', compact('fixtures'))->render(),
            'fixtures' => $statsService->indexPayload(),
            'has_live' => $fixtures->contains(fn (Fixture $f) => $f->isLive()),
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
