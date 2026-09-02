<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

class Todo implements JsonSerializable
{
    public function __construct(
        public ?int $id = null,
        public string $title = '',
        public ?string $description = null,
        public bool $completed = false,
        public ?string $created_at = null,
        public ?string $updated_at = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            title: (string)($data['title'] ?? ''),
            description: $data['description'] ?? null,
            completed: !empty($data['completed']),
            created_at: $data['created_at'] ?? null,
            updated_at: $data['updated_at'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'completed' => (bool)$this->completed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
