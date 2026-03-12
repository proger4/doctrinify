<?php

declare(strict_types=1);

namespace generated\classes;

class OrderItem
{
    protected $id = null;
    protected $order_id = null;
    protected $customer_id = null;
    protected $product_id = null;
    protected $quantity = null;
    protected $price = null;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value): self
    {
        $this->id = $value;

        return $this;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($value): self
    {
        $this->order_id = $value;

        return $this;
    }

    public function getCustomerId()
    {
        return $this->customer_id;
    }

    public function setCustomerId($value): self
    {
        $this->customer_id = $value;

        return $this;
    }

    public function getProductId()
    {
        return $this->product_id;
    }

    public function setProductId($value): self
    {
        $this->product_id = $value;

        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($value): self
    {
        $this->quantity = $value;

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
}
