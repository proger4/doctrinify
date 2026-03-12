<?php

declare(strict_types=1);

namespace generated\classes;

class Admin
{
    protected $id = null;
    protected $username = null;
    protected $password_hash = null;
    protected $type = null;
    protected $created_at = null;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value): self
    {
        $this->id = $value;

        return $this;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($value): self
    {
        $this->username = $value;

        return $this;
    }

    public function getPasswordHash()
    {
        return $this->password_hash;
    }

    public function setPasswordHash($value): self
    {
        $this->password_hash = $value;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value): self
    {
        $this->type = $value;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($value): self
    {
        $this->created_at = $value;

        return $this;
    }
}
