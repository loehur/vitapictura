<?php

class DBC
{
    const db_host = \Env::DB_HOST ?? 'localhost';

    private static function defaultDbm(): array
    {
        return [
            'dev' => [
                0 => ['db' => 'myapp', 'user' => 'root', 'pass' => ''],
            ],
            'pro' => [
                0 => ['db' => 'myapp', 'user' => 'myapp', 'pass' => ''],
            ],
        ];
    }

    public static function dbm(): array
    {
        $merged = self::defaultDbm();

        if (!defined('Env::DB_CREDENTIALS')) {
            return $merged;
        }

        $envCreds = \Env::DB_CREDENTIALS;
        if (!is_array($envCreds)) {
            return $merged;
        }

        foreach ($merged as $mode => $databases) {
            if (empty($envCreds[$mode]) || !is_array($envCreds[$mode])) {
                continue;
            }

            foreach ($envCreds[$mode] as $index => $config) {
                if (!is_array($config)) {
                    continue;
                }

                $base = $merged[$mode][$index] ?? ['db' => '', 'user' => '', 'pass' => ''];
                $merged[$mode][$index] = array_merge($base, $config);
            }
        }

        return $merged;
    }

    public static function getDbConfig(int $index): array
    {
        $mode = \Env::MODE ?? 'dev';
        $dbm = self::dbm();

        if (!isset($dbm[$mode][$index])) {
            throw new \RuntimeException("Database config index {$index} tidak ditemukan untuk mode '{$mode}'");
        }

        return $dbm[$mode][$index];
    }
}
