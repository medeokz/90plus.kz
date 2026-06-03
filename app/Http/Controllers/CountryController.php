<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function show(string $slug): View
    {
        $country = Country::query()->where('slug', $slug)->firstOrFail();
        $clubs = $country->clubs()
            ->orderBy('name')
            ->paginate(60);

        return view('countries.show', compact('country', 'clubs'));
    }
}
