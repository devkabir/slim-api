<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateTodoDTO;
use App\DTOs\UpdateTodoDTO;
use App\Exceptions\TodoNotFoundException;
use App\Models\Todo;
use App\Repositories\TodoRepositoryInterface;
use Psr\Log\LoggerInterface;

final readonly class TodoService
{
    private const CACHE_PREFIX_ITEM    = 'todo_item_';
    private const CACHE_PREFIX_LIST    = 'todo_list_';
    private const CACHE_NAMESPACE_LIST = 'todo_list_ns';

    public function __construct(
        private TodoRepositoryInterface $repository,
        private CacheService $cache,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return array{todos: Todo[], pagination: array{total: int, page: int, per_page: int, total_pages: int, has_next_page: bool, has_prev_page: bool}}
     */
    public function getPaginatedTodos(?bool $completed = null, int $page = 1, int $limit = 20): array
    {
        $nsVersion = $this->cache->getNamespaceVersion(self::CACHE_NAMESPACE_LIST);
        $filterKey = $completed === null ? 'all' : ($completed ? 'completed' : 'pending');
        $cacheKey  = self::CACHE_PREFIX_LIST . "v{$nsVersion}_{$filterKey}_p{$page}_l{$limit}";

        $cached = $this->cache->get($cacheKey);
        if ($cached !== false && is_array($cached) && isset($cached['items'], $cached['pagination'])) {
            $todos = array_map(fn($item) => $item instanceof Todo ? $item : Todo::fromArray($item), $cached['items']);

            return [
                'todos'      => $todos,
                'pagination' => $cached['pagination'],
            ];
        }

        $total      = $this->repository->countAll($completed);
        $offset     = ($page - 1) * $limit;
        $todos      = $this->repository->findAll($completed, $limit, $offset);
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

        $pagination = [
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $limit,
            'total_pages'   => $totalPages,
            'has_next_page' => $page < $totalPages,
            'has_prev_page' => $page > 1,
        ];

        // Store serialized items and pagination metadata in cache
        $serializedItems = array_map(fn(Todo $t) => $t->jsonSerialize(), $todos);
        $this->cache->set($cacheKey, [
            'items'      => $serializedItems,
            'pagination' => $pagination,
        ]);

        return [
            'todos'      => $todos,
            'pagination' => $pagination,
        ];
    }

    /**
     * @throws TodoNotFoundException
     */
    public function getTodoById(int $id): Todo
    {
        $cacheKey = self::CACHE_PREFIX_ITEM . $id;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return Todo::fromArray($cached);
        }

        $todo = $this->repository->findById($id);
        if ($todo === null) {
            throw new TodoNotFoundException($id);
        }

        $this->cache->set($cacheKey, $todo->jsonSerialize());

        return $todo;
    }

    public function createTodo(CreateTodoDTO $dto): Todo
    {
        $todo = $this->repository->create($dto->title, $dto->description, $dto->completed);

        // Invalidate list caches using O(1) version bumping and targeted direct key deletion
        $this->invalidateListCaches();

        // Warm up single item cache
        if ($todo->id !== null) {
            $this->cache->set(self::CACHE_PREFIX_ITEM . $todo->id, $todo->jsonSerialize());
        }

        $this->logger?->info('Todo created successfully', ['id' => $todo->id, 'title' => $todo->title]);

        return $todo;
    }

    /**
     * @throws TodoNotFoundException
     */
    public function updateTodo(int $id, UpdateTodoDTO $dto): Todo
    {
        $existing = $this->repository->findById($id);
        if ($existing === null) {
            throw new TodoNotFoundException($id);
        }

        $updated = $this->repository->update($id, $dto->toArray());
        if ($updated === null) {
            throw new TodoNotFoundException($id);
        }

        $this->cache->delete(self::CACHE_PREFIX_ITEM . $id);
        $this->invalidateListCaches();
        $this->cache->set(self::CACHE_PREFIX_ITEM . $id, $updated->jsonSerialize());
        $this->logger?->info('Todo updated successfully', ['id' => $id]);

        return $updated;
    }

    /**
     * @throws TodoNotFoundException
     */
    public function deleteTodo(int $id): void
    {
        $existing = $this->repository->findById($id);
        if ($existing === null) {
            throw new TodoNotFoundException($id);
        }

        $deleted = $this->repository->delete($id);
        if (! $deleted) {
            throw new TodoNotFoundException($id);
        }

        $this->cache->delete(self::CACHE_PREFIX_ITEM . $id);
        $this->invalidateListCaches();
        $this->logger?->info('Todo deleted successfully', ['id' => $id]);
    }

    /**
     * Invalidate list caches by bumping namespace version and deleting known list keys directly.
     */
    private function invalidateListCaches(): void
    {
        // 1. O(1) versioned cache invalidation
        $this->cache->incrementNamespaceVersion(self::CACHE_NAMESPACE_LIST);

        // 2. Direct deletion of known static list keys
        $this->cache->deleteMulti([
            self::CACHE_PREFIX_LIST . 'all',
            self::CACHE_PREFIX_LIST . 'completed',
            self::CACHE_PREFIX_LIST . 'pending',
        ]);
    }
}
