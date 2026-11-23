<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Bsc\ScoreCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('authenticated user can view settings page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.index'));
    $response->assertOk();
});

test('authenticated user can update settings and upload company logo', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $logo = UploadedFile::fake()->image('company_logo.png', 200, 200);

    $response = $this->actingAs($user)->post(route('settings.update'), [
        'company_name' => 'EthioInnovate PLC',
        'cap_percentage' => 115.0,
        'default_language' => 'am',
        'threshold_outstanding' => 92.0,
        'threshold_exceeds' => 78.0,
        'threshold_meets' => 62.0,
        'logo' => $logo,
    ]);

    $response->assertRedirect();

    expect(Setting::get('company_name'))->toBe('EthioInnovate PLC')
        ->and(Setting::get('cap_percentage'))->toEqual(115.0)
        ->and(Setting::get('threshold_outstanding'))->toEqual(92.0)
        ->and(ScoreCalculator::getDefaultCap())->toEqual(115.0);

    $savedLogo = Setting::get('company_logo');
    expect($savedLogo)->not->toBeNull();
});

test('score calculator respects dynamic cap setting', function () {
    Setting::set('cap_percentage', 110.0);

    expect(ScoreCalculator::getDefaultCap())->toBe(110.0);

    // Target 100, actual 150 -> 150% -> capped at 110%
    $pct = ScoreCalculator::calculateAchievementPercentage(100.0, 150.0, 'higher_better');
    expect($pct)->toBe(110.0);

    // Reset setting
    Setting::set('cap_percentage', 120.0);
    $pct2 = ScoreCalculator::calculateAchievementPercentage(100.0, 150.0, 'higher_better');
    expect($pct2)->toBe(120.0);
});
