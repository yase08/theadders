<?php

namespace App\Http\Controllers;

use App\Interfaces\InboxOnboardingInterface;
use Illuminate\Http\Request;

class InboxOnboardingController extends Controller
{
    private InboxOnboardingInterface $inboxOnboardingRepository;

    public function __construct(InboxOnboardingInterface $inboxOnboardingRepository)
    {
        $this->inboxOnboardingRepository = $inboxOnboardingRepository;
    }

    public function getSlides()
    {
        try {
            $slides = $this->inboxOnboardingRepository->getAllSlides();
            return response()->json([
                'slides' => $slides,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function getSlide($id)
    {
        try {
            $slide = $this->inboxOnboardingRepository->getSlideById($id);
            if (!$slide) {
                return response()->json(['message' => 'Slide not found'], 404);
            }
            return response()->json([
                'slide' => $slide,
                'message' => 'success'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
