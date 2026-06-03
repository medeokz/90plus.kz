<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use PDO;

class ImportSqliteArticlesCommand extends Command
{
    protected $signature = 'articles:import-sqlite {path=database/database.sqlite}';

    protected $description = 'Import articles from SQLite file into current database (MySQL 90plus)';

    public function handle(): int
    {
        $path = base_path($this->argument('path'));

        if (! file_exists($path)) {
            $this->error("SQLite file not found: {$path}");

            return self::FAILURE;
        }

        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $rows = $pdo->query('SELECT * FROM articles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $this->warn('No articles in SQLite file.');

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            unset($row['id']);

            $exists = Article::where('source_url', $row['source_url'])
                ->orWhere('slug', $row['slug'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            try {
                Article::create($row);
                $imported++;
            } catch (\Throwable $e) {
                $this->warn('Skip row: '.$row['slug'].' — '.$e->getMessage());
            }
        }

        $this->info("Imported {$imported} articles, skipped {$skipped} duplicates.");
        $this->info('Total in database: '.Article::count());

        return self::SUCCESS;
    }
}
