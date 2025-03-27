<?php

namespace App\Repositories;

use App\Repositories\Interfaces\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class BaseRepository
 * 
 * @package App\Repositories
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @var Model
     */
    protected Model $model;

    /**
     * BaseRepository constructor.
     * 
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @inheritDoc
     */
    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->get($columns);
    }

    /**
     * @inheritDoc
     */
    public function paginate(
        int $perPage = 15,
        array $columns = ['*'],
        array $relations = [],
        string $pageName = 'page',
        int $page = null
    ): LengthAwarePaginator {
        return $this->model->with($relations)->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * @inheritDoc
     */
    public function findById(
        int $id,
        array $columns = ['*'],
        array $relations = [],
        array $appends = []
    ): ?Model {
        $query = $this->model->with($relations);
        $model = $query->find($id, $columns);

        if (!$model) {
            return null;
        }

        if ($appends) {
            $model->append($appends);
        }

        return $model;
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * @inheritDoc
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->findById($id);
        $model->update($data);
        return $model;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $id): bool
    {
        return $this->findById($id)->delete();
    }

    /**
     * @inheritDoc
     */
    public function getModel(): Model
    {
        return $this->model;
    }
} 