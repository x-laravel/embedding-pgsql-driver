<?php

namespace XLaravel\Embedding\Driver\Pgsql;

use Illuminate\Support\ServiceProvider;
use XLaravel\Embedding\Contracts\PayloadStoreMetrics;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\SimilarityManager;

class PgsqlEmbeddingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VectorStoreMetrics::class, PgsqlVectorStoreMetrics::class);
        $this->app->bind(PayloadStoreMetrics::class, PgsqlPayloadStoreMetrics::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'embedding-pgsql-migrations');
        }

        $this->app->resolving(SimilarityManager::class, function (SimilarityManager $manager) {
            $manager->extend('pgsql', fn () => new PgsqlDriver());
        });
    }
}
