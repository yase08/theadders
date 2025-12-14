<?php

namespace App\Repositories;

use App\Interfaces\InboxOnboardingInterface;
use App\Models\InboxOnboarding;

class InboxOnboardingRepository implements InboxOnboardingInterface
{
    public function getAllSlides()
    {
        return InboxOnboarding::where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    public function getSlideById($id)
    {
        return InboxOnboarding::where('is_active', 1)->find($id);
    }
}
