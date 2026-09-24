<?php

namespace XLaravel\Embedding\Driver\Pgsql\Tests;

use Laravel\Ai\Embeddings;
use Orchestra\Testbench\TestCase as Orchestra;
use XLaravel\Embedding\EmbeddingServiceProvider;
use XLaravel\Embedding\Driver\Pgsql\PgsqlEmbeddingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Embeddings::fake();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Ai\AiServiceProvider::class,
            EmbeddingServiceProvider::class,
            PgsqlEmbeddingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'embedding_test'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'schema' => 'public',
        ]);

        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.providers.openai', [
            'driver' => 'openai',
            'api_key' => 'fake-api-key-for-testing',
        ]);
        $app['config']->set('ai.default_for_embeddings', 'openai');

        $app['config']->set('embedding.database.connection', 'pgsql');
        $app['config']->set('embedding.queue.connection', 'sync');
        $app['config']->set('embedding.similarity.driver', 'pgsql');
    }
}
