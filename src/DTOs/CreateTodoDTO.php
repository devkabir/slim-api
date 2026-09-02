<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class CreateTodoDTO
{
    public function __construct(
        public string $title,
        public ?string $description,
        public bool $completed
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        if ( ! isset($data['title']) || ! is_string($data['title'])) {
            $errors['title'] = 'The title field is required and must be a string.';
        } else {
            $title = trim($data['title']);
            if ($title === '') {
                $errors['title'] = 'The title field cannot be blank.';
            } elseif (mb_strlen($title) > 255) {
                $errors['title'] = 'The title field cannot exceed 255 characters.';
            }
        }

        $description = null;
        if (array_key_exists('description', $data)) {
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

        $completed = false;
        if (array_key_exists('completed', $data)) {
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
            title: trim((string)$data['title']),
            description: $description,
            completed: $completed
        );
    }
}
