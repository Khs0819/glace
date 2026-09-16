<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

/**
 * Compare what the code writes with what the database actually has.
 *
 * `migrate:status` only says which migrations were recorded, not whether the
 * columns they create exist — a migration edited after it had already run is
 * recorded as "Ran" and still leaves its new column missing. This checks every
 * model's fillable attributes against the real table, which is what fails at
 * runtime with "Unknown column".
 */
class SchemaCheck extends Command
{
    protected $signature = 'schema:check';

    protected $description = 'Report columns the models write that are missing from the database';

    public function handle(): int
    {
        $missing = [];
        $checked = 0;

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            try {
                /** @var Model $model */
                $model = new $class();
                $table = $model->getTable();
            } catch (Throwable) {
                continue;
            }

            if (! Schema::hasTable($table)) {
                $missing[] = [$class, $table, '(table missing)'];

                continue;
            }

            $columns = array_map('strtolower', Schema::getColumnListing($table));

            foreach ($model->getFillable() as $attribute) {
                $checked++;

                if (! in_array(strtolower($attribute), $columns, true)) {
                    $missing[] = [class_basename($class), $table, $attribute];
                }
            }
        }

        $this->newLine();
        $this->line("  Checked {$checked} column(s) across the models.");

        if ($missing === []) {
            $this->components->info('Every column the models write exists in the database.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['model', 'table', 'missing column'], $missing);
        $this->components->error(count($missing) . ' missing — saving these models fails until a migration adds them.');
        $this->line('  Run: php artisan migrate --force');

        return self::FAILURE;
    }
}
