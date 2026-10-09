<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');

        return (new JsonResource($settings))->response();
    }

    public function store(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        if (isset($data['company.timezone']) && in_array($data['company.timezone'], timezone_identifiers_list(), true)) {
            config(['app.timezone' => $data['company.timezone']]);
            date_default_timezone_set($data['company.timezone']);
        }

        $settings = Setting::all()->pluck('value', 'key');

        return (new JsonResource($settings))->response();
    }
}
