<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SyncDatabases extends Command
{
    protected $signature = 'db:sync
        {--from=mysql : Source Laravel database connection}
        {--to=supabase : Destination Laravel database connection}
        {--table=* : Only synchronize these tables}
        {--replace : Delete destination rows before copying}
        {--chunk=250 : Rows copied per batch}
        {--force : Allow execution outside the local environment}';

    protected $description = 'Synchronize application data between two configured database connections';

    /**
     * Parent tables precede children so foreign keys remain valid during copy.
     *
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'customer_profiles',
        'system_settings',
        'audit_logs',
        'room_types',
        'rooms',
        'amenities',
        'amenity_room_type',
        'room_images',
        'pages',
        'room_type_inventories',
        'rate_plans',
        'seasonal_rates',
        'promotions',
        'promotion_room_type',
        'bookings',
        'booking_guests',
        'booking_items',
        'booking_item_nights',
        'payment_attempts',
        'payment_events',
        'room_assignments',
        'cancellation_requests',
        'refunds',
        'faqs',
        'chatbot_sessions',
        'chatbot_messages',
    ];

    public function handle(): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->components->error('Database sync is restricted to local by default. Use --force intentionally.');

            return self::FAILURE;
        }

        $sourceName = (string) $this->option('from');
        $destinationName = (string) $this->option('to');

        if ($sourceName === $destinationName) {
            $this->components->error('Source and destination connections must differ.');

            return self::FAILURE;
        }

        $requested = array_values(array_filter((array) $this->option('table')));
        $tables = $requested ?: self::TABLES;
        $unknown = array_diff($tables, self::TABLES);

        if ($unknown !== []) {
            $this->components->error('Unsupported tables: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $chunkSize = max(1, min(2000, (int) $this->option('chunk')));
        $source = DB::connection($sourceName);
        $destination = DB::connection($destinationName);

        try {
            $source->getPdo();
            $destination->getPdo();
        } catch (Throwable $exception) {
            $this->components->error('Database connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            Schema::connection($destinationName)->disableForeignKeyConstraints();

            try {
                foreach (array_reverse($tables) as $table) {
                    if (Schema::connection($destinationName)->hasTable($table)) {
                        $destination->table($table)->delete();
                    }
                }
            } finally {
                Schema::connection($destinationName)->enableForeignKeyConstraints();
            }
        }

        foreach ($tables as $table) {
            if (! Schema::connection($sourceName)->hasTable($table)) {
                $this->components->warn("Skipping {$table}: source table does not exist.");

                continue;
            }

            if (! Schema::connection($destinationName)->hasTable($table)) {
                throw new RuntimeException(
                    "Destination table {$table} does not exist. Run: php artisan migrate --database={$destinationName}"
                );
            }

            $this->syncTable($source, $destination, $table, $chunkSize);
        }

        $this->synchronizePostgresSequences($destination, $tables);

        $this->components->info("Synchronization complete: {$sourceName} → {$destinationName}.");

        return self::SUCCESS;
    }

    private function syncTable(
        Connection $source,
        Connection $destination,
        string $table,
        int $chunkSize,
    ): void {
        $columns = Schema::connection($source->getName())->getColumnListing($table);
        $key = in_array('id', $columns, true) ? 'id' : $columns;

        $destination->transaction(function () use ($source, $destination, $table, $columns, $key, $chunkSize): void {
            $copied = 0;
            $orderColumn = in_array('id', $columns, true) ? 'id' : $columns[0];

            $source->table($table)
                ->orderBy($orderColumn)
                ->chunk($chunkSize, function ($rows) use ($destination, $table, $columns, $key, &$copied): void {
                    $payload = $rows->map(fn ($row) => (array) $row)->all();

                    if ($payload !== []) {
                        $destination->table($table)->upsert($payload, $key, $columns);
                        $copied += count($payload);
                    }
                });

            $this->line("  {$table}: {$copied} rows");
        });
    }

    /**
     * Upserting explicit MySQL IDs does not advance PostgreSQL sequences.
     * Without this, the next production insert can reuse an existing ID.
     *
     * @param  list<string>  $tables
     */
    private function synchronizePostgresSequences(Connection $destination, array $tables): void
    {
        if ($destination->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            $columns = Schema::connection($destination->getName())->getColumnListing($table);

            if (! in_array('id', $columns, true)) {
                continue;
            }

            $sequence = $destination->selectOne(
                'select pg_get_serial_sequence(?, ?) as name',
                [$table, 'id'],
            )?->name;

            if (! $sequence) {
                continue;
            }

            $maximumId = (int) $destination->table($table)->max('id');

            if ($maximumId > 0) {
                $destination->statement(
                    'select setval(?::regclass, ?, true)',
                    [$sequence, $maximumId],
                );
            }
        }
    }
}
