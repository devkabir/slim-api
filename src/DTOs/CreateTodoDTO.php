<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

readonly class CreateTodoDTO
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public bool $completed = false
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

        // 2. Validate 'title' (required, string, 1-255 characters)
        $title = '';
        if ( ! array_key_exists('title', $data)) {
            $errors['title'] = 'The title field is required.';
        } elseif ( ! is_string($data['title'])) {
            $errors['title'] = 'The title field must be a string.';
        } else {
            $trimmed = trim($data['title']);
            if ($trimmed === '') {
                $errors['title'] = 'The title field cannot be blank.';
            } elseif (mb_strlen($trimmed) > 255) {
                $errors['title'] = 'The title field cannot exceed 255 characters.';
            } else {
                $title = $trimmed;
            }
        }

        // 3. Validate 'description' (optional, string or null, max 65535 chars)
        $description = null;
        if (array_key_exists('description', $data)) {
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

        // 4. Validate 'completed' (optional, must be strict boolean)
        $completed = false;
        if (array_key_exists('completed', $data)) {
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
            completed: $completed
        );
    }
}
