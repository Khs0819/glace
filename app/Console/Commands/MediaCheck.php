<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Find rows that point at a file the public disk does not have.
 *
 * This is a different failure from "no image was ever uploaded", and the
 * dashboard's own backlog filter cannot see it: that one asks the database
 * `image IS NULL`, so a row holding a perfectly well-formed path scores as
 * complete while the storefront serves a 404 for it. The gap opens whenever
 * the files and the database part company — a container rebuilt without a
 * volume on `storage/app/public`, a restore of one but not the other.
 *
 * Read-only. It names what is broken and never deletes a row: a missing file
 * is usually a mount that needs fixing, and blanking the column would destroy
 * the only record of what the picture was.
 */
class MediaCheck extends Command
{
    protected $signature = 'media:check {--list : Print every missing path, not just a count}';

    protected $description = 'Report database image paths whose file is missing from the public disk';

    /**
     * Every column that holds a path on the public disk.
     *
     * Listed rather than discovered: a name-based guess would sweep in columns
     * that hold external URLs or plain text, and report them all as broken.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMNS = [
        'products'            => ['image'],
        'product_items'       => ['image'],
        'product_sizes'       => ['image'],
        'events'              => ['list_image'],
        'event_images'        => ['image_url'],
        'hero_slides'         => ['man_img', 'piece_img', 'zigzags_img'],
        'home_abouts'         => ['image'],
        'home_why_glaces'     => ['video_thumbnail'],
        'payment_accounts'    => ['qr_image'],
        'topup_requests'      => ['receipt_image'],
        'wallet_transactions' => ['receipt_image'],
        'orders'              => ['receipt_image'],
    ];

    /**
     * Not covered: `home_why_glaces.features` is JSON holding `{label, image}`
     * pairs. Reaching into it needs a different shape of query, and it is one
     * row on the home page — checked by looking at the page.
     */

    public function handle(): int
    {
        $disk    = Storage::disk('public');
        $missing = [];
        $checked = 0;

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $rows = DB::table($table)
                    ->select('id', $column)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->get();

                foreach ($rows as $row) {
                    $path = (string) $row->{$column};

                    // Externally hosted images are somebody else's uptime.
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        continue;
                    }

                    $checked++;

                    if (! $disk->exists($path)) {
                        $missing[] = ['table' => $table, 'column' => $column, 'id' => $row->id, 'path' => $path];
                    }
                }
            }
        }

        $this->newLine();
        $this->line('  Disk root : ' . $disk->path(''));
        $this->line('  Checked   : ' . $checked . ' path(s)');
        $this->line('  Missing   : ' . count($missing));
        $this->newLine();

        if ($missing === []) {
            $this->components->info('Every stored path resolves to a real file.');

            return self::SUCCESS;
        }

        $byTable = [];

        foreach ($missing as $row) {
            $key             = $row['table'] . '.' . $row['column'];
            $byTable[$key]   = ($byTable[$key] ?? 0) + 1;
        }

        $this->table(
            ['table.column', 'missing'],
            array_map(static fn ($k, $v) => [$k, $v], array_keys($byTable), $byTable),
        );

        if ($this->option('list')) {
            $this->newLine();

            foreach ($missing as $row) {
                $this->line("  {$row['table']}.{$row['column']} #{$row['id']}  {$row['path']}");
            }
        } else {
            $this->comment('  Re-run with --list to see every path.');
        }

        $this->newLine();
        // The order matters: re-uploading before the volume exists means doing
        // it twice.
        $this->warn('  Mount a volume on storage/app/public BEFORE re-uploading, or the next');
        $this->warn('  rebuild loses these files again exactly as it did this time.');

        return self::FAILURE;
    }
}
