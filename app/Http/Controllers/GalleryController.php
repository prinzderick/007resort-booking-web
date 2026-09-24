<?php

namespace App\Http\Controllers;

use App\Services\Online\ContentService;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function __construct(private readonly ContentService $content) {}

    public function index(Request $request)
    {
        $albums = $this->content->albums();
        $photos = [];
        foreach ($albums as $a) {
            $full = $this->content->album($a['slug']);
            foreach ((array) ($full['items'] ?? []) as $i) {
                $photos[] = $i + ['album' => $a['slug'], 'albumTitle' => $a['title']];
            }
        }

        $active = (string) $request->query('album', '');
        $active = in_array($active, array_column($albums, 'slug'), true) ? $active : '';

        return view('gallery', ['page' => $this->content->page('gallery'), 'albums' => $albums, 'photos' => $photos, 'active' => $active]);
    }
}
