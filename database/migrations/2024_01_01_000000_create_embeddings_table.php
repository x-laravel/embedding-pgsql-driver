<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('embedding.database.connection');
    }

    public function up(): void
    {
        $dimensions = config('embedding.dimensions', 1536);

        DB::connection($this->getConnection())->statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create(config('embedding.database.embeddings_table', 'embeddings'), function (Blueprint $table) use ($dimensions) {
            $table->id();
            $table->morphs('embeddable');
            $table->string('slot', 64)->default('default');
            $table->vector('vector', dimensions: $dimensions);
            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('embedding.database.embeddings_table', 'embeddings'));
    }
};
