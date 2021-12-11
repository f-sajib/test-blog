<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Carbon\Carbon;
use http\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use function GuzzleHttp\Psr7\str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index()
    {
        return view('products.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        $variants = Variant::all();
        return view('products.create', compact('variants'));
    }


    /**
     * Create product
     * @param ProductRequest $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(ProductRequest $request)
    {
        $inputs = $request->validated();

        $product_data = array_merge(Arr::only($inputs, ['title', 'description']), [
            'sku' => $inputs['sku'] ?? Str::slug($inputs['title'])
        ]);

        $product = Product::query()->create($product_data);

        $product_variants = [];
        $product_variant_prices = [];

        foreach ($inputs['product_variant'] as $value) {
            foreach ($value['tags'] as $tag) {
                array_push($product_variants, [
                    'variant_id' => $value['option'],
                    'product_id' => $product->id,
                    'variant' => $tag,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]);
            }
        }

        $product_variants = ProductVariant::query()->insert($product_variants);

        foreach ($inputs['product_variant_prices'] as $value) {
            $variants = explode('/', $value['title']);
            array_push($product_variant_prices, [
                'price' => $value['price'],
                'stock' => $value['stock'],
                'product_variant_one' => ProductVariant::query()->where('variant', $variants[0])->where('product_id',$product->id)->first()->id,
                'product_variant_two' => ProductVariant::query()->where('variant', $variants[1])->where('product_id',$product->id)->first()->id,
                'product_variant_three' => $variants[2] ? ProductVariant::query()->where('variant', $variants[2])->where('product_id',$product->id)->first()->id : null,
                'product_id' => $product->id,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);
        }

        $product_variant_prices = ProductVariantPrice::query()->insert($product_variant_prices);

        return redirect('/product');
//        return [
//            'product' => $product,
//            '$product_variants' => $product_variants,
//            '$product_variant_prices' => $product_variant_prices,
//
//        ];
    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show($product)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $variants = Variant::all();
        return view('products.edit', compact('variants'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        //
    }
}
