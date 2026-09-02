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
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        // 1. Reject unexpected properties
        $allowedFields = ['title', 'description', 'completed'];
        $unexpected    = array_diff(array_keys($data), $allowedFields);
        if ( ! empty($unexpected)) {
            $errors['unexpected_properties'] = sprintf(
                'Unrecognized properties: %s. Only %s are allowed.',
                implode(', ', $unexpected),
                implode(', ', $allowedFields)
            );
        }

        $hasTitle       = array_key_exists('title', $data);
        $hasDescription = array_key_exists('description', $data);
        $hasCompleted   = array_key_exists('completed', $data);

        // 2. Ensure at least one updatable property is provided
        if ( ! $hasTitle && ! $hasDescription && ! $hasCompleted && empty($errors)) {
            $errors['body'] = 'At least one updatable field (title, description, or completed) must be provided.';
        }

        // 3. Validate 'title'
        $title = null;
        if ($hasTitle) {
            if ( ! is_string($data['title'])) {
                $errors['title'] = 'The title must be a string.';
            } else {
                $trimmed = trim($data['title']);
                if ($trimmed === '') {
                    $errors['title'] = 'The title cannot be blank.';
                } elseif (mb_strlen($trimmed) > 255) {
                    $errors['title'] = 'The title cannot exceed 255 characters.';
                } else {
                    $title = $trimmed;
                }
            }
        }

        // 4. Validate 'description'
        $description = null;
        if ($hasDescription) {
            if ($data['description'] !== null && ! is_string($data['description'])) {
                $errors['description'] = 'The description must be a string or null.';
            } elseif (is_string($data['description'])) {
                $trimmedDesc = trim($data['description']);
                if (mb_strlen($trimmedDesc) > 65535) {
                    $errors['description'] = 'The description exceeds maximum allowed length of 65,535 characters.';
                } else {
                    $description = $trimmedDesc === '' ? null : $trimmedDesc;
                }
            }
        }

        // 5. Validate 'completed'
        $completed = null;
        if ($hasCompleted) {
            if ( ! is_bool($data['completed'])) {
                $errors['completed'] = 'The completed field must be an actual boolean (true or false).';
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
