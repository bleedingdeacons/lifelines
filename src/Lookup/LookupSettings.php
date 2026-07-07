<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads and writes the lookup configuration, stored in a single wp_options row.
 *
 * All column lists are passed through Columns::whitelist() on the way in and out
 * so a stored option can never introduce an unknown/unsafe column identifier.
 */
final class LookupSettings
{
    public const OPTION = 'lifelines_lookup_settings';

    public const MAX_RESULT_LIMIT = 200;

    /** @var array<string,mixed> */
    private array $data;

    public function __construct()
    {
        $stored = get_option(self::OPTION, []);
        $this->data = wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'search_columns'  => ['Place', 'Postcode', 'County', 'Region', 'AreaCode'],
            'display_columns' => ['Place', 'County', 'Service_Name', 'Number', 'Open_Times', 'Postcode'],
            'result_limit'    => 50,
            'min_chars'       => 2,
        ];
    }

    /**
     * Columns matched against the search term (partial match). Falls back to the
     * defaults if the stored/whitelisted list is empty, so search is never broken.
     *
     * @return list<string>
     */
    public function searchColumns(): array
    {
        $columns = Columns::whitelist($this->data['search_columns'] ?? []);

        return $columns !== [] ? $columns : self::defaults()['search_columns'];
    }

    /**
     * Columns shown in the results table.
     *
     * @return list<string>
     */
    public function displayColumns(): array
    {
        $columns = Columns::whitelist($this->data['display_columns'] ?? []);

        return $columns !== [] ? $columns : self::defaults()['display_columns'];
    }

    public function resultLimit(): int
    {
        $limit = (int) ($this->data['result_limit'] ?? 50);

        return max(1, min(self::MAX_RESULT_LIMIT, $limit));
    }

    public function minChars(): int
    {
        return max(1, (int) ($this->data['min_chars'] ?? 2));
    }

    /**
     * Sanitise raw form input and persist it.
     *
     * @param array<string,mixed> $input
     */
    public static function save(array $input): void
    {
        $clean = [
            'search_columns'  => Columns::whitelist($input['search_columns'] ?? []),
            'display_columns' => Columns::whitelist($input['display_columns'] ?? []),
            'result_limit'    => max(1, min(self::MAX_RESULT_LIMIT, (int) ($input['result_limit'] ?? 50))),
            'min_chars'       => max(1, (int) ($input['min_chars'] ?? 2)),
        ];

        // Guard against saving an empty configuration that would break search.
        if ($clean['search_columns'] === []) {
            $clean['search_columns'] = self::defaults()['search_columns'];
        }
        if ($clean['display_columns'] === []) {
            $clean['display_columns'] = self::defaults()['display_columns'];
        }

        update_option(self::OPTION, $clean);
    }
}
