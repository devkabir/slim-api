# Complete CRUD Recipe & Boilerplate for Slim 4

This recipe demonstrates how to create a complete, production-ready CRUD resource (`Project`) following the exact architectural conventions of this codebase.

---

## 1. DTOs (`src/DTOs/`)

### `CreateProjectDTO.php`
```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class CreateProjectDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public string $status = 'active'
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        // 1. Disallow unexpected fields
        $allowed = ['name', 'description', 'status'];
        $unexpected = array_diff(array_keys($data), $allowed);
        if (!empty($unexpected)) {
            $errors['unexpected_properties'] = sprintf(
                'Unrecognized properties: %s. Only %s are allowed.',
                implode(', ', $unexpected),
                implode(', ', $allowed)
            );
        }

        // 2. Validate name
        $name = '';
        if (!array_key_exists('name', $data)) {
            $errors['name'] = 'The name field is required.';
        } elseif (!is_string($data['name'])) {
            $errors['name'] = 'The name field must be a string.';
        } else {
            $trimmed = trim($data['name']);
            if ($trimmed === '') {
                $errors['name'] = 'The name field cannot be blank.';
            } elseif (mb_strlen($trimmed) > 255) {
                $errors['name'] = 'The name field cannot exceed 255 characters.';
            } else {
                $name = $trimmed;
            }
        }

        // 3. Validate description
        $description = null;
        if (array_key_exists('description', $data)) {
            if ($data['description'] !== null && !is_string($data['description'])) {
                $errors['description'] = 'The description must be a string or null.';
            } elseif (is_string($data['description'])) {
                $trimmedDesc = trim($data['description']);
                $description = $trimmedDesc === '' ? null : $trimmedDesc;
            }
        }

        // 4. Validate status
        $status = 'active';
        if (array_key_exists('status', $data)) {
            $allowedStatuses = ['active', 'paused', 'completed'];
            if (!is_string($data['status']) || !in_array($data['status'], $allowedStatuses, true)) {
                $errors['status'] = sprintf('The status must be one of: %s.', implode(', ', $allowedStatuses));
            } else {
                $status = $data['status'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return new self(name: $name, description: $description, status: $status);
    }
}
```

### `UpdateProjectDTO.php`
```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class UpdateProjectDTO
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public array $attributes
    ) {}

    public static function fromArray(array $data): self
    {
        $errors = [];
        $allowed = ['name', 'description', 'status'];
        $unexpected = array_diff(array_keys($data), $allowed);
        if (!empty($unexpected)) {
            $errors['unexpected_properties'] = sprintf('Unrecognized properties: %s.', implode(', ', $unexpected));
        }

        if (empty($data)) {
            $errors['payload'] = 'At least one field must be provided for update.';
        }

        $attributes = [];

        if (array_key_exists('name', $data)) {
            if (!is_string($data['name']) || trim($data['name']) === '') {
                $errors['name'] = 'The name cannot be empty.';
            } else {
                $attributes['name'] = trim($data['name']);
            }
        }

        if (array_key_exists('description', $data)) {
            if ($data['description'] !== null && !is_string($data['description'])) {
                $errors['description'] = 'The description must be a string or null.';
            } else {
                $attributes['description'] = $data['description'] !== null ? trim((string)$data['description']) : null;
            }
        }

        if (array_key_exists('status', $data)) {
            $allowedStatuses = ['active', 'paused', 'completed'];
            if (!is_string($data['status']) || !in_array($data['status'], $allowedStatuses, true)) {
                $errors['status'] = sprintf('The status must be one of: %s.', implode(', ', $allowedStatuses));
            } else {
                $attributes['status'] = $data['status'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return new self(attributes: $attributes);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
```

---

## 2. Model (`src/Models/Project.php`)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

readonly class Project implements JsonSerializable
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $description,
        public string $status,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            name: (string)$data['name'],
            description: isset($data['description']) && $data['description'] !== '' ? (string)$data['description'] : null,
            status: (string)($data['status'] ?? 'active'),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
