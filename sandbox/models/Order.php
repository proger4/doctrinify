<?php
namespace app\models;

/**
 * Модель заказа с композитным первичным ключом (order_id, customer_id)
 */
class Order extends BaseModel
{
    public static function tableName()
    {
        return 'order';
    }

    public function rules()
    {
        return [
            [['order_id', 'customer_id', 'order_date'], 'required'],
            [['order_id', 'customer_id'], 'integer'],
            ['order_date', 'date', 'format' => 'php:Y-m-d'],
            ['status', 'string', 'max' => 20],
            [['order_id', 'customer_id'], 'unique', 'targetAttribute' => ['order_id', 'customer_id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'order_id' => 'Order #',
            'customer_id' => 'Customer',
            'order_date' => 'Date',
            'status' => 'Status',
        ];
    }

    public function getCustomer()
    {
        // belongsTo
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getItems()
    {
        // hasMany
        return $this->hasMany(OrderItem::class, ['order_id' => 'order_id']);
    }

    public function getItemsWithCondition()
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'order_id'])
            ->onCondition(['condition' => 'order_item.price > 0'])
            ->addOrderBy(['order_id' => SORT_DESC]);
    }
}
