<?php

declare(strict_types=1);

namespace generated\classes;

class Category
{
    protected $id = null;
    protected $parent_id = null;
    protected $name = null;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value): self
    {
        $this->id = $value;

        return $this;
    }

    public function getParentId()
    {
        return $this->parent_id;
    }

    public function setParentId($value): self
    {
        $this->parent_id = $value;

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
}
