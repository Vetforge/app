<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    /**
     * SQL de recherche LIKE sur une colonne date, portable selon le driver.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function dateSearchSql(Builder $query, string $column): string
    {
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        return match (DB::getDriverName()) {
            'pgsql' => "LOWER(COALESCE(TO_CHAR({$wrappedColumn}, 'YYYY-MM-DD'), '')) LIKE ? ESCAPE '\\'",
            'sqlite' => "LOWER(COALESCE(CAST({$wrappedColumn} AS TEXT), '')) LIKE ? ESCAPE '\\'",
            'sqlsrv' => "LOWER(COALESCE(CONVERT(varchar(10), {$wrappedColumn}, 23), '')) LIKE ?",
            default => "LOWER(COALESCE(CAST({$wrappedColumn} AS CHAR), '')) LIKE ?",
        };
    }
}
