<?php
namespace app\models;

/**
 * Позиция заказа
 */
class OrderItem extends BaseModel
{
    public static function tableName()
    {
        return 'order_item';
    }

    public function rules()
    {
        return [
            [['order_id', 'customer_id', 'product_id', 'quantity', 'price'], 'required'],
            [['order_id', 'customer_id', 'product_id', 'quantity'], 'integer'],
            ['price', 'number'],
            ['quantity', 'integer', 'min' => 1],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'Item ID',
            'order_id' => 'Order',
            'customer_id' => 'Customer',
            'product_id' => 'Product',
            'quantity' => 'Qty',
            'price' => 'Price',
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(Order::class, ['order_id' => 'order_id', 'customer_id' => 'customer_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }
}
