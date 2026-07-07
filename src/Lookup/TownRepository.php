<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only queries against the lookup table.
 */
final class TownRepository
{
    /**
     * Partial-match search across the given searchable columns, returning only
     * the given display columns.
     *
     * Column identifiers are whitelisted (Columns::whitelist) and then
     * back-ticked; the search term is bound via $wpdb->prepare, so neither the
     * identifiers nor the value can carry injection.
     *
     * @param list<string> $searchColumns
     * @param list<string> $displayColumns
     * @return list<array<string,string|null>>
     */
    public function search(string $term, array $searchColumns, array $displayColumns, int $limit): array
    {
        global $wpdb;

        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $searchColumns  = Columns::whitelist($searchColumns);
        $displayColumns = Columns::whitelist($displayColumns);

        if ($searchColumns === [] || $displayColumns === []) {
            return [];
        }

        $limit = max(1, min(LookupSettings::MAX_RESULT_LIMIT, $limit));
        $table = TownSchema::tableName();

        $selectList = implode(', ', array_map(static fn (string $c): string => "`$c`", $displayColumns));

        $like       = '%' . $wpdb->esc_like($term) . '%';
        $prefixLike = $wpdb->esc_like($term) . '%';

        $conditions = [];
        $params     = [];
        foreach ($searchColumns as $column) {
            $conditions[] = "`$column` LIKE %s";
            $params[]     = $like;
        }

        // Rank prefix matches on the first searchable column above the rest,
        // then order alphabetically by the first display column for stability.
        $rankColumn    = $searchColumns[0];
        $orderColumn   = $displayColumns[0];
        $params[]      = $prefixLike;

        $sql = "SELECT $selectList
                FROM `$table`
                WHERE " . implode(' OR ', $conditions) . "
                ORDER BY (`$rankColumn` LIKE %s) DESC, `$orderColumn` ASC
                LIMIT $limit";

        $prepared = $wpdb->prepare($sql, $params);

        /** @var list<array<string,string|null>>|null $rows */
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
