<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\HeroSlider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HeroSliderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $slides = HeroSlider::orderBy('sort_order', 'asc')->get();
        return response()->json($slides);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string|max:500',
            'badge' => 'nullable|string|max:100',
            'image_url' => 'required|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get the highest sort order if not provided
        $sortOrder = $request->input('sort_order');
        if ($sortOrder === null) {
            $maxSortOrder = HeroSlider::max('sort_order') ?? 0;
            $sortOrder = $maxSortOrder + 1;
        }

        $slide = HeroSlider::create([
            'title' => $request->input('title'),
            'subtitle' => $request->input('subtitle'),
            'badge' => $request->input('badge'),
            'image_url' => $request->input('image_url'),
            'sort_order' => $sortOrder,
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json($slide, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(HeroSlider $heroSlider)
    {
        return response()->json($heroSlider);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HeroSlider $heroSlider)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'sometimes|required|string|max:500',
            'badge' => 'nullable|string|max:100',
            'image_url' => 'sometimes|required|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $heroSlider->update($request->all());

        return response()->json($heroSlider);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HeroSlider $heroSlider)
    {
        $heroSlider->delete();
        return response()->json(null, 204);
    }

    /**
     * Get active slides for frontend display.
     */
    public function active()
    {
        $slides = HeroSlider::active()->ordered()->get();
        return response()->json($slides);
    }

    /**
     * Reorder slides.
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slides' => 'required|array',
            'slides.*.id' => 'required|exists:hero_sliders,id',
            'slides.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->input('slides') as $slideData) {
            HeroSlider::where('id', $slideData['id'])
                ->update(['sort_order' => $slideData['sort_order']]);
        }

        return response()->json(['message' => 'Slides reordered successfully']);
    }
}
