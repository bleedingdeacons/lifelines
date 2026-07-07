<?php

declare(strict_types=1);

namespace LifeLines\Lookup;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical column whitelist for the uk_towns dataset.
 *
 * This is the security linchpin of the lookup feature: column identifiers are
 * never taken from user input directly. Admin-configured column names are only
 * ever used in SQL if they appear as keys here, so column identifiers can be
 * safely back-ticked into queries while search *values* go through
 * $wpdb->prepare().
 */
final class Columns
{
    /**
     * Column key => human-readable label.
     *
     * The keys and their order mirror the uk_towns table definition exactly, so
     * a column-less `INSERT ... VALUES` from the dump maps 1:1.
     *
     * @var array<string,string>
     */
    public const ALL = [
        'ID'           => 'ID',
        'Place'        => 'Place',
        'AA_Region'    => 'AA Region',
        'Service_Name' => 'Service',
        'Number'       => 'Phone Number',
        'Open_Times'   => 'Open Times',
        'County'       => 'County',
        'Country'      => 'Country',
        'Postcode'     => 'Postcode',
        'AreaCode'     => 'Area Code',
        'GridRef'      => 'Grid Ref',
        'Latitude'     => 'Latitude',
        'Longitude'    => 'Longitude',
        'Easting'      => 'Easting',
        'Northing'     => 'Northing',
        'Region'       => 'Region',
        'Category'     => 'Category',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function isValid(string $column): bool
    {
        return isset(self::ALL[$column]);
    }

    public static function label(string $column): string
    {
        return self::ALL[$column] ?? $column;
    }

    /**
     * Filter an arbitrary list down to valid, de-duplicated column keys,
     * preserving order.
     *
     * @param mixed $columns
     * @return list<string>
     */
    public static function whitelist($columns): array
    {
        if (!is_array($columns)) {
            return [];
        }

        $valid = [];
        foreach ($columns as $column) {
            if (is_string($column) && self::isValid($column) && !in_array($column, $valid, true)) {
                $valid[] = $column;
            }
        }

        return $valid;
    }
}
