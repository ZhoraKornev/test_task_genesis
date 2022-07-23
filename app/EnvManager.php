<?php

namespace PhpEnv;

class EnvManager
{
    protected $error = '';

    /**
     * Returns an explanation of what is happening
     *
     * @return string
     */
    public static function getError(): string
    {
        return self::$error;
    }

    public static function get(string $key)
    {
        return getenv($key);
    }

    public static function set(string $key, $value): bool
    {
        return putenv(sprintf('%s=%s', $key, $value));
    }

    public static function setArray(array $keyValue): bool
    {
        $next = true;
        $keys = array_keys($keyValue);
        $max = count($keyValue);
        $i = 0;

        while ($next && ($i < $max)) {
            $k = $keys[$i];
            $v = $keyValue[$k];

            if (strpos($v, '${') !== false) {
                self::parseEnvVars($v);
            }

            $next = self::set($k, $v);

            if(!$next) {
                self::$error = "The var $k, could not be setted, value: $v";
            }

            ++$i;
        }

        return $next;
    }

    public static function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            return [];
        }

        $matches = $result = $keys = $values = [];

        $content = file_get_contents($filename);

        $content = preg_replace('/#.+/', '', $content);

        preg_match_all('/(.+)=(.+)/', $content, $matches);

        $max = count($matches[0]);

        if (!$max) {
            return $result;
        }

        $keys = $matches[1];
        $values = $matches[2];

        for ($i = 0 ;$i < $max; ++$i) {
            $key = $keys[$i];
            $result[$key] = self::getValue($values[$i]);
        }

        return $result;
    }

    public static function loadVarsFromFile(string $filename): bool
    {
        return self::setArray(self::parse($filename));
    }

    protected static function getValue(string $value): string
    {
        if (strpos($value, '"') !== false) {
            $value = preg_replace('/"/', '', $value);
        }

        return ($value != 'null') ? $value : '';
    }

    protected static function parseEnvVars(string &$value): void
    {
        $matches = $replace = $keys = [];

        preg_match_all('/\${([\w\d]+)}/', $value, $matches);

        $max = count($matches[0]);

        if (!$max) {
            return;
        }

        $replace = $matches[0];
        $keys = $matches[1];

        for ($i = 0 ;$i < $max; ++$i) {
            $varValue = self::get($keys[$i]);
            $value = str_replace($replace[$i], $varValue, $value);
        }
    }
}