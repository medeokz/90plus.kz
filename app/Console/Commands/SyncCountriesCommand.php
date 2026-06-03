<?php

namespace App\Console\Commands;

use App\Services\Soccer365ClubService;
use App\Services\Soccer365CountryService;
use Illuminate\Console\Command;

class SyncCountriesCommand extends Command
{
    protected $signature = 'countries:sync {--pages=5 : Pages to scan on /countries/} {--link-clubs : Link existing clubs to countries}';

    protected $description = 'Sync countries list and flags from soccer365.ru';

    public function handle(Soccer365CountryService $service, Soccer365ClubService $clubService): int
    {
        $pages = max(1, (int) $this->option('pages'));
        $count = $service->syncAll($pages);
        $this->info("Synced {$count} countries.");

        $fixed = 0;
        \App\Models\Country::query()->each(function ($country) use (&$fixed) {
            $flag = \App\Support\Soccer365ImageUrl::flag($country->source_country_id);
            if ($country->getRawOriginal('flag_url') !== $flag) {
                $country->update(['flag_url' => $flag]);
                $fixed++;
            }
        });
        $this->info("Fixed {$fixed} country flags.");

        if ($this->option('link-clubs')) {
            $linked = $clubService->linkAllClubsToCountries();
            $this->info("Linked {$linked} clubs to countries.");
        }

        return self::SUCCESS;
    }
}
