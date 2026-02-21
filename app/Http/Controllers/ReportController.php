<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportProductRequest;
use App\Models\Product;
use App\Models\ProductReport;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function getReportReasons()
    {
        $reasons = [
            ['value' => 'spam',           'label' => 'Spam'],
            ['value' => 'inappropriate',  'label' => 'Inappropriate Content'],
            ['value' => 'fake',           'label' => 'Fake Product / Scam'],
            ['value' => 'prohibited',     'label' => 'Prohibited Item'],
            ['value' => 'wrong_category', 'label' => 'Wrong Category'],
            ['value' => 'other',          'label' => 'Other'],
        ];

        return response()->json([
            'success' => true,
            'data'    => $reasons,
        ]);
    }

    public function reportProduct(ReportProductRequest $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $reporterId = Auth::id();

            if ($product->author === $reporterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot report your own product.',
                ], 403);
            }

            $alreadyReported = ProductReport::where('product_id', $id)
                ->where('reporter_id', $reporterId)
                ->exists();

            if ($alreadyReported) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this product.',
                ], 409);
            }

            ProductReport::create([
                'product_id'  => $id,
                'reporter_id' => $reporterId,
                'reason'      => $request->reason,
                'description' => $request->description ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product reported successfully.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to report product: ' . $e->getMessage(),
            ], 500);
        }
    }
}
