<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class UpdateTodoDTO
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?bool $completed,
        public bool $hasTitle,
        public bool $hasDescription,
        public bool $hasCompleted
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $errors         = [];
        $hasTitle       = array_key_exists('title', $data);
        $hasDescription = array_key_exists('description', $data);
        $hasCompleted   = array_key_exists('completed', $data);

        if ( ! $hasTitle && ! $hasDescription && ! $hasCompleted) {
            throw new ValidationException(['body' => 'No updatable fields were provided.']);
        }

        $title = null;
        if ($hasTitle) {
            if ( ! is_string($data['title'])) {
                $errors['title'] = 'The title must be a string.';
            } else {
                $title = trim($data['title']);
                if ($title === '') {
                    $errors['title'] = 'The title cannot be blank.';
                } elseif (mb_strlen($title) > 255) {
                    $errors['title'] = 'The title cannot exceed 255 characters.';
                }
            }
        }

        $description = null;
        if ($hasDescription) {
            if ($data['description'] !== null && ! is_string($data['description'])) {
                $errors['description'] = 'The description must be a string or null.';
            } elseif (is_string($data['description'])) {
                $desc = trim($data['description']);
                if (mb_strlen($desc) > 65535) {
                    $errors['description'] = 'The description exceeds maximum allowed length.';
                } else {
                    $description = $desc === '' ? null : $desc;
                }
            }
        }

        $completed = null;
        if ($hasCompleted) {
            if ( ! is_bool($data['completed'])) {
                $errors['completed'] = 'The completed field must be a boolean.';
            } else {
                $completed = $data['completed'];
            }
        }

        if ( ! empty($errors)) {
            throw new ValidationException($errors);
        }

        return new self(
            title: $title,
            description: $description,
            completed: $completed,
            hasTitle: $hasTitle,
            hasDescription: $hasDescription,
            hasCompleted: $hasCompleted
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        if ($this->hasTitle) {
            $data['title'] = $this->title;
        }
        if ($this->hasDescription) {
            $data['description'] = $this->description;
        }
        if ($this->hasCompleted) {
            $data['completed'] = $this->completed;
        }

        return $data;
    }
}
