<?php

namespace XLaravel\Embedding\Driver\Pgsql;

use Illuminate\Support\Facades\DB;
use Throwable;
use XLaravel\Embedding\Contracts\PayloadStoreMetrics;
use XLaravel\Embedding\Models\Embeddable;

class PgsqlPayloadStoreMetrics implements PayloadStoreMetrics
{
    public function snapshot(): array
    {
        $rows = Embeddable::query()->count();
        $bytes = null;
        $dataBytes = null;
        $indexBytes = null;

        $table = config('embedding.database.embeddables_table', 'embeddables');

        try {
            // pg_table_size includes the TOAST relation that holds large JSONB payloads.
            $row = DB::connection(config('embedding.database.connection'))
                ->selectOne(
                    'SELECT
                        pg_total_relation_size(?::regclass) AS total_bytes,
                        pg_table_size(?::regclass) AS data_bytes,
                        pg_indexes_size(?::regclass) AS index_bytes',
                    [$table, $table, $table]
                );

            if ($row !== null) {
                $bytes = isset($row->total_bytes) ? (int) $row->total_bytes : null;
                $dataBytes = isset($row->data_bytes) ? (int) $row->data_bytes : null;
                $indexBytes = isset($row->index_bytes) ? (int) $row->index_bytes : null;
            }
        } catch (Throwable) {
            // Table missing from search_path or insufficient privileges.
        }

        return [
            'rows' => $rows,
            'bytes' => $bytes,
            'data_bytes' => $dataBytes,
            'index_bytes' => $indexBytes,
        ];
    }
}
