<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminSorting
{
    private readonly ?string $column;

    private readonly string $direction;

    /** @param array<string, string|Builder|array> $columns Allowed sort keys and their database expressions. */
    public function __construct(private readonly Request $request, private readonly array $columns)
    {
        $column = $request->query('sort');
        $direction = $request->query('direction');
        $this->column = is_string($column) && array_key_exists($column, $columns) ? $column : null;
        $this->direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }

    public function apply(Builder $query): Builder
    {
        if ($this->column === null) {
            return $query;
        }

        $query->reorder();

        foreach (Arr::wrap($this->columns[$this->column]) as $expression) {
            $query->orderBy($expression, $this->direction);
        }

        return $query->orderBy($query->getModel()->getQualifiedKeyName(), $this->direction);
    }

    public function directionFor(string $column): ?string
    {
        return $this->column === $column ? $this->direction : null;
    }

    public function urlFor(string $column): string
    {
        $query = Arr::except($this->request->query(), ['sort', 'direction', 'page']);
        $nextDirection = match ($this->directionFor($column)) {
            'asc' => 'desc',
            'desc' => null,
            default => 'asc',
        };

        if ($nextDirection !== null) {
            $query += ['sort' => $column, 'direction' => $nextDirection];
        }

        return $this->request->url().($query ? '?'.Arr::query($query) : '');
    }
}
