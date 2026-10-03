<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

// Resposta paginada ESTÀNDARD de les llistes que poden créixer (docs: frontend/CLAUDE.md, «Llistes llargues»): l'endpoint
// rep `?page=1&perPage=10` i retorna { data, page, perPage, total, hasMore }. El frontend carrega la primera pàgina i va
// demanant les següents en fer scroll (hook useInfiniteList + InfiniteScrollSentinel); mai no s'envia tota la llista.
class Paged
{
    public const MAX_PER_PAGE = 50;

    /** Pàgina i mida demanades, ja acotades. @return array{0: int, 1: int} */
    public static function window(Request $request, int $defaultPerPage = 10): array
    {
        return [
            max(1, (int) $request->query('page', 1)),
            min(self::MAX_PER_PAGE, max(1, (int) $request->query('perPage', $defaultPerPage))),
        ];
    }

    /** Embolcall estàndard d'una pàgina ja carregada. */
    public static function envelope(iterable $data, int $page, int $perPage, int $total): array
    {
        return [
            'data' => collect($data)->values(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hasMore' => $page * $perPage < $total,
        ];
    }

    /** @param  callable(mixed): array  $map  Rep un model (Eloquent) o una fila (consulta base). */
    public static function of(Builder|Relation|QueryBuilder $query, Request $request, callable $map, int $defaultPerPage = 10): array
    {
        [$page, $perPage] = self::window($request, $defaultPerPage);
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        return self::envelope($rows->map($map), $page, $perPage, $total);
    }
}
