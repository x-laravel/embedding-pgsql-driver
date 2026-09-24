<?php

namespace XLaravel\Embedding\Driver\Pgsql;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use XLaravel\Embedding\Contracts\SearchRequest;
use XLaravel\Embedding\Contracts\SimilarityDriver;
use XLaravel\Embedding\Models\Embeddable as EmbeddableRecord;

class PgsqlDriver implements SimilarityDriver
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function search(Model $prototype, SearchRequest $request): Collection
    {
        $embeddingClass = config('embedding.model');
        $vectorLiteral = '['.implode(',', $request->vector).']';

        $query = $embeddingClass::query()
            ->select(['embeddable_id'])
            ->selectRaw('1 - (vector <=> ?) AS similarity_score', [$vectorLiteral])
            ->where('embeddable_type', $prototype->getMorphClass())
            ->where('slot', $request->slot)
            ->orderByRaw('vector <=> ? ASC', [$vectorLiteral])
            ->limit($request->limit);

        if ($request->threshold > 0.0) {
            $query->whereRaw('1 - (vector <=> ?) >= ?', [$vectorLiteral, $request->threshold]);
        }

        if ($request->ids !== null) {
            $query->whereIn('embeddable_id', $request->ids);
        }

        if (! empty($request->filter)) {
            $this->applyPayloadFilter($query, $request->filter);
        }

        $results = $query->get();

        $matchedIds = $results->pluck('embeddable_id')->all();
        $scores = $results->pluck('similarity_score', 'embeddable_id')->all();

        $modelQuery = in_array(SoftDeletes::class, class_uses_recursive($prototype), true)
            ? $prototype::query()->withTrashed()
            : $prototype::query();

        return $modelQuery->findMany($matchedIds)
            ->each(fn ($m) => $m->setAttribute('similarity_score', (float) ($scores[$m->getKey()] ?? 0.0)))
            ->sortByDesc(fn ($m) => $m->getAttribute('similarity_score'))
            ->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function applyPayloadFilter(Builder $query, array $filter): void
    {
        $embeddingsTable = $query->getModel()->getTable();
        $embeddablesTable = (new EmbeddableRecord())->getTable();

        $query->whereExists(function (QueryBuilder $exists) use ($filter, $embeddingsTable, $embeddablesTable) {
            $exists->from($embeddablesTable)
                ->whereColumn("{$embeddablesTable}.embeddable_type", "{$embeddingsTable}.embeddable_type")
                ->whereColumn("{$embeddablesTable}.embeddable_id", "{$embeddingsTable}.embeddable_id");

            foreach ($filter as $key => $value) {
                $this->applyPayloadCondition($exists, $key, $value);
            }
        });
    }

    protected function applyPayloadCondition(QueryBuilder $query, string $key, mixed $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid payload filter key [{$key}].");
        }

        $candidates = is_array($value) ? array_values($value) : [$value];

        if ($candidates === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $bindings = array_map(fn ($candidate) => $this->toJsonLiteral($key, $candidate), $candidates);
        $placeholders = implode(', ', array_fill(0, count($bindings), '?::jsonb'));

        // jsonb equality never matches across JSON types, so 34 and "34" stay distinct.
        $query->whereRaw("payload->'{$key}' IN ({$placeholders})", $bindings);
    }

    protected function toJsonLiteral(string $key, mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException("Payload filter values must be scalar or arrays of scalars [{$key}].");
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
