<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfigurationImageController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimetypes:image/*|max:3072',
        ]);

        $extension = $request->file('image')->getClientOriginalExtension();

        $imageName = 'default_image.' . $extension;

        $request->file('image')->storeAs('public/defaults', $imageName);

        Log::info('Subiendo imagen por tenant: ' . DB::connection()->getDatabaseName());

        DB::connection('tenant')->table('configurations')->update([
            'product_default_image' => $imageName,
        ]);

        return response()->json([
            'message' => 'Imagen subida correctamente.',
            'file' => $imageName,
        ]);
    }
}
