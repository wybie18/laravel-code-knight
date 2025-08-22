<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    public function upload(Request $request){
        $validated = $request->validate([
            'image' => 'image|required|max:2048'
        ]);
        $validated['image'] = $request->file('image')->store('lessons', 'public');

        return response()->json([
            'success' => true,
            'url' => url(Storage::url($validated['image']))
        ]);
    }
}
