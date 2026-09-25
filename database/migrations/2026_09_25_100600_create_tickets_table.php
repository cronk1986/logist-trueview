<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('repair_id')->unique();
            $table->string('incident_number')->unique();
            $table->string('subject');
            $table->foreignId('status_id')->constrained('ticket_statuses')->restrictOnDelete();
            $table->text('issue_description');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
