<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    public function productVariantPrices() {
        return $this->hasMany(ProductVariantPrice::class);
    }

    public function productVariant() {
        return $this->hasMany(ProductVariant::class);
    }
}
