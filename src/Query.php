<?php

namespace TheHome\StatamicElasticsearch;

use Illuminate\Support\Collection;
use Statamic\Contracts\Search\Result;
use Statamic\Search\PlainResult;
use Statamic\Search\QueryBuilder;
use Statamic\Facades\Data;
use Statamic\Search\Searchables\Providers;
use Statamic\Support\Str;

class Query extends QueryBuilder
{
    /**
     * total
     *
     * @var int
     */
    protected $total;

    /**
     * site
     *
     * @var string $site
     */
    protected $site;

    /**
     * site
     *
     * @var string $collection
     */
    protected $collection;

    /**
     * Method getSearchResults
     *
     * @param  string $query
     * @return Collection
     */
    public function getSearchResults(string $query): Collection
    {
        $result = $this->index->searchUsingApi(
            $query,
            $this->limit,
            $this->offset,
            $this->site,
            $this->collection,
        );

        $this->total = $result['total'];

        return $result['hits'];
    }

    /**
     * Method getItems
     *
     * @return mixed
     */
    public function getItems()
    {
        return $this->getBaseItems();
    }

    /**
     * Method getBaseItems
     *
     * @return mixed
     */
    public function getBaseItems()
    {
        $results = $this->getSearchResults($this->query);

        if (! $this->withData) {
            return $this->collect($results)
                ->map(fn ($result) => new PlainResult($result))
                ->each(fn (Result $result, $i) => $result->setIndex($this->index)->setScore($results[$i]['search_score'] ?? null));
        }

        return $this->collect($results)->groupBy(function ($result) {
            return Str::before($result['id'], '::');
        })->flatMap(function ($results, $prefix) {
            $results = $results->keyBy('id');
            $ids = $results->map(fn ($result) => Str::after($result['id'], $prefix.'::'))->values()->all();

            return app(Providers::class)
                ->getByPrefix($prefix)
                ->find($ids)
                ->map->toSearchResult()
                ->each(function (Result $result) use ($results) {
                    return $result
                        ->setIndex($this->index)
                        ->setRawResult($raw = $results[$result->getReference()])
                        ->setScore($raw['search_score'] ?? null);
                });
        })
            ->sortByDesc->getScore()
            ->values();
    }

    /**
     * Method getTotal
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Method site
     *
     * @param  string $site
     * @return \TheHome\StatamicElasticsearch\Query
     */
    public function site($site): self
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Method collection
     *
     * @param  string $collection
     * @return \TheHome\StatamicElasticsearch\Query
     */
    public function collection($collection): self
    {
        $this->collection = $collection;

        return $this;
    }
}
