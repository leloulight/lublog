<?php
namespace lublog\Http\Controllers;

use lublog\Http\Requests;
use lublog\Http\Controllers\Controller;
use Illuminate\Http\Request;
use lublog\Article;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Stmt\Catch_;

class PageController extends Controller
{

    /**
     * 用于博客主页文章的显示
     */
    public function welcome(Request $request)
    {
      
        $page = $request->input('page', 1);
        $key = "welcome:articles:" . $page;
        if (Cache::has($key)) {
            Log::info("从缓存中取得数据.");
            $articles = Cache::get($key);
        } else {
            Log::info("缓存中没有数据，从DB取得数据，并放入缓存中.");
            $articles = Article::orderBy('created_at', 'desc')->paginate();
            $expiresAt = Carbon::now()->addMinute(30);
            Cache::put($key, $articles, $expiresAt);
            Redis::command('rpush', [
                'welcome:articles:pages',
                $key
            ]);
        }
        return view('welcome')->with('articles', $articles);
    }

    /**
     * 留言板
     */
    public function mboard()
    {
        $article = Article::where('title', '=', '留言板')->first();
        return view('article_detailed')->with('article', $article);
    }

    /**
     * 关于
     */
    public function about()
    {
        $article = Article::where('title', '=', '关于')->first();
        return view('article_detailed')->with('article', $article);
    }

    /**
     * rss
     */
    public function feed()
    {
        $rss = \RSS::make();
        Log::info("开始构建RSS XML.");
        if (Cache::has('self:rss')) {
            
            Log::info("发现缓存的RSS XMLDOCUMENT。");
            $rss = Cache::get("self:rss");
            // make channel.
        } else {
            Log::info("没有发现缓存中存在RSS XMLDOCUMENT。开始构建。");
            $rss->channel([
                'title' => 'LuBlog',
                'description' => '蝼蚁虽小，也有梦想。',
                'link' => url('/')
            ])->withImage([
                
                'url' => asset('/images/avatar.jpg'),
                'title' => '头像',
                'link' => url('/')
            ]);
            $articles = Article::orderBy('created_at', 'desc')->take(20)->get();
            // gen posts data ......
            foreach ($articles as $article) {
                $rss->item([
                    'title' => $article->title,
                    'description|cdata' => $article->description,
                    'link' => url('/article/' . $article->id)
                ]);
            }
            $expries_at = Carbon::now()->addMinutes(30);
            Cache::put('self:rss', $rss, $expries_at);
        }
        
        // If you want to save the rss data to file.
        // $rss->save('rss.xml');
        
        // Or just make a response to the http request.
        return \Response::make($rss->render(), 200, [
            'Content-Type' => 'text/xml'
        ]);
    }
}
