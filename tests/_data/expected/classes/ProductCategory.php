<?php

declare(strict_types=1);

namespace generated\classes;

class ProductCategory
{
    protected $product_id = null;
    protected $category_id = null;
    protected $assigned_at = null;

    public function getProductId()
    {
        return $this->product_id;
    }

    public function setProductId($value): self
    {
        $this->product_id = $value;

        return $this;
    }

    public function getCategoryId()
    {
        return $this->category_id;
    }

    public function setCategoryId($value): self
    {
        $this->category_id = $value;

        return $this;
    }

    public function getAssignedAt()
    {
        return $this->assigned_at;
    }

    public function setAssignedAt($value): self
    {
        $this->assigned_at = $value;

        return $this;
    }
}
