<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\ProductIndexRequest;
use App\Interfaces\ProductCategoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private ProductCategoryInterface $productCategoryRepository;

    public function __construct(ProductCategoryInterface $userRepositoryInterface)
    {
        $this->productCategoryRepository = $userRepositoryInterface;
    }

    public function storeProduct(ProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            if ($request->hasFile('thumbail')) {
                $validatedData['thumbail'] = $request->file('thumbail')->store('product_images', 'public');
            }

            $product = $this->productCategoryRepository->storeProduct($validatedData);

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

            $products = $this->productCategoryRepository->getProducts($validatedData);
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
            $products = $this->productCategoryRepository->getUserProducts($request->validated());

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
            $product = $this->productCategoryRepository->getProductDetail($id);
            return ApiResponseClass::sendResponse($product, 'success', 200);
        } catch (\Exception $e) {
            return ApiResponseClass::sendResponse(null, $e->getMessage(), 404);
        }
    }
}
