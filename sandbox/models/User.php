<?php
namespace app\models;

/**
 * Базовый пользователь (одна таблица с дискриминатором type)
 */
class User extends BaseModel
{
    public static function tableName()
    {
        return 'user';
    }

    public function rules()
    {
        return [
            [['username', 'password_hash', 'type'], 'required'],
            ['username', 'string', 'max' => 50],
            ['password_hash', 'string', 'max' => 255],
            ['type', 'string', 'max' => 20],
            ['username', 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'password_hash' => 'Password',
            'type' => 'User Type',
            'created_at' => 'Created',
        ];
    }
}
