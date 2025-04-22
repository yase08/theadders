<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\ProductIndexRequest;
use App\Interfaces\ProductCategoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\TradeRequest;

class ProductController extends Controller
{
    private ProductCategoryInterface $productCategoryInterface;

    public function __construct(ProductCategoryInterface $productCategoryInterface)
    {
        $this->productCategoryInterface = $productCategoryInterface;
    }

    public function storeProduct(ProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Handle thumbnail upload
            if ($request->hasFile('thumbail')) {
                $validatedData['thumbail'] = $request->file('thumbail')->store('product_images', 'public');
            }

            // Handle multiple product images
            if ($request->hasFile('product_images')) {
                $productImages = [];
                foreach ($request->file('product_images') as $image) {
                    $filename = $image->store('product_images', 'public');
                    $productImages[] = $filename;
                }
                $validatedData['product_images'] = $productImages;
            }

            $product = $this->productCategoryInterface->storeProduct($validatedData);

            DB::commit();
            return response()->json([
                'message' => 'success',
                'product' => $product
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'error',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function index(ProductIndexRequest $request)
    {
        try {
            $validatedData = $request->validated();

            $products = $this->productCategoryInterface->getProducts($validatedData);
            return response()->json([
                'message' => 'success',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function myProducts(ProductIndexRequest $request)
    {
        try {
            $products = $this->productCategoryInterface->getUserProducts($request->validated());

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = $this->productCategoryInterface->getProductDetail($id);
            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function tradeHistory(TradeRequest $request)
    {
        try {
            $filters = $request->validated();
            $products = $this->productCategoryInterface->getUserTradeHistory($filters);
            
            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->productCategoryInterface->deleteProduct($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(ProductRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Handle thumbnail upload
            if ($request->hasFile('thumbail')) {
                $validatedData['thumbail'] = $request->file('thumbail')->store('product_images', 'public');
            }

            // Handle multiple product images
            if ($request->hasFile('product_images')) {
                $productImages = [];
                foreach ($request->file('product_images') as $image) {
                    $filename = $image->store('product_images', 'public');
                    $productImages[] = $filename;
                }
                $validatedData['product_images'] = $productImages;
            }

            $product = $this->productCategoryInterface->updateProduct($id, $validatedData);

            DB::commit();
            return response()->json([
                'message' => 'success',
                'product' => $product
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
