<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportProductRequest;
use App\Http\Requests\ReportUserRequest;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{

    public function getProductReportReasons()
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


    public function getUserReportReasons()
    {
        $reasons = [
            ['value' => 'harassment',           'label' => 'Harassment / Intimidation'],
            ['value' => 'hate_speech',          'label' => 'Hate Speech'],
            ['value' => 'scam',                 'label' => 'Scam'],
            ['value' => 'impersonation',        'label' => 'Impersonation'],
            ['value' => 'inappropriate_content','label' => 'Inappropriate Content'],
            ['value' => 'spam',                 'label' => 'Spam'],
            ['value' => 'other',                'label' => 'Other'],
        ];

        return response()->json([
            'success' => true,
            'data'    => $reasons,
        ]);
    }

    public function reportUser(ReportUserRequest $request, $userId)
    {
        try {
            $reportedUser = User::find($userId);

            if (!$reportedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $reporterId = Auth::id();

            if ($reporterId == $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot report yourself.',
                ], 403);
            }

            $alreadyReported = UserReport::where('reported_user_id', $userId)
                ->where('reporter_id', $reporterId)
                ->exists();

            if ($alreadyReported) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this user.',
                ], 409);
            }

            UserReport::create([
                'reported_user_id' => $userId,
                'reporter_id'      => $reporterId,
                'reason'           => $request->reason,
                'description'      => $request->description ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User reported successfully.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to report user: ' . $e->getMessage(),
            ], 500);
        }
    }
}
