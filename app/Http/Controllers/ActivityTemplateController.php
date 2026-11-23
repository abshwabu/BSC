<?php

namespace App\Http\Controllers;

use App\Models\ActivityTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityTemplateController extends Controller
{
    /**
     * Display a listing of templates, or return JSON for wizard insertion.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $templates = ActivityTemplate::query()
            ->when($request->query('position_key'), function ($query, $key) {
                if ($key !== 'all') {
                    $query->where('position_key', $key);
                }
            })
            ->orderBy('position_key')
            ->orderBy('sort_order')
            ->get();

        if ($request->wantsJson() || $request->query('json')) {
            return response()->json($templates);
        }

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'position_key' => 'required|string|in:sales,marketing,tech,custom',
            'title' => 'required|string|max:255',
            'activity_text' => 'required|string',
            'kpi_text' => 'required|string',
            'default_target_value' => 'nullable|numeric',
            'default_target_label' => 'nullable|string|max:255',
            'direction' => 'required|string|in:higher_better,lower_better',
            'perspective' => 'required|string|in:financial,customers,internal,learning',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? (ActivityTemplate::where('position_key', $validated['position_key'])->max('sort_order') + 1);

        $template = ActivityTemplate::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'ቴምፕሌቱ በተሳካ ሁኔታ ተፈጥሯል!',
                'template' => $template,
            ]);
        }

        return redirect()->back()->with('success', 'ቴምፕሌቱ በተሳካ ሁኔታ ተፈጥሯል!');
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, ActivityTemplate $template): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'position_key' => 'required|string|in:sales,marketing,tech,custom',
            'title' => 'required|string|max:255',
            'activity_text' => 'required|string',
            'kpi_text' => 'required|string',
            'default_target_value' => 'nullable|numeric',
            'default_target_label' => 'nullable|string|max:255',
            'direction' => 'required|string|in:higher_better,lower_better',
            'perspective' => 'required|string|in:financial,customers,internal,learning',
            'sort_order' => 'nullable|integer',
        ]);

        $template->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'ቴምፕሌቱ በተሳካ ሁኔታ ተሻሽሏል!',
                'template' => $template,
            ]);
        }

        return redirect()->back()->with('success', 'ቴምፕሌቱ በተሳካ ሁኔታ ተሻሽሏል!');
    }

    /**
     * Remove the specified template from storage.
     */
    public function destroy(Request $request, ActivityTemplate $template): RedirectResponse|JsonResponse
    {
        $template->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'ቴምፕሌቱ ተሰርዟል!',
            ]);
        }

        return redirect()->back()->with('success', 'ቴምፕሌቱ በተሳካ ሁኔታ ተሰርዟል።');
    }
}
