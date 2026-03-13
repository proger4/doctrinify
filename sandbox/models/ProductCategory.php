<?php
namespace app\models;

/**
 * Промежуточная модель many-to-many product<->category
 */
class ProductCategory extends BaseModel
{
    public static function tableName()
    {
        return 'product_category';
    }

    public static function primaryKey()
    {
        return ['product_id', 'category_id'];
    }

    public function rules()
    {
        return [
            [['product_id', 'category_id'], 'required'],
            [['product_id', 'category_id'], 'integer'],
            [['product_id', 'category_id'], 'unique', 'targetAttribute' => ['product_id', 'category_id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'product_id' => 'Product',
            'category_id' => 'Category',
            'assigned_at' => 'Assigned At',
        ];
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getCategory()
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }
}
