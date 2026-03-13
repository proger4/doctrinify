<?php
namespace app\models;

/**
 * Модель клиента (простая, одна таблица)
 */
class Customer extends BaseModel
{
    public static function tableName()
    {
        return 'customer';
    }

    public function rules()
    {
        return [
            [['name', 'email'], 'required'],
            ['email', 'email'],
            [['name'], 'string', 'max' => 255],
            [['email'], 'string', 'max' => 255],
            [['email'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Full Name',
            'email' => 'Email Address',
            'created_at' => 'Registered At',
        ];
    }

    public function getOrders()
    {
        // hasMany через внешний ключ customer_id в таблице order
        return $this->hasMany(Order::class, ['customer_id' => 'id']);
    }
}
