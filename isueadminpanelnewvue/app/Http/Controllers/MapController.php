<?php

namespace App\Http\Controllers;

use App\Models\Map;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Log;

class MapController extends Controller
{
    public function index()
    {
        $maps = Map::with('buildings')->get();
        // Add image_url to each map
        $maps->each(function ($map) {
            $map->image_url = asset('storage/' . $map->image_path);
        });
        return $maps;
    }

    public function store(Request $request)
    {
        Log::info('Map store request received', [
            'has_file' => $request->hasFile('image'),
            'file_size' => $request->hasFile('image') ? $request->file('image')->getSize() : null,
            'file_mime' => $request->hasFile('image') ? $request->file('image')->getMimeType() : null,
            'request_data' => $request->except(['image'])
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|max:102400', // Increased to 100MB to match PHP configuration
            'width' => 'required|numeric',
            'height' => 'required|numeric',
            'is_published' => 'nullable|in:true,false,1,0'
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            try {
                $imagePath = $request->file('image')->store('maps', 'public');
                Log::info('Image stored successfully', ['path' => $imagePath]);
            } catch (\Exception $e) {
                Log::error('Image storage failed', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                return response()->json([
                    'message' => 'The image failed to upload.',
                    'errors' => ['image' => [$e->getMessage()]]
                ], 422);
            }
        }

        $map = Map::create([
            'name' => $request->name,
            'image_path' => $imagePath,
            'width' => $request->width,
            'height' => $request->height,
            'is_active' => false, // New maps are inactive by default
            'is_published' => filter_var($request->input('is_published', false), FILTER_VALIDATE_BOOLEAN) // Default to unpublished
        ]);

        $map->image_url = $imagePath ? asset('storage/' . $map->image_path) : null;
        
        return response()->json($map, 201);
    }

    public function show(Map $map)
    {
        $map->load('buildings');
        $map->image_url = asset('storage/' . $map->image_path);
        return response()->json($map);
    }

    public function update(Request $request, Map $map)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'image' => 'sometimes|required|image|max:10240',
            'width' => 'sometimes|required|integer',
            'height' => 'sometimes|required|integer',
            'is_active' => 'sometimes|required|boolean'
        ]);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($map->image_path) {
                Storage::disk('public')->delete($map->image_path);
            }
            $imagePath = $request->file('image')->store('maps', 'public');
            $map->image_path = $imagePath;
        }

        $map->fill($request->except('image'));
        $map->save();

        $map->image_url = asset('storage/' . $map->image_path);
        return response()->json($map);
    }

    public function destroy(Map $map)
    {
        if ($map->image_path) {
            Storage::disk('public')->delete($map->image_path);
        }
        $map->delete();
        return response()->json(null, 204);
    }

    public function activate(Map $map)
    {
        // Deactivate all other maps
        Map::where('id', '!=', $map->id)->update(['is_active' => false]);
        
        // Activate the selected map
        $map->is_active = true;
        $map->save();

        $map->image_url = asset('storage/' . $map->image_path);
        return response()->json($map);
    }

    public function getActive()
    {
        $map = Map::where('is_active', true)->with('buildings')->first();
        if (!$map) {
            return response()->json(['message' => 'No active map found'], 404);
        }
        $map->image_url = asset('storage/' . $map->image_path);
        return response()->json($map);
    }

    public function upload(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg|max:10240' // 10MB max
            ]);

            $map = Map::where('is_active', true)->first();
            if (!$map) {
                return response()->json(['message' => 'No active map found'], 404);
            }

            // Get image dimensions
            $image = Image::make($request->file('image'));
            $width = $image->width();
            $height = $image->height();

            // Delete old image if exists
            if ($map->image_path) {
                Storage::disk('public')->delete($map->image_path);
            }

            // Store new image
            $imagePath = $request->file('image')->store('maps', 'public');
            
            // Update map record
            $map->update([
                'image_path' => $imagePath,
                'width' => $width,
                'height' => $height
            ]);

            $map->image_url = asset('storage/' . $map->image_path);
            
            return response()->json([
                'message' => 'Map image uploaded successfully',
                'map' => $map
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Map upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to upload map image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 