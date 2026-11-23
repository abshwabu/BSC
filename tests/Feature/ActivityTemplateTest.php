<?php

use App\Models\ActivityTemplate;
use App\Models\User;

test('activity templates index can be rendered and returns templates', function () {
    $user = User::factory()->create();

    $template = ActivityTemplate::create([
        'position_key' => 'sales',
        'title' => 'የደንበኞች ማሳያ (Product Demos)',
        'activity_text' => 'የምርት ማሳያዎችን ለደንበኞች ማቅረብ',
        'kpi_text' => 'የተካሄዱ ማሳያዎች ብዛት',
        'default_target_value' => 60,
        'default_target_label' => 'ማሳያዎች',
        'direction' => 'higher_better',
        'perspective' => 'customers',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->get(route('templates.index'));
    $response->assertOk();

    // JSON API endpoint
    $jsonResponse = $this->actingAs($user)->getJson(route('activity-templates.index'));
    $jsonResponse->assertOk();
    $jsonResponse->assertJsonFragment([
        'title' => 'የደንበኞች ማሳያ (Product Demos)',
    ]);
});

test('user can create an activity template', function () {
    $user = User::factory()->create();

    $payload = [
        'position_key' => 'marketing',
        'title' => 'ይዘት ማዘጋጀት (Content Production)',
        'activity_text' => 'ሳምንታዊ ጽሑፎችንና ቪዲዮዎችን ማዘጋጀት',
        'kpi_text' => 'የታተሙ ይዘቶች',
        'default_target_value' => 24,
        'default_target_label' => 'ይዘቶች',
        'direction' => 'higher_better',
        'perspective' => 'internal',
    ];

    $response = $this->actingAs($user)->post(route('templates.store'), $payload);
    $response->assertRedirect();

    $this->assertDatabaseHas('activity_templates', [
        'title' => 'ይዘት ማዘጋጀት (Content Production)',
        'position_key' => 'marketing',
        'default_target_value' => 24,
    ]);
});

test('user can update an activity template', function () {
    $user = User::factory()->create();

    $template = ActivityTemplate::create([
        'position_key' => 'tech',
        'title' => 'የሲስተም ዝግጁነት (System Uptime)',
        'activity_text' => 'የሰርቨር ዝግጁነትን መጠበቅ',
        'kpi_text' => 'የሰርቨር ዝግጁነት %',
        'default_target_value' => 99.5,
        'default_target_label' => '%',
        'direction' => 'higher_better',
        'perspective' => 'internal',
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->put(route('templates.update', $template->id), [
        'position_key' => 'tech',
        'title' => 'የሲስተም ዝግጁነት (High Availability)',
        'activity_text' => 'የሰርቨር ዝግጁነትን መጠበቅ',
        'kpi_text' => 'የሰርቨር ዝግጁነት %',
        'default_target_value' => 99.9,
        'default_target_label' => '%',
        'direction' => 'higher_better',
        'perspective' => 'internal',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('activity_templates', [
        'id' => $template->id,
        'title' => 'የሲስተም ዝግጁነት (High Availability)',
        'default_target_value' => 99.9,
    ]);
});

test('user can delete an activity template', function () {
    $user = User::factory()->create();

    $template = ActivityTemplate::create([
        'position_key' => 'sales',
        'title' => 'ለመሰረዝ የታሰበ ቴምፕሌት',
        'activity_text' => 'የሽያጭ ማስታወሻ',
        'kpi_text' => 'ቁጥር',
        'default_target_value' => 10,
        'direction' => 'higher_better',
        'perspective' => 'financial',
    ]);

    $response = $this->actingAs($user)->delete(route('templates.destroy', $template->id));
    $response->assertRedirect();

    $this->assertDatabaseMissing('activity_templates', [
        'id' => $template->id,
    ]);
});
