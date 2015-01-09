<?php

namespace Craft;


class Stripey_ProductService extends BaseApplicationComponent
{
    public function getProductById($id)
    {

        $product = Stripey_ProductRecord::model()->findById($id);

        return Stripey_ProductModel::populateModel($product);

    }

    public function deleteProduct($product)
    {
        $product = Stripey_ProductRecord::model()->findById($product->id);
        return $product->delete();
    }
}