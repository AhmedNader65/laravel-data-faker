<?php

namespace Webkul\DataFaker\Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\DataFaker\Database\Factories\Product\ProductAttributeValueFactory;
use Webkul\DataFaker\Database\Factories\Product\ProductFactory;
use Webkul\DataFaker\Database\Factories\Product\AttributeFactory;
use Webkul\Product\Models\ProductAttributeValue;
use Faker\Generator as Faker;
use Webkul\DataFaker\Database\Factories\Product\ProductFlatFactory;
use Webkul\DataFaker\Database\Factories\Product\ProductImageFactory;
use Webkul\DataFaker\Database\Factories\Product\ProductInventoryFactory;

class ProductTableDataSeeder extends Seeder
{
    protected $faker;

    public function __construct(Faker $faker)
    {
        $this->faker = $faker;
    }

    public function run()
    {
        $productFactory = new ProductFactory();
        $attributeValueFacroty = new ProductAttributeValueFactory();
        $attributeFacroty = new AttributeFactory();
        $inventory = new ProductInventoryFactory();
        $image = new ProductImageFactory();

        //seed fake products
        $productFactory
            ->count(1)
            ->has($inventory, 'inventories')
            ->has($image->count(4)->state(function (array $value, $product) {

                $imageData = $this->uploadImages($product['id']);
                return $imageData;

            }), 'images')
            ->has($attributeValueFacroty->state( function (array $value, $product) {

                $data = $this->withAttribute();

                $attributeValues = $data['attribute'];

                $productId = $product['id'];
                $data['data']['product_id'] = $productId;

                $productData = $data['data'];

                $attributeValueFacroty = new ProductAttributeValueFactory();

                foreach($attributeValues as $attributeValue) {
                    $attributeValue['product_id'] = $productId;

                    $attributeValueFacroty->state($attributeValue)->create();
                }

                $productFlat = new ProductFlatFactory();
                $productFlat->state($productData)->create();

                return null;
                // return ['product_id' => $productId];

            }), 'attribute_values')


            ->create();
    }

    public function withAttribute()
    {
        $fakeData = $this->getSimpleProductDummyData($this->faker, 'simple');

        $attributes = app('Webkul\Attribute\Repositories\AttributeRepository')->get();

        foreach ($attributes as $attribute) {

            if (! isset($fakeData[$attribute->code]) || (in_array($attribute->type, ['date', 'datetime']) && ! $fakeData[$attribute->code]))
                continue;

            if ($attribute->type == 'multiselect' || $attribute->type == 'checkbox') {
                $fakeData[$attribute->code] = implode(",", $fakeData[$attribute->code]);
            }

            if ($attribute->type == 'image' || $attribute->type == 'file') {
                $dir = 'product';
                if (gettype($fakeData[$attribute->code]) == 'object') {
                    $fakeData[$attribute->code] = request()->file($attribute->code)->store($dir);
                } else {
                    $fakeData[$attribute->code] = NULL;
                }
            }

            $attributeValue = [
                // 'product_id' => $product['product_id'],
                'attribute_id' => $attribute->id,
                'value' => $fakeData[$attribute->code],
                'channel' => $attribute->value_per_channel ? $fakeData['channel'] : null,
                'locale' => $attribute->value_per_locale ? $fakeData['locale'] : null
            ];

            $attributeValue[ProductAttributeValue::$attributeTypeFields[$attribute->type]] = $attributeValue['value'];

            unset($attributeValue['value']);
            // dd($attributeValue);
            // return $this->state($attributeValue);

            $value[] = $attributeValue;


        }

        return ['attribute' => $value, 'data' => $fakeData];
        // dd($attributeValue);
    }

    /**
     * Dummy Data For Simple Product
     *
     * @param $faker, $productType
     * @return array
     */
    public function getSimpleProductDummyData($faker, $productType)
    {
        $productName = $faker->userName;

        $sku = substr(strtolower(str_replace(array('a','e','i','o','u'), '', $productName)), 0, 6);

        $productSku = str_replace(' ', '', $sku) . "-". str_replace(' ', '', $sku) . "-" . rand(1,9999999) . "-" . rand(1,9999999);

        $price = $faker->numberBetween($min = 0, $max = 500);

        $specialPrice = rand('0', $faker->numberBetween($min = 0, $max = 500));

        if ($specialPrice == 0) {
            $max = $price;
            $min = $price;
        } else {
            $max = $specialPrice;
            $min = $specialPrice;
        }

        $localeCode = core()->getCurrentLocale()->code;

        $channelCode = core()->getCurrentChannel()->code;

        $productFaker = \Faker\Factory::create();

        $productFaker->addProvider(new \Bezhanov\Faker\Provider\Commerce($productFaker));

        $data = [
            'sku' => $productSku,
            'name' => $productFaker->productName,
            'url_key' => $faker->unique(true)->word . '-' . rand(1,9999999),
            'new' => 1,
            'featured' => 1,
            'visible_individually' => 1,
            'min_price' => $min,
            'max_price' => $max,
            'status' => 1,
            'color' => 1,
            'price' => $price,
            'special_price' => 0,
            'special_price_from' => null,
            'special_price_to' => null,
            'width' => $faker->randomNumber(2),
            'height' => $faker->randomNumber(2),
            'depth' => $faker->randomNumber(2),
            'meta_title' => '',
            'meta_keywords' => '',
            'meta_description' => '',
            'weight' => $faker->randomNumber(2),
            'color_label' => $faker->colorName,
            'size' => 6,
            'size_label' => 'S',
            'short_description' => '<p>' . $faker->paragraph . '</p>',
            'description' => '<p>' . $faker->paragraph . '</p>',
            'channel' => $channelCode,
            'locale' => $localeCode,
        ];

        return $data;
    }
}