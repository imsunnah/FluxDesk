<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('ticket_number')->nullable()->after('type');
            $table->string('ticket_subject')->nullable()->after('ticket_number');
            $table->text('ticket_description')->nullable()->after('ticket_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['ticket_number', 'ticket_subject', 'ticket_description']);
        });
    }
};
