<?php
namespace app\models;

/**
 * Администратор – наследник User, использует ту же таблицу,
 * но с дискриминатором type='admin'. В Yii2 это может быть реализовано через
 * переопределение tableName и условий.
 */
class Admin extends User
{
    public static function tableName()
    {
        return 'user'; // та же таблица
    }

    public static function find()
    {
        // добавляем условие по умолчанию для администраторов
        return parent::find()->where(['type' => 'admin']);
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            $this->type = 'admin';
            return true;
        }
        return false;
    }
}
