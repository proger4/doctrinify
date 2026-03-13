<?php
namespace app\models;

/**
 * Категория с иерархией (parent/children)
 */
class Category extends BaseModel
{
    public static function tableName()
    {
        return 'category';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            ['parent_id', 'integer'],
            ['name', 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'Category ID',
            'parent_id' => 'Parent Category',
            'name' => 'Category Name',
        ];
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren()
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getProductCategories()
    {
        return $this->hasMany(ProductCategory::class, ['category_id' => 'id']);
    }

    public function getProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])->via('productCategories');
    }
}
