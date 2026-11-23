<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Display the settings configuration page.
     */
    public function index(): Response
    {
        $settings = [
            'company_name' => Setting::get('company_name', 'EthioTech Solutions'),
            'company_logo' => Setting::get('company_logo', null),
            'cap_percentage' => Setting::get('cap_percentage', 120.0),
            'default_language' => Setting::get('default_language', 'am'),
            'threshold_outstanding' => Setting::get('threshold_outstanding', 90.0),
            'threshold_exceeds' => Setting::get('threshold_exceeds', 75.0),
            'threshold_meets' => Setting::get('threshold_meets', 60.0),
        ];

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'cap_percentage' => 'required|numeric|min:50|max:200',
            'default_language' => 'required|string|in:am,en',
            'threshold_outstanding' => 'nullable|numeric|min:80|max:100',
            'threshold_exceeds' => 'nullable|numeric|min:60|max:89',
            'threshold_meets' => 'nullable|numeric|min:40|max:74',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ]);

        Setting::set('company_name', $validated['company_name']);
        Setting::set('cap_percentage', (float) $validated['cap_percentage']);
        Setting::set('default_language', $validated['default_language']);

        if (isset($validated['threshold_outstanding'])) {
            Setting::set('threshold_outstanding', (float) $validated['threshold_outstanding']);
        }
        if (isset($validated['threshold_exceeds'])) {
            Setting::set('threshold_exceeds', (float) $validated['threshold_exceeds']);
        }
        if (isset($validated['threshold_meets'])) {
            Setting::set('threshold_meets', (float) $validated['threshold_meets']);
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = 'logo_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('logos', $filename, 'public');
            Setting::set('company_logo', Storage::url($path));
        } elseif ($request->boolean('remove_logo')) {
            Setting::set('company_logo', null);
        }

        return redirect()->back()->with('success', 'ቅንብሮች በተሳካ ሁኔታ ተቀምጠዋል! (Settings saved successfully)');
    }
}
