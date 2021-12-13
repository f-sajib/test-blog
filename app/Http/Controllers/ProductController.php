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
            $product = $this->product($inputs);

            $this->productVariant($inputs,$product->id);

            $this->productVariantPrices($inputs,$product->id);

            return [
                'message' => 'Successfully created',
                'status' => true,
            ];

        } catch (\Exception $e) {
            return $e;
        }

    }


    public function edit(Product $product)
    {
        $variants = Variant::all();
        $productVariant = $product->productVariant;
        $productVariantPrices = $product->productVariantPrices;
        return view('products.edit', compact('variants','product','productVariant','productVariantPrices'));
    }


    public function update(ProductRequest $request, Product $product)
    {
        return $request->validated();
    }

    private function product($inputs,$create = true)
    {
        $product_data = array_merge(Arr::only($inputs, ['title', 'description']), [
            'sku' => $inputs['sku'] ?? Str::slug($inputs['title'])
        ]);

        return Product::query()->create($product_data);
    }

    private function productVariant($inputs, $productId, $create = true)
    {
        $product_variants = [];
        foreach ($inputs['product_variant'] as $value) {
            foreach ($value['tags'] as $tag) {
                array_push($product_variants, [
                    'variant_id' => $value['option'],
                    'product_id' => $productId,
                    'variant' => $tag,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]);
            }
        }

        return ProductVariant::query()->insert($product_variants);
    }

    private function productVariantPrices($inputs, $productId, $create = true)
    {
        $product_variant_prices = [];
        $variants = [];

        foreach ($inputs['product_variant_prices'] as $value) {
            $variant_price = explode('/', $value['title']);

            foreach ($variant_price as $value2) {
                $data =  ProductVariant::query()
                    ->where('variant', $value2)
                    ->where('product_id',$productId)->first();
                array_push($variants,[$data ? $data->id : null]);
            }

            array_push($product_variant_prices, [
                'price' => $value['price'],
                'stock' => $value['stock'],
                'product_variant_one' => $variants[0][0],
                'product_variant_two' => $variants[1][0],
                'product_variant_three' => $variants[2][0],
                'product_id' => $productId,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);
        }
        return ProductVariantPrice::query()->insert($product_variant_prices);
    }
}