```

---

## 3. Repository (`src/Repositories/ProjectRepository.php`)

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;
use App\Models\Project;
use RuntimeException;
use Psr\Log\LoggerInterface;

class ProjectRepository
{
    public function __construct(
        private PDO $db,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * @return Project[]
     */
    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM `projects` ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return array_map(fn($row) => Project::fromArray($row), $rows);
        } catch (Throwable $e) {
            $this->logger?->error('ProjectRepository::findAll failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function countAll(): int
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM `projects`");
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $this->logger?->error('ProjectRepository::countAll failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function findById(int $id): ?Project
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM `projects` WHERE `id` = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return $row ? Project::fromArray($row) : null;
        } catch (Throwable $e) {
            $this->logger?->error('ProjectRepository::findById failed', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function create(string $name, ?string $description, string $status): Project
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO `projects` (`name`, `description`, `status`) VALUES (:name, :description, :status)"
            );
            $stmt->execute([
                ':name'        => $name,
                ':description' => $description,
                ':status'      => $status,
            ]);

            $id = (int)$this->db->lastInsertId();
            $project = $this->findById($id);

            if ($project === null) {
                throw new RuntimeException('Created project could not be re-loaded.');
            }

            return $project;
        } catch (Throwable $e) {
            $this->logger?->error('ProjectRepository::create failed', ['name' => $name, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function update(int $id, array $data): ?Project
    {
        if (empty($data)) {
            return $this->findById($id);
        }

        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $value;
        }

        try {
            $sql = "UPDATE `projects` SET " . implode(', ', $fields) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->findById($id);
        } catch (Throwable $e) {
            $this->logger?->error('ProjectRepository::update failed', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM `projects` WHERE `id` = :id");
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            $this->logger?->error('ProjectRepository::delete failed', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

---

## 4. Service (`src/Services/ProjectService.php`)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Config\Cache;
use App\DTOs\CreateProjectDTO;
use App\DTOs\UpdateProjectDTO;
use Psr\Log\LoggerInterface;
use App\Repositories\ProjectRepository;

class ProjectService
{
    private const CACHE_PREFIX_ITEM    = 'project_item_';
    private const CACHE_PREFIX_LIST    = 'project_list_';
    private const CACHE_NAMESPACE_LIST = 'project_list_ns';

    public function __construct(
        private ProjectRepository $repository,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * @return array{projects: Project[], pagination: array<string, mixed>}
     */
    public function getPaginatedProjects(int $page = 1, int $limit = 20): array
    {
        $nsVersion = Cache::getNamespaceVersion(self::CACHE_NAMESPACE_LIST);
        $cacheKey  = self::CACHE_PREFIX_LIST . "v{$nsVersion}_p{$page}_l{$limit}";

        $cached = Cache::get($cacheKey);
        if ($cached !== false && is_array($cached) && isset($cached['items'], $cached['pagination'])) {
            $projects = array_map(fn($item) => $item instanceof Project ? $item : Project::fromArray($item), $cached['items']);
            return ['projects' => $projects, 'pagination' => $cached['pagination']];
        }

        $total      = $this->repository->countAll();
        $offset     = ($page - 1) * $limit;
        $projects   = $this->repository->findAll($limit, $offset);
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

        $pagination = [
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $limit,
            'total_pages'   => $totalPages,
            'has_next_page' => $page < $totalPages,
            'has_prev_page' => $page > 1,
        ];

        Cache::set($cacheKey, [
            'items'      => array_map(fn(Project $p) => $p->jsonSerialize(), $projects),
            'pagination' => $pagination,
        ]);

        return ['projects' => $projects, 'pagination' => $pagination];
    }

    public function getProjectById(int $id): ?Project
    {
        $cacheKey = self::CACHE_PREFIX_ITEM . $id;
        $cached = Cache::get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return Project::fromArray($cached);
        }

        $project = $this->repository->findById($id);
        if ($project !== null) {
            Cache::set($cacheKey, $project->jsonSerialize());
        }

        return $project;
    }

    public function createProject(CreateProjectDTO $dto): Project
    {
        $project = $this->repository->create($dto->name, $dto->description, $dto->status);
        $this->invalidateListCaches();

        if ($project->id !== null) {
            Cache::set(self::CACHE_PREFIX_ITEM . $project->id, $project->jsonSerialize());
        }

        $this->logger?->info('Project created', ['id' => $project->id, 'name' => $project->name]);
        return $project;
    }

    public function updateProject(int $id, UpdateProjectDTO $dto): ?Project
    {
        $existing = $this->repository->findById($id);
        if (!$existing) {
            return null;
        }

        $updated = $this->repository->update($id, $dto->toArray());
        if ($updated) {
            Cache::delete(self::CACHE_PREFIX_ITEM . $id);
            $this->invalidateListCaches();
            Cache::set(self::CACHE_PREFIX_ITEM . $id, $updated->jsonSerialize());
            $this->logger?->info('Project updated', ['id' => $id]);
        }

        return $updated;
    }

    public function deleteProject(int $id): bool
    {
        $deleted = $this->repository->delete($id);
        if ($deleted) {
            Cache::delete(self::CACHE_PREFIX_ITEM . $id);
            $this->invalidateListCaches();
            $this->logger?->info('Project deleted', ['id' => $id]);
        }

        return $deleted;
    }

    private function invalidateListCaches(): void
    {
        Cache::incrementNamespaceVersion(self::CACHE_NAMESPACE_LIST);
    }
}
```

