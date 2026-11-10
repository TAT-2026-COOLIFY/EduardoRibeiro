<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(Request $request)
    {
        $setting = $request->user()->setting;

        return response()->json(['settings' => $setting?->settings ?? []]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['settings' => ['required', 'array']]);
        $setting = UserSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['settings' => $data['settings']]
        );

        return response()->json(['settings' => $setting->settings]);
    }
}
