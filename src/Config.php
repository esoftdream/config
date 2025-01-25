<?php

namespace Esoftdream;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

class Config
{
    private BaseConnection $db;
    private string $table = 'sys_config';

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Menambahkan atau mengupdate konfigurasi berdasarkan key.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): bool
    {
        $builder = $this->db->table($this->table);

        // Cek apakah key sudah ada
        $exists = $builder->where('config_key', $key)->countAllResults();

        $data = [
            'config_key'              => $key,
            'config_value'            => $this->prepareValue($value),
            'config_type'             => gettype($value),
            'config_updated_datetime' => Time::now()->toDateTimeString(),
        ];

        if ($exists) {
            // Update jika sudah ada
            return $builder->where('config_key', $key)->update($data);
        }
        // Insert jika belum ada
        $data['config_created_datetime'] = Time::now()->toDateTimeString();

        return $builder->insert($data);
    }

    /**
     * Mengambil nilai konfigurasi berdasarkan key.
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        $builder = $this->db->table($this->table);

        $result = $builder
            ->select('config_value, config_type')
            ->where('config_key', $key)
            ->get()
            ->getRow();

        if ($result) {
            // Return value berdasarkan type
            return $this->parseValue($result->config_value, $result->config_type);
        }

        return null;
    }

    /**
     * Menghapus konfigurasi berdasarkan key.
     */
    public function forget(string $key): bool
    {
        $builder = $this->db->table($this->table);

        return $builder->where('config_key', $key)->delete();
    }

    /**
     * Takes care of converting some item types so they can be safely
     * stored and re-hydrated into the config files.
     *
     * @param mixed $value
     *
     * @return mixed|string
     */
    private function prepareValue($value)
    {
        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return $value;
    }

    /**
     * Handles some special case conversions that
     * data might have been saved as, such as booleans
     * and serialized data.
     *
     * @param mixed $value
     *
     * @return bool|mixed
     */
    private function parseValue($value, string $type)
    {
        // Serialized?
        if ($this->isSerialized($value)) {
            $value = unserialize($value);
        }

        settype($value, $type);

        return $value;
    }

    /**
     * Checks to see if an object is serialized and correctly formatted.
     *
     * Taken from Wordpress core functions.
     *
     * @param mixed $data
     * @param bool  $strict Whether to be strict about the end of the string.
     */
    private function isSerialized($data, $strict = true): bool
    {
        // If it isn't a string, it isn't serialized.
        if (! is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');
            // Either ; or } must exist.
            if (false === $semicolon && false === $brace) {
                return false;
            }
            // But neither must be in the first X characters.
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];

        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }

                // Or else fall through.
                // no break
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);

            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool) preg_match("/^{$token}:[0-9.E+-]+;{$end}/", $data);
        }

        return false;
    }
}
