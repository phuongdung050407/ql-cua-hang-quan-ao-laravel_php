<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct(
        private readonly ProductRecommendationService $recommendationService
    ) {}

    public function index(Request $request)
    {
        $recommendedProducts = $this->recommendationService->getRecommendations(
            Auth::user(),
            $request->session()->getId(),
            8
        );

        $newProducts = Product::active()
            ->with(['category', 'variants'])
            ->latest()
            ->take(8)
            ->get();

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return view('storefront.home', compact('recommendedProducts', 'newProducts', 'categories'));
    }
}