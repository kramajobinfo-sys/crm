<?php
namespace App\Repositories;
use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model) {}
    public function all(array $columns = ['*']): Collection { return $this->model->newQuery()->get($columns); }
    public function paginate(int $perPage = 25, array $filters = [], array $columns = ['*']): LengthAwarePaginator
    {
        $query = $this->model->newQuery();
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage, $columns);
    }
    public function find(int $id, array $columns = ['*']): ?Model { return $this->model->newQuery()->find($id, $columns); }
    public function findOrFail(int $id, array $columns = ['*']): Model { return $this->model->newQuery()->findOrFail($id, $columns); }
    public function findBy(string $column, mixed $value): ?Model { return $this->model->newQuery()->where($column, $value)->first(); }
    public function create(array $data): Model { return $this->model->newQuery()->create($data); }
    public function update(int $id, array $data): Model { $r = $this->findOrFail($id); $r->update($data); return $r->refresh(); }
    public function delete(int $id): bool { return (bool) $this->findOrFail($id)->delete(); }

    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $column => $value) {
            if ($value === null || $value === '') continue;
            if ($column === 'q') { $this->applySearch($query, $value); continue; }
            if ($column === 'sort') { $this->applySort($query, $value); continue; }
            $query->where($column, $value);
        }
    }
    protected function applySearch(Builder $query, string $term): void {}
    protected function applySort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy(ltrim($sort, '-+'), $direction);
    }
}
