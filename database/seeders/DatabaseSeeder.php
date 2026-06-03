<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Fixture;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@football.kz'],
            ['name' => 'Admin', 'password' => Hash::make('admin123')]
        );

        $this->seedDemoArticles();
        $this->seedUsaSenegalFixture();
    }

    private function seedDemoArticles(): void
    {
        $articles = [
            [
                'title_en' => 'Premier League title race heats up as top clubs clash',
                'title_kk' => 'Премьер-лига: чемпиондық жарыс қызу',
                'summary_en' => 'The race for the Premier League title intensifies.',
                'summary_kk' => 'Премьер-лига чемпионы үшін бәсеке қызу.',
                'content_en' => 'The race for the Premier League title intensifies.',
                'content_kk' => 'Премьер-лига чемпионы үшін бәсеке қызу.',
                'source_url' => 'https://example.com/article/premier-league-title-race',
                'source_name' => 'BBC Sport',
                'image_url' => 'https://images.unsplash.com/photo-1574629810360-7cae9884b1fe?w=800&q=80',
                'slug' => 'premier-liga-chempiondyk-zarys',
                'published_at' => now()->subHours(2),
                'status' => 'published',
                'fetched_at' => now(),
            ],
        ];

        foreach ($articles as $article) {
            Article::updateOrCreate(['source_url' => $article['source_url']], $article);
        }
    }

    private function seedUsaSenegalFixture(): void
    {
        Fixture::updateOrCreate(
            ['external_id' => 2394563],
            [
                'competition' => 'Товарищеский (сборные)',
                'home_team' => 'США',
                'away_team' => 'Сенегал',
                'home_team_flag' => 'https://media.api-sports.io/flags/us.svg',
                'away_team_flag' => 'https://media.api-sports.io/flags/sn.svg',
                'home_score' => 3,
                'away_score' => 2,
                'status' => 'FT',
                'minute' => 94,
                'kickoff_at' => '2026-05-31 22:30:00',
                'venue' => 'Банк оф Америка Стэдиум',
                'city' => 'Шарлотт, США',
                'weather' => 'күн ашық',
                'temperature' => '+23°C',
                'broadcast' => 'MEGOGO',
                'referees' => ['Николас Уолш', 'Фрэнк Коннор', 'Дэниел Макфарлейн', 'Дон Робертсон'],
                'events' => [
                    ['minute' => 7, 'type' => 'goal', 'team' => 'home', 'player' => 'Сержиньо Дест', 'assist' => 'Кристиан Пулишич'],
                    ['minute' => 20, 'type' => 'goal', 'team' => 'home', 'player' => 'Рикардо Пепи', 'assist' => 'Кристиан Пулишич'],
                    ['minute' => 44, 'type' => 'goal', 'team' => 'away', 'player' => 'Садио Мане', 'assist' => null],
                    ['minute' => 49, 'type' => 'goal', 'team' => 'home', 'player' => 'Фоларин Балогун', 'assist' => 'Хабиб Диарра'],
                    ['minute' => 52, 'type' => 'goal', 'team' => 'away', 'player' => 'Садио Мане', 'assist' => null],
                    ['minute' => 63, 'type' => 'goal', 'team' => 'home', 'player' => 'Фоларин Балогун', 'assist' => null],
                    ['minute' => 66, 'type' => 'goal', 'team' => 'home', 'player' => 'Малик Тилман', 'assist' => null],
                ],
                'lineups' => [
                    'home' => [
                        'coach' => 'Маурисио Почеттино',
                        'starting' => [
                            ['number' => 1, 'name' => 'Мэтт Тёрнер'],
                            ['number' => 2, 'name' => 'Сержиньо Дест'],
                            ['number' => 10, 'name' => 'Кристиан Пулишич'],
                            ['number' => 9, 'name' => 'Рикардо Пепи'],
                        ],
                        'subs' => [
                            ['number' => 20, 'name' => 'Фоларин Балогун'],
                            ['number' => 17, 'name' => 'Малик Тилман'],
                        ],
                    ],
                    'away' => [
                        'coach' => 'Пап Тьяв',
                        'starting' => [
                            ['number' => 10, 'name' => 'Садио Мане (c)'],
                            ['number' => 21, 'name' => 'Хабиб Диарра'],
                            ['number' => 11, 'name' => 'Николас Джэксон'],
                        ],
                        'subs' => [
                            ['number' => 7, 'name' => 'Ассане Диао'],
                        ],
                    ],
                ],
                'statistics' => [
                    'periods' => [
                        'full' => [
                            ['label' => 'Күтілетін голдар (xG)', 'home' => 2.29, 'away' => 1.74, 'percent' => false],
                            ['label' => 'Соққылар', 'home' => 13, 'away' => 7, 'percent' => false],
                            ['label' => 'Қақпаға соққылар', 'home' => 3, 'away' => 3, 'percent' => false],
                            ['label' => 'Доп алу %', 'home' => 45, 'away' => 55, 'percent' => true],
                            ['label' => 'Бұрыштама добы', 'home' => 3, 'away' => 7, 'percent' => false],
                            ['label' => 'Фолдар', 'home' => 21, 'away' => 9, 'percent' => false],
                            ['label' => 'Пас берулер', 'home' => 335, 'away' => 404, 'percent' => false],
                            ['label' => 'Пас дәлдігі %', 'home' => 82, 'away' => 86, 'percent' => true],
                        ],
                        '1h' => [
                            ['label' => 'Күтілетін голдар (xG)', 'home' => 1.3, 'away' => 0.93, 'percent' => false],
                            ['label' => 'Соққылар', 'home' => 8, 'away' => 4, 'percent' => false],
                            ['label' => 'Доп алу %', 'home' => 56, 'away' => 44, 'percent' => true],
                        ],
                        '2h' => [
                            ['label' => 'Күтілетін голдар (xG)', 'home' => 0.99, 'away' => 0.81, 'percent' => false],
                            ['label' => 'Соққылар', 'home' => 5, 'away' => 3, 'percent' => false],
                            ['label' => 'Доп алу %', 'home' => 32, 'away' => 68, 'percent' => true],
                        ],
                    ],
                ],
                'team_form' => [
                    'home' => [
                        'form' => 'ППВВВ',
                        'summary' => ['matches' => 5, 'goals' => 11, 'avg' => 2.2, 'wins' => 3, 'draws' => 0, 'losses' => 2],
                        'recent' => [
                            ['date' => '31.03', 'text' => 'США 0 — 2 Португалия'],
                            ['date' => '28.03', 'text' => 'США 2 — 5 Бельгия'],
                        ],
                    ],
                    'away' => [
                        'form' => 'ВВВВВ',
                        'summary' => ['matches' => 5, 'goals' => 10, 'avg' => 2.0, 'wins' => 5, 'draws' => 0, 'losses' => 0],
                        'recent' => [
                            ['date' => '31.03', 'text' => 'Сенегал 3 — 1 Гамбия'],
                            ['date' => '28.03', 'text' => 'Сенегал 2 — 0 Перу'],
                        ],
                    ],
                ],
            ]
        );
    }
}
