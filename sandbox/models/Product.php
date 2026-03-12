<?php
namespace app\models;

/**
 * Товар
 */
class Product extends BaseModel
{
    public static function tableName()
    {
        return 'product';
    }

    public function rules()
    {
        return [
            [['sku', 'name', 'price'], 'required'],
            ['sku', 'string', 'max' => 50],
            ['name', 'string', 'max' => 255],
            ['price', 'number'],
            ['stock', 'integer'],
            ['sku', 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'Product ID',
            'sku' => 'SKU',
            'name' => 'Product Name',
            'price' => 'Price',
            'stock' => 'Stock',
        ];
    }

    public function getOrderItems()
    {
        return $this->hasMany(OrderItem::class, ['product_id' => 'id']);
    }

    public function getProductCategories()
    {
        return $this->hasMany(ProductCategory::class, ['product_id' => 'id']);
    }

    public function getCategories()
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])->via('productCategories');
    }

    public function getCategoriesWithSql()
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])
            ->via('productCategories')
            ->where(['status' => 'active'])
            ->andWhere(['condition' => 'pc.deleted_at IS NULL'])
            ->orderBy(['name' => SORT_ASC])
            ->addOrderBy(['order' => 'pc.priority DESC'])
            ->joinWith(['productCategories pc'], true, 'LEFT JOIN')
            ->andWhere(['joinType' => 'LEFT JOIN']);
    }
}
