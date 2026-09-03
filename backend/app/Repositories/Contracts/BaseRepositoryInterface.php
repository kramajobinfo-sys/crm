<?php
namespace App\Repositories\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
interface BaseRepositoryInterface
{
    public function all(array $columns = ['*']): Collection;
    public function paginate(int $perPage = 25, array $filters = [], array $columns = ['*']): LengthAwarePaginator;
    public function find(int $id, array $columns = ['*']): ?Model;
    public function findOrFail(int $id, array $columns = ['*']): Model;
    public function create(array $data): Model;
    public function update(int $id, array $data): Model;
    public function delete(int $id): bool;
    public function findBy(string $column, mixed $value): ?Model;
}
