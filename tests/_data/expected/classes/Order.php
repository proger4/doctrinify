<?php

declare(strict_types=1);

namespace generated\classes;

class Order
{
    protected $order_id = null;
    protected $customer_id = null;
    protected $order_date = null;
    protected $status = null;

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

    public function getOrderDate()
    {
        return $this->order_date;
    }

    public function setOrderDate($value): self
    {
        $this->order_date = $value;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($value): self
    {
        $this->status = $value;

        return $this;
    }
}
