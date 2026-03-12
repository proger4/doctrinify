<?php

declare(strict_types=1);

namespace generated\classes;

class Product
{
    protected $id = null;
    protected $sku = null;
    protected $name = null;
    protected $price = null;
    protected $stock = null;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value): self
    {
        $this->id = $value;

        return $this;
    }

    public function getSku()
    {
        return $this->sku;
    }

    public function setSku($value): self
    {
        $this->sku = $value;

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

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($value): self
    {
        $this->price = $value;

        return $this;
    }

    public function getStock()
    {
        return $this->stock;
    }

    public function setStock($value): self
    {
        $this->stock = $value;

        return $this;
    }
}
