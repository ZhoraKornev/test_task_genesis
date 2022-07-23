<?php

namespace App;

use App\Exceptions\EnvHelperInitializationException;

class EnvHelper
{
    protected const DEFAULT_ENV_FILE = '.env';

    protected string $fileName;

    public function __construct(string $fileName = EnvHelper::DEFAULT_ENV_FILE)
    {
        $this->fileName = $fileName;
    }

    /**
     * @return void
     * @throws EnvHelperInitializationException
     */
    public function init(): void
    {
        //TODO add some redis cache for all ENVs
        if (!$this->setArray($this->parseFile())){
            throw new EnvHelperInitializationException();
        }
    }

    public function get(string $key): bool|array|string|int
    {
        return getenv($key);
    }

    public function set(string $key, $value): bool
    {
        return putenv(sprintf('%s=%s', $key, $value));
    }


    protected function getValue(string $value): string
    {
        if (strpos($value, '"') !== false) {
            $value = preg_replace('/"/', '', $value);
        }

        return ($value != 'null') ? $value : '';
    }

    protected function parseEnvVars(string &$value): void
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
            $varValue = $this->get($keys[$i]);
            $value = str_replace($replace[$i], $varValue, $value);
        }
    }

    protected function parseFile(): array
    {
        if (!file_exists($this->fileName)) {
            return [];
        }

        $matches = $result = $keys = $values = [];
        $content = file_get_contents($this->fileName);
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
            $result[$key] = $this->getValue($values[$i]);
        }

        return $result;
    }

    protected function setArray(array $keyValue): bool
    {
        $keys = array_keys($keyValue);
        $max = count($keyValue);
        $i = 0;

        while (($i < $max)) {
            $k = $keys[$i];
            $v = $keyValue[$k];

            if (str_contains($v, '${')) {
                $this->parseEnvVars($v);
            }

            $next = $this->set($k, $v);

            if(!$next) {
                throw new \UnexpectedValueException("The var $k, could not be settled, value: $v");
            }

            ++$i;
        }

        return true;
    }
}
