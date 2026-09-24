<?php

namespace XLaravel\Embedding\Driver\Pgsql\Tests\Feature;

use XLaravel\Embedding\Contracts\PayloadStoreMetrics;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Driver\Pgsql\PgsqlDriver;
use XLaravel\Embedding\Driver\Pgsql\PgsqlPayloadStoreMetrics;
use XLaravel\Embedding\Driver\Pgsql\PgsqlVectorStoreMetrics;
use XLaravel\Embedding\Driver\Pgsql\Tests\Fixtures\Models\Post;
use XLaravel\Embedding\Driver\Pgsql\Tests\Fixtures\Models\VenueWithPayload;
use XLaravel\Embedding\Driver\Pgsql\Tests\TestCase;
use XLaravel\Embedding\SimilarityManager;
use XLaravel\Embedding\Storage\JsonVectorStore;

class PgsqlEmbeddingServiceProviderTest extends TestCase
{
    public function test_it_registers_the_pgsql_driver(): void
    {
        $manager = app(SimilarityManager::class);

        $this->assertInstanceOf(PgsqlDriver::class, $manager->driver('pgsql'));
    }

    public function test_it_can_be_set_as_the_default_driver(): void
    {
        $manager = app(SimilarityManager::class);
        $manager->forgetDrivers();

        config(['embedding.similarity.driver' => 'pgsql']);

        $this->assertInstanceOf(PgsqlDriver::class, $manager->driver());
    }

    public function test_it_uses_core_json_vector_store(): void
    {
        $this->assertInstanceOf(JsonVectorStore::class, app(VectorStore::class));
    }

    public function test_it_stores_and_reads_embedding_correctly(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $this->assertNotNull($post->fresh()->embedding);
        $this->assertIsArray($post->fresh()->embedding->vector);
    }

    public function test_it_binds_pgsql_vector_store_metrics(): void
    {
        $this->assertInstanceOf(PgsqlVectorStoreMetrics::class, app(VectorStoreMetrics::class));
    }

    public function test_metrics_snapshot_reports_rows_and_byte_sizes(): void
    {
        Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $snapshot = app(VectorStoreMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['rows']);
        $this->assertIsInt($snapshot['bytes']);
        $this->assertIsInt($snapshot['data_bytes']);
        $this->assertIsInt($snapshot['index_bytes']);
        $this->assertGreaterThan(0, $snapshot['bytes']);
    }

    public function test_it_binds_pgsql_payload_store_metrics(): void
    {
        $this->assertInstanceOf(PgsqlPayloadStoreMetrics::class, app(PayloadStoreMetrics::class));
    }

    public function test_payload_metrics_snapshot_reports_rows_and_byte_sizes(): void
    {
        VenueWithPayload::create([
            'name' => 'Kebapçı',
            'province_id' => 34,
            'category_id' => 3,
            'active' => true,
            'code' => 'V-1',
        ]);

        $snapshot = app(PayloadStoreMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['rows']);
        $this->assertIsInt($snapshot['bytes']);
        $this->assertIsInt($snapshot['data_bytes']);
        $this->assertIsInt($snapshot['index_bytes']);
        $this->assertGreaterThan(0, $snapshot['bytes']);
        $this->assertGreaterThan(0, $snapshot['data_bytes']);
        $this->assertGreaterThan(0, $snapshot['index_bytes']);
    }

    public function test_payload_metrics_rows_are_zero_on_empty_table(): void
    {
        $snapshot = app(PayloadStoreMetrics::class)->snapshot();

        $this->assertSame(0, $snapshot['rows']);
    }
}
