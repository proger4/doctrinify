<?php

declare(strict_types=1);

namespace generated\classes;

class Customer
{
    protected $id = null;
    protected $name = null;
    protected $email = null;
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

    public function getName()
    {
        return $this->name;
    }

    public function setName($value): self
    {
        $this->name = $value;

        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($value): self
    {
        $this->email = $value;

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
