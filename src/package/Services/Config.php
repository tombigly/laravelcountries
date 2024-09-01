<?php

namespace PragmaRX\Countries\Package\Services;

use Illuminate\Support\Collection;

class Config
{
    /**
     * Configuration.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $config;

    /**
     * Key prefix.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Config constructor.
     *
     * @param  array|null  $config
     */
    public function __construct($config = null)
    {
        $this->initialize($config);
    }

    /**
     * @param $key
     * @return \Illuminate\Support\Collection
     */
    public function get($key)
    {
        return $this->config->get($this->prefix.$key);
    }

    /**
     * @param $config
     */
    protected function initialize($config = [])
    {
        if (\is_object($config)) {
            $this->config = $config;

            $this->prefix = 'countries.';
        } else {
            $this->config = $this->loadConfig()->replaceRecursive($config);
        }
    }

    /**
     * Load the config.
     *
     * @return Collection
     */
    protected function loadConfig()
    {
        return collect(
            require __DIR__.'/../../config/countries.php'
        );
    }

    /**
     * Redirect properties access to config's Collection.
     *
     * @param $name
     * @return mixed|static
     */
    public function __get($name)
    {
        return $this->config->get($name);
    }

    /**
     * Redirect methods calls to config's Coollection.
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return \call_user_func_array([$this->config, $name], $arguments);
    }
}
