<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\ProductIndexRequest;
use App\Interfaces\ProductCategoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\FirebaseService;

use App\Http\Requests\TradeRequest;

class ProductController extends Controller
{
    private ProductCategoryInterface $productCategoryInterface;
    protected $firebaseService;


    public function __construct(ProductCategoryInterface $productCategoryInterface, FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
        $this->productCategoryInterface = $productCategoryInterface;
    }

    public function storeProduct(ProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            
            if ($request->hasFile('thumbail')) {
                $validatedData['thumbail'] = $request->file('thumbail')->store('product_images', 'public');
            }

            
            if ($request->hasFile('product_images')) {
                $productImages = [];
                foreach ($request->file('product_images') as $image) {
                    $filename = $image->store('product_images', 'public');
                    $productImages[] = $filename;
                }
                $validatedData['product_images'] = $productImages;
            }

            $product = $this->productCategoryInterface->storeProduct($validatedData);

            $this->firebaseService->updateLatestProductTimestamp();

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

    public function readNewProducts()
    {
        $this->firebaseService->clearNewProductStatus(auth()->id());
        return response()->json(['message' => 'success'], 200);
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
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateProductRequest $request, $id)
    {
        try {
            \Log::info('Product Update Request Data:', [
                'all' => $request->all(),
                'files' => $request->allFiles(),
                'validated' => $request->validated()
            ]);
            
            DB::beginTransaction();

            $validatedData = $request->validated();

            
            if ($request->hasFile('thumbail')) {
                $validatedData['thumbail'] = $request->file('thumbail')->store('product_images', 'public');
            } elseif (!isset($validatedData['thumbail'])) {
                unset($validatedData['thumbail']);
            }

            
            if ($request->hasFile('product_images')) {
                $productImages = [];
                foreach ($request->file('product_images') as $image) {
                    $filename = $image->store('product_images', 'public');
                    $productImages[] = $filename;
                }
                $validatedData['product_images'] = $productImages;
            } elseif (!isset($validatedData['product_images'])) {
                unset($validatedData['product_images']);
            }

            $product = $this->productCategoryInterface->updateProduct($id, $validatedData);

            DB::commit();
            return response()->json([
                'message' => 'success',
                'product' => $product
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Product Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
