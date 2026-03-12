<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Абстрактный базовый класс, не имеющий своей таблицы.
 * Все модели наследуют его.
 */
abstract class BaseModel extends ActiveRecord
{
    // Общая логика, если есть
}
