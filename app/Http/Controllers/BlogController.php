<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsClient;
use App\Services\Cms\CmsUnavailableException;
use App\Services\Online\ContentService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function __construct(private readonly CmsClient $cms, private readonly ContentService $content) {}

    public function index(Request $request)
    {
        $cat = (string) $request->query('category', '');
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $categories = [];
        try {
            $categories = $this->cms->postCategories();
        } catch (CmsUnavailableException) {
        }
        $slugs = array_column($categories, 'slug');
        $cat = in_array($cat, $slugs, true) ? $cat : '';

        $posts = $this->content->allPosts(array_filter(['category' => $cat, 'q' => $q]));
        $featured = ($page === 1 && $cat === '' && $q === '') ? (collect($posts)->firstWhere('featured', true) ?? ($posts[0] ?? null)) : null;
        $list = $featured ? array_values(array_filter($posts, fn ($p) => $p['slug'] !== $featured['slug'])) : $posts;
        $pages = max(1, (int) ceil(count($list) / self::PER_PAGE));
        $page = min($page, $pages);

        return view('blog.index', [
            'page' => $this->content->page('blog'),
            'featured' => $featured,
            'posts' => array_slice($list, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'categories' => $categories, 'cat' => $cat, 'q' => $q, 'current' => $page, 'pages' => $pages, 'total' => count($posts),
        ]);
    }

    public function show(string $slug)
    {
        $post = $this->cms->post($slug);
        abort_if($post === null, 404);

        return view('blog.show', ['post' => $post, 'related' => $post['related'] ?? [], 'events' => $this->content->events(['limit' => 3])]);
    }
}
