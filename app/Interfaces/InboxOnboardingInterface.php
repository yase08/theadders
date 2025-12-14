<?php

namespace App\Interfaces;

interface InboxOnboardingInterface
{
    public function getAllSlides();
    public function getSlideById($id);
}
