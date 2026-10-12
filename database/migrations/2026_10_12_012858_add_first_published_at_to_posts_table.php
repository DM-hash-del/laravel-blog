<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * first_published_at records the first publish, so a post's slug stays
     * frozen even after it is unpublished. Posts already published are
     * backfilled from their published_at.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('first_published_at')->nullable()->after('published_at');
        });

        DB::table('posts')
            ->where('status', 'published')
            ->update(['first_published_at' => DB::raw('published_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('first_published_at');
        });
    }
};
