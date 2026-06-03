<?php

return [
    'translation_driver' => env('TRANSLATION_DRIVER', 'google'),
    'libretranslate_url' => env('LIBRETRANSLATE_URL'),
    'libretranslate_api_key' => env('LIBRETRANSLATE_API_KEY'),
    'fetch_interval' => (int) env('ARTICLE_FETCH_INTERVAL', 60),

    'api_football_key' => env('API_FOOTBALL_KEY'),

    'competitions' => [
        'world-cup-2026' => [
            'key' => 'world-cup-2026',
            'name' => 'Әлем чемпионаты 2026',
            'short' => 'ӘЧ 2026',
            'slug' => 'world-cup-2026',
            'api_league_id' => 1,
            'api_season' => 2026,
            'data_file' => database_path('data/world_cup_2026.php'),
            'external_id_prefix' => 742,
        ],
        'kz-premier-liga' => [
            'key' => 'kz-premier-liga',
            'name' => 'Премьер-лига',
            'short' => 'ҚПЛ',
            'slug' => 'premier-liga',
            'league_key' => 'kz',
            'api_league_id' => 389,
            'api_season' => 2026,
            'data_file' => database_path('data/kz_premier_league.php'),
        ],
    ],

    'leagues' => [
        [
            'key' => 'kz',
            'name' => 'Қазақстан Премьер-лигасы',
            'short' => 'KZ',
            'country' => 'Қазақстан',
            'flag' => 'kz',
            'api_id' => 389,
            'api_season' => 2026,
            'flashscore_url' => 'https://www.flashscorekz.com/football/kazakhstan/premier-league/standings/',
        ],
        [
            'key' => 'pl',
            'name' => 'Англия Премьер-лигасы',
            'short' => 'АПЛ',
            'country' => 'Англия',
            'flag' => 'gb-eng',
            'api_id' => 39,
            'flashscore_url' => 'https://www.flashscorekz.com/football/england/premier-league/standings/',
        ],
        [
            'key' => 'bl1',
            'name' => 'Германия Бундеслигасы',
            'short' => 'BL',
            'country' => 'Германия',
            'flag' => 'de',
            'api_id' => 78,
            'flashscore_url' => 'https://www.flashscorekz.com/football/germany/bundesliga/standings/',
        ],
        [
            'key' => 'fl1',
            'name' => 'Франция Лига 1',
            'short' => 'L1',
            'country' => 'Франция',
            'flag' => 'fr',
            'api_id' => 61,
            'flashscore_url' => 'https://www.flashscorekz.com/football/france/ligue-1/standings/',
        ],
        [
            'key' => 'sa',
            'name' => 'Италия Серия A',
            'short' => 'SA',
            'country' => 'Италия',
            'flag' => 'it',
            'api_id' => 135,
            'flashscore_url' => 'https://www.flashscorekz.com/football/italy/serie-a/standings/',
        ],
        [
            'key' => 'laliga',
            'name' => 'Испания Ла Лига',
            'short' => 'LL',
            'country' => 'Испания',
            'flag' => 'es',
            'api_id' => 140,
            'flashscore_url' => 'https://www.flashscorekz.com/football/spain/laliga/standings/',
        ],
    ],

    /*
    | Каждый час articles:fetch-hourly берёт по 1 новости с каждого сайта.
    | lang: en | ru — язык исходного текста для перевода на казахский.
    */
    'sources' => [
        // ——— Ағылшынша ———
        [
            'name' => 'BBC Sport',
            'rss_url' => 'https://feeds.bbci.co.uk/sport/football/rss.xml',
            'lang' => 'en',
        ],
        [
            'name' => 'The Guardian',
            'rss_url' => 'https://www.theguardian.com/football/rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Sky Sports',
            'rss_url' => 'https://www.skysports.com/rss/12040',
            'lang' => 'en',
        ],
        [
            'name' => 'ESPN FC',
            'rss_url' => 'https://www.espn.com/espn/rss/soccer/news',
            'lang' => 'en',
        ],
        [
            'name' => 'Reuters',
            'rss_url' => 'https://www.reutersagency.com/feed/?best-topics=sports&post_type=best',
            'lang' => 'en',
        ],
        [
            'name' => 'CBS Sports',
            'rss_url' => 'https://www.cbssports.com/rss/headlines/soccer',
            'lang' => 'en',
        ],
        [
            'name' => 'Premier League',
            'rss_url' => 'https://www.premierleague.com/news/rss',
            'lang' => 'en',
        ],
        [
            'name' => 'UEFA',
            'rss_url' => 'https://www.uefa.com/rssfeed/news/rss.xml',
            'lang' => 'en',
        ],
        [
            'name' => 'GOAL',
            'rss_url' => 'https://www.goal.com/feeds/en/news',
            'lang' => 'en',
        ],
        [
            'name' => 'TalkSport',
            'rss_url' => 'https://talksport.com/feed/',
            'lang' => 'en',
        ],
        [
            'name' => 'The Independent',
            'rss_url' => 'https://www.independent.co.uk/sport/football/rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Daily Mirror',
            'rss_url' => 'https://www.mirror.co.uk/sport/football/rss.xml',
            'lang' => 'en',
        ],
        [
            'name' => 'FourFourTwo',
            'rss_url' => 'https://www.fourfourtwo.com/feeds/all',
            'lang' => 'en',
        ],
        [
            'name' => 'Evening Standard',
            'rss_url' => 'https://www.standard.co.uk/sport/football/rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Daily Mail',
            'rss_url' => 'https://www.dailymail.co.uk/sport/football/index.rss',
            'lang' => 'en',
        ],
        [
            'name' => 'The Sun',
            'rss_url' => 'https://www.thesun.co.uk/sport/football/feed/',
            'lang' => 'en',
        ],
        [
            'name' => 'Yahoo Soccer',
            'rss_url' => 'https://sports.yahoo.com/soccer/rss.xml',
            'lang' => 'en',
        ],
        [
            'name' => 'Football Italia',
            'rss_url' => 'https://www.football-italia.net/feed',
            'lang' => 'en',
        ],
        [
            'name' => 'CaughtOffside',
            'rss_url' => 'https://www.caughtoffside.com/feed/',
            'lang' => 'en',
        ],
        [
            'name' => 'Football London',
            'rss_url' => 'https://www.football.london/?service=rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Planet Football',
            'rss_url' => 'https://www.planetfootball.com/feed',
            'lang' => 'en',
        ],
        [
            'name' => 'Football Critic',
            'rss_url' => 'https://www.footballcritic.com/rss/news',
            'lang' => 'en',
        ],
        [
            'name' => 'Soccerway',
            'rss_url' => 'https://int.soccerway.com/rss/news.xml',
            'lang' => 'en',
        ],
        [
            'name' => 'World Soccer Talk',
            'rss_url' => 'https://worldsoccertalk.com/feed/',
            'lang' => 'en',
        ],
        [
            'name' => 'Football Insider',
            'rss_url' => 'https://www.footballinsider247.com/feed/',
            'lang' => 'en',
        ],
        [
            'name' => 'The Telegraph',
            'rss_url' => 'https://www.telegraph.co.uk/football/rss.xml',
            'lang' => 'en',
        ],
        [
            'name' => 'Metro Football',
            'rss_url' => 'https://metro.co.uk/sport/football/feed/',
            'lang' => 'en',
        ],
        [
            'name' => 'Bleacher Report',
            'rss_url' => 'https://bleacherreport.com/articles/feed?tag_id=20',
            'lang' => 'en',
        ],
        [
            'name' => 'SB Nation',
            'rss_url' => 'https://www.sbnation.com/rss/current',
            'lang' => 'en',
        ],
        [
            'name' => 'Football365',
            'rss_url' => 'https://www.football365.com/feed',
            'lang' => 'en',
        ],
        [
            'name' => 'Sporting News',
            'rss_url' => 'https://www.sportingnews.com/us/soccer/rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Liverpool Echo',
            'rss_url' => 'https://www.liverpoolecho.co.uk/sport/football/?service=rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Manchester Evening News',
            'rss_url' => 'https://www.manchestereveningnews.co.uk/sport/football/?service=rss',
            'lang' => 'en',
        ],
        [
            'name' => 'Transfermarkt',
            'rss_url' => 'https://www.transfermarkt.com/rss/news',
            'lang' => 'en',
        ],
        [
            'name' => 'Bundesliga',
            'rss_url' => 'https://www.bundesliga.com/en/bundesliga/news/rss',
            'lang' => 'en',
        ],
        [
            'name' => 'FIFA',
            'rss_url' => 'https://www.fifa.com/rss-feeds/news',
            'lang' => 'en',
        ],

        // ——— Орысша ———
        [
            'name' => 'Sport Express',
            'rss_url' => 'https://www.sport-express.ru/services/materials/news/football/se/',
            'lang' => 'ru',
        ],
        [
            'name' => 'TASS Спорт',
            'rss_url' => 'https://tass.ru/rss/v2.xml?sectionId=136',
            'lang' => 'ru',
        ],
        [
            'name' => 'Euro-football.ru',
            'rss_url' => 'https://www.euro-football.ru/rss.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Lenta.ru Спорт',
            'rss_url' => 'https://lenta.ru/rss/news/sport',
            'lang' => 'ru',
        ],
        [
            'name' => 'Championat',
            'rss_url' => 'https://www.championat.com/rss/news/football.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Sports.ru',
            'rss_url' => 'https://www.sports.ru/rss/football.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Gazeta.ru Футбол',
            'rss_url' => 'https://www.gazeta.ru/export/rss/sport/football.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'РИА Новости Спорт',
            'rss_url' => 'https://ria.ru/export/rss2/sport/index.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Матч ТВ',
            'rss_url' => 'https://matchtv.ru/rss/news/football',
            'lang' => 'ru',
        ],
        [
            'name' => 'Sovsport',
            'rss_url' => 'https://www.sovsport.ru/feeds/news.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Коммерсантъ Спорт',
            'rss_url' => 'https://www.kommersant.ru/RSS/sport.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'МК Спорт',
            'rss_url' => 'https://www.mk.ru/rss/sport/index.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'RT Спорт',
            'rss_url' => 'https://www.rt.com/sport/rss/',
            'lang' => 'ru',
        ],
        [
            'name' => 'РБК Спорт',
            'rss_url' => 'https://rsport.ria.ru/export/rss2/index.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Известия Спорт',
            'rss_url' => 'https://iz.ru/xml/rss/sport.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Soccer.ru',
            'rss_url' => 'https://www.soccer.ru/rss/news.xml',
            'lang' => 'ru',
        ],
        [
            'name' => 'Soccer365',
            'type' => 'soccer365',
            'news_url' => 'https://soccer365.ru/news/',
            'lang' => 'ru',
        ],
        [
            'name' => 'Soccer365 Press',
            'type' => 'soccer365_press',
            'news_url' => 'https://soccer365.ru/press/',
            'lang' => 'ru',
        ],
        [
            'name' => 'Sport24',
            'rss_url' => 'https://sport24.ru/rss.xml',
            'lang' => 'ru',
        ],
    ],
];
