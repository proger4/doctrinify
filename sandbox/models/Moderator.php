<?php
namespace app\models;

/**
 * Модератор - наследник User в той же таблице user
 */
class Moderator extends User
{
    public static function tableName()
    {
        return 'user';
    }

    public static function find()
    {
        return parent::find()->where(['type' => 'moderator']);
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            $this->type = 'moderator';
            return true;
        }

        return false;
    }
}
