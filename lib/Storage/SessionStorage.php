<?php

namespace OCA\HiorgOAuth\Storage;

use Hybridauth\Storage\StorageInterface;
use OCP\ISession;

class SessionStorage implements StorageInterface
{
    /** @var ISession */
    private $session;

    public function __construct(ISession $session)
    {
        $this->session = $session;
    }

    /**
    * {@inheritdoc}
    */
    public function get($key): mixed
    {
        return $this->session->get($key);
    }

    /**
    * {@inheritdoc}
    */
    public function set($key, $value): void
    {
        $this->session->set($key, $value);
    }

    /**
    * {@inheritdoc}
    */
    public function delete($key): void
    {
        $this->session->remove($key);
    }

    /**
    * {@inheritdoc}
    */
    public function deleteMatch($key): void
    {
        foreach ($this->session as $k => $v) {
            if (strstr($k, $key)) {
                $this->delete($k);
            }
        }
    }

    /**
    * {@inheritdoc}
    */
    public function clear(): void
    {
        $this->session->clear();
    }
}
