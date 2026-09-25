<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('custom_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('view_type', ['my_assets', 'my_tickets', 'my_contracts', 'my_locations']);
            $table->json('columns');
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'title']);
            $table->index(['user_id', 'view_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_views');
    }
};
