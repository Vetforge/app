<?php

declare(strict_types=1);

namespace App\Support;

class SearchTerm
{
    /**
     * Construire un motif LIKE « contient » en échappant les jokers (\, %, _)
     * afin que le terme saisi soit toujours recherché littéralement.
     */
    public static function likeContains(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