---

## 5. Controller (`src/Controllers/ProjectController.php`)

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTOs\CreateProjectDTO;
use App\DTOs\UpdateProjectDTO;
use App\Response\ApiResponse;
use App\Services\ProjectService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProjectController
{
    public function __construct(
        private ProjectService $projectService,
        private ApiResponse $response
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page   = max(1, (int)($params['page'] ?? 1));
        $limit  = min(100, max(1, (int)($params['limit'] ?? 20)));

        $result = $this->projectService->getPaginatedProjects($page, $limit);

        return $this->response->success(
            data: $result['projects'],
            meta: ['pagination' => $result['pagination']]
        );
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $this->validateId($args['id'] ?? null);
        if ($id === null) {
            return $this->response->error('The project ID must be a positive integer.', 'VALIDATION_ERROR', 422);
        }

        $project = $this->projectService->getProjectById($id);
        if (!$project) {
            return $this->response->error("Project with ID {$id} not found.", 'NOT_FOUND', 404);
        }

        return $this->response->success(data: $project);
    }

    public function create(Request $request, Response $response): Response
    {
        $body    = (array)($request->getParsedBody() ?? []);
        $dto     = CreateProjectDTO::fromArray($body);
        $project = $this->projectService->createProject($dto);

        return $this->response->success(data: $project, message: 'Project created successfully.', statusCode: 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $this->validateId($args['id'] ?? null);
        if ($id === null) {
            return $this->response->error('The project ID must be a positive integer.', 'VALIDATION_ERROR', 422);
        }

        $body    = (array)($request->getParsedBody() ?? []);
        $dto     = UpdateProjectDTO::fromArray($body);
        $updated = $this->projectService->updateProject($id, $dto);

        if (!$updated) {
            return $this->response->error("Project with ID {$id} not found.", 'NOT_FOUND', 404);
        }

        return $this->response->success(data: $updated, message: 'Project updated successfully.');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $this->validateId($args['id'] ?? null);
        if ($id === null) {
            return $this->response->error('The project ID must be a positive integer.', 'VALIDATION_ERROR', 422);
        }

        $deleted = $this->projectService->deleteProject($id);
        if (!$deleted) {
            return $this->response->error("Project with ID {$id} not found.", 'NOT_FOUND', 404);
        }

        return $this->response->success(message: "Project with ID {$id} deleted successfully.");
    }

    private function validateId(mixed $id): ?int
    {
        $validated = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return ($validated === false || $validated === null) ? null : (int)$validated;
    }
}
```
