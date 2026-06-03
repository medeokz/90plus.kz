# Football.kz

Футбол мақалалар сайты — ағылшынша сайттардан (BBC Sport, The Guardian, Sky Sports, ESPN FC) мақалаларды қазақ тіліне аударады.

Дизайн [Finance.kz](https://finance.kz/) стилінде: көк акцент, карточкalar, заманауи интерфейс.

## Орнату

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Сайт: http://localhost:8000

## Мақалаларды жүктеу

**Сағат сайын (автомат):** әр сайттан 1 мақала, толық мәтін + қазақша аударма (барлығы 10 сайт):

```bash
php artisan schedule:work
```

Немесе бір рет қолмен:

```bash
php artisan articles:fetch-hourly
```

**Барлық сайттардан бір уақытта (көбірек мақала):**

```bash
php artisan articles:fetch --limit=3 --full
```

## Конфигурация

`.env` файлында:

| Параметр | Сипаттама |
|----------|-----------|
| `TRANSLATION_DRIVER` | `google` (әдепкі) немесе `libre` |
| `LIBRETRANSLATE_URL` | LibreTranslate API URL |
| `ARTICLE_FETCH_INTERVAL` | Жаңарту интервалы (минут) |

## Технологиялар

- Laravel 11
- SQLite (немесе MySQL)
- Google Translate PHP / LibreTranslate
- RSS: BBC Sport, The Guardian, Sky Sports, ESPN FC
