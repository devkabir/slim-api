<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Cache;
use App\Models\Todo;
use App\Repositories\TodoRepository;

class TodoService
{
    private const CACHE_PREFIX_ITEM = 'todo_item_';
    private const CACHE_PREFIX_LIST = 'todo_list_';

    public function __construct(
        private TodoRepository $repository = new TodoRepository()
    ) {}

    /**
     * @return Todo[]
     */
    public function getAllTodos(?bool $completed = null): array
    {
        $cacheKey = self::CACHE_PREFIX_LIST . ($completed === null ? 'all' : ($completed ? 'completed' : 'pending'));
        
        $cached = Cache::get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return array_map(fn($item) => $item instanceof Todo ? $item : Todo::fromArray($item), $cached);
        }

        $todos = $this->repository->findAll($completed);

        // Store in cache as raw arrays for reliable serialization
        $serialized = array_map(fn(Todo $t) => $t->jsonSerialize(), $todos);
        Cache::set($cacheKey, $serialized);

        return $todos;
    }

    public function getTodoById(int $id): ?Todo
    {
        $cacheKey = self::CACHE_PREFIX_ITEM . $id;

        $cached = Cache::get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return Todo::fromArray($cached);
        }

        $todo = $this->repository->findById($id);
        if ($todo !== null) {
            Cache::set($cacheKey, $todo->jsonSerialize());
        }

        return $todo;
    }

    public function createTodo(string $title, ?string $description = null, bool $completed = false): Todo
    {
        $todo = $this->repository->create($title, $description, $completed);
        
        // Invalidate list caches
        $this->invalidateListCaches();

        // Warm up single item cache
        if ($todo->id !== null) {
            Cache::set(self::CACHE_PREFIX_ITEM . $todo->id, $todo->jsonSerialize());
        }

        return $todo;
    }

    public function updateTodo(int $id, array $data): ?Todo
    {
        $existing = $this->repository->findById($id);
        if (!$existing) {
            return null;
        }

        $updated = $this->repository->update($id, $data);
        if ($updated) {
            // Invalidate cache
            Cache::delete(self::CACHE_PREFIX_ITEM . $id);
            $this->invalidateListCaches();
            Cache::set(self::CACHE_PREFIX_ITEM . $id, $updated->jsonSerialize());
        }

        return $updated;
    }

    public function deleteTodo(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            Cache::delete(self::CACHE_PREFIX_ITEM . $id);
            $this->invalidateListCaches();
        }

        return $deleted;
    }

    private function invalidateListCaches(): void
    {
        Cache::delete(self::CACHE_PREFIX_LIST . 'all');
        Cache::delete(self::CACHE_PREFIX_LIST . 'completed');
        Cache::delete(self::CACHE_PREFIX_LIST . 'pending');
        Cache::deleteByPrefix(self::CACHE_PREFIX_LIST);
    }
}
