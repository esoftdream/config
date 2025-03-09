<?php

namespace Esoftdream;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;

class Config
{
    private BaseConnection $db;
    private string $table = 'sys_config';
    private $cache;
    private $encrypter;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->cache = Services::cache();
        $this->encrypter = Services::encrypter();
    }

    /**
     * Menambahkan atau mengupdate konfigurasi berdasarkan key dengan caching & enkripsi.
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
            'config_value'            => $this->encryptData($value), // Enkripsi nilai sebelum disimpan
            'config_type'             => gettype($value),
            'config_updated_datetime' => Time::now()->toDateTimeString(),
        ];

        if ($exists) {
            $result = $builder->where('config_key', $key)->update($data);
        } else {
            $data['config_created_datetime'] = Time::now()->toDateTimeString();
            $result = $builder->insert($data);
        }

        if ($result) {
            // Simpan ke cache setelah update
            $this->cache->save("config_{$key}", $value, 3600); // Cache selama 1 jam
        }

        return $result;
    }

    /**
     * Mengambil nilai konfigurasi berdasarkan key dengan caching.
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        // Cek apakah ada di cache terlebih dahulu
        if ($this->cache->get("config_{$key}")) {
            return $this->cache->get("config_{$key}");
        }

        $builder = $this->db->table($this->table);
        $result = $builder
            ->select('config_value, config_type')
            ->where('config_key', $key)
            ->get()
            ->getRow();

        if ($result) {
            $decryptedValue = $this->decryptData($result->config_value); // Dekripsi sebelum dikembalikan
            $parsedValue = $this->parseValue($decryptedValue, $result->config_type);

            // Simpan ke cache untuk pemanggilan selanjutnya
            $this->cache->save("config_{$key}", $parsedValue, 3600);

            return $parsedValue;
        }

        return null;
    }

    /**
     * Menghapus konfigurasi berdasarkan key, serta menghapus cache-nya.
     */
    public function forget(string $key): bool
    {
        $builder = $this->db->table($this->table);

        $deleted = $builder->where('config_key', $key)->delete();
        if ($deleted) {
            $this->cache->delete("config_{$key}"); // Hapus dari cache
        }

        return $deleted;
    }

    /**
     * Mengenkripsi data sebelum disimpan ke database.
     */
    private function encryptData($value): string
    {
        return base64_encode($this->encrypter->encrypt($this->prepareValue($value)));
    }

    /**
     * Mendekripsi data setelah diambil dari database.
     */
    private function decryptData($encryptedValue)
    {
        return $this->encrypter->decrypt(base64_decode($encryptedValue));
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
