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
            $table->string('type')->default('direct')->after('id'); // 'direct', 'group'
            $table->string('group_name')->nullable()->after('type');
            $table->text('group_description')->nullable()->after('group_name');
            $table->boolean('is_encrypted')->default(false)->after('group_description');
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['type', 'group_name', 'group_description', 'is_encrypted']);
        });
    }
};
