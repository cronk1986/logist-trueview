<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const INDEXES = [
        'assets' => ['item_name', 'status', 'created_at', 'updated_at'],
        'tickets' => ['created_at', 'updated_at'],
        'contracts' => ['created_at', 'updated_at'],
        'locations' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropIndex([$column]);
                }
            });
        }
    }
};
