<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = Template::query()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name');

        if ($category = $request->query('category')) {
            $query->where(function ($q) use ($category) {
                $q->whereHas('category', function ($c) use ($category) {
                    $c->where('slug', $category)->orWhere('id', $category);
                });
            });
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show($idOrSlug)
    {
        $template = Template::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->with('category')
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Desain undangan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function categories()
    {
        return response()->json([
            'success' => true,
            'data' => Category::withCount('templates')->orderBy('name')->get(),
        ]);
    }
}
