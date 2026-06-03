<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $clubFilter = null;
        $query = Transfer::query()
            ->with(['fromClub.countryRecord', 'toClub.countryRecord'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id');

        if ($request->filled('club')) {
            $clubFilter = Club::query()->where('slug', $request->string('club'))->first();
            if ($clubFilter) {
                $query->where(function ($q) use ($clubFilter) {
                    $q->where('from_club_source_id', $clubFilter->source_club_id)
                        ->orWhere('to_club_source_id', $clubFilter->source_club_id);
                });
            }
        }

        $transfers = $query->paginate(60)->withQueryString();

        return view('transfers.index', [
            'transfers' => $transfers,
            'clubFilter' => $clubFilter,
        ]);
    }
}
