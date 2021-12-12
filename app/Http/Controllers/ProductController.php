<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductController extends Controller
{

    public function index()
    {
        $products = Product::query()->with(['productVariantPrices' => function($q) {
            $q->with('productVariantOne')
            ->with('productVariantTwo')
            ->with('productVariantThree');
         }]);

        if(request()->get('title')) {
            $products = $products->where('title', 'like', '%' . request()->get('title') . '%');
        }

        if(request()->get('variant')) {
            $variant = request()->get('variant');
            $products = $products->whereHas('productVariantPrices', function($pvp) use ($variant){
                    $pvp->whereHas('productVariantOne', function ($pvo) use ($variant) {
                        $pvo->where('variant',$variant);
                    })->orwhereHas('productVariantTwo', function ($pvt) use ($variant) {
                        $pvt->where('variant',$variant);
                    })->orwhereHas('productVariantThree', function ($pvth) use ($variant) {
                        $pvth->where('variant',$variant);
                    });
                });
        }

        if(request()->get('date')) {
            $date = \Carbon\Carbon::parse(request()->get('date'))->format('Y-m-d');
            $products = $products->whereDate('created_at',$date);
        }

        if(request()->get('price_from') && request()->get('price_to')) {
            $from = (int)request()->get('price_from');
            $to = (int)request()->get('price_to');
            $products = $products->whereHas('productVariantPrices', function ($q) use ($from,$to){
                $q->whereBetween('price',[$from,$to]);
            });
        }

        $lists = [];
        $products = $products->paginate(2);
        $variants = ProductVariant::query()->with('variants')->groupBy('variant')->get();

        if(count($variants) > 0) {
            foreach ($variants as $key => $value) {
                if(array_key_exists($value['variants']['title'],$lists)) {
                    $lists[$value['variants']['title']][] = $value['variant'];
                } else {
                    $lists[$value['variants']['title']] = [$value['variant']];
                }
            }
        }

        return view('products.index', compact('products','lists'));
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
     *  Create product
     * @param ProductRequest $request
     * @return array|\Exception
     */
    public function store(ProductRequest $request)
    {
        $inputs = $request->validated();


        try {
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
                    'product_variant_two' => $variants[1] ? ProductVariant::query()->where('variant', $variants[1])->where('product_id',$product->id)->first()->id : null,
                    'product_variant_three' => $variants[2] ? ProductVariant::query()->where('variant', $variants[2])->where('product_id',$product->id)->first()->id : null,
                    'product_id' => $product->id,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]);
            }

            $product_variant_prices = ProductVariantPrice::query()->insert($product_variant_prices);

            return [
                'message' => 'Successfully created',
                'status' => true,
            ];

        } catch (\Exception $e) {
            return $e;
        }

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


    public function edit(Product $product)
    {
        $variants = Variant::all();
        $productVariant = $product->productVariant;
        $productVariantPrices = $product->productVariantPrices;
        return view('products.edit', compact('variants','product','productVariant','productVariantPrices'));
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
