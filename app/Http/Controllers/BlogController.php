<?php

namespace App\Http\Controllers;

use App\Blog;
use App\Config;
use App\Http\Resources\Blogs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class BlogController extends Controller
{
    public function blogs(Request $request)
    {
        $years = $this->getBlogYears();
        $year = is_numeric($request->year) ? $request->year : min($years);
        // 正常博客
        $normal = Blog::query()
            ->whereIn('status', [1, 3])
            ->select()
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'asc')
            ->offset(0)
            ->limit(100)
            ->get();
        // 异常博客
        $abnormal = Blog::query()
            ->where('status', 4)
            ->select()
            ->orderBy('created_at', 'asc')
            ->get();
        return view('layouts.blogs.list', compact('years', 'year', 'normal', 'abnormal'));
    }

    public function items(Request $request)
    {
        $years = $this->getBlogYears();
        $year = is_numeric($request->year) ? $request->year : min($years);
        $normal = Blog::query()
            ->whereIn('status', [1, 3])
            ->select()
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'asc')
            ->paginate(100);
        return Blogs::collection($normal);
    }

    public function blog(Request $request)
    {
        $id = $request->route('id');
        $field = is_numeric($id) ? 'id' : 'slug';
        $blog = Blog::where($field, $id)->whereIn('status', [1, 3, 4])->firstOrFail();
        $blog->increment('views');
        return view('layouts.blogs.detail', compact('blog'));
    }

    public function join(Request $request)
    {
        // 系统是否开启申请系统
        $config = Config::where('key', 'close_apply')->first();
        $closeApply = $config->value == 1 ? true : false;
        if ($request->isMethod('post')) {
            if ($closeApply) {
                return ['code' => 0, 'message' => '申请系统已被管理员关闭!'];
            }
            $postData = $request->all();

            $email = $request->input('email');
            $link = $request->input('link');
            /*if (Blog::where('email', $email)->where('status', '<>', 2)->count()) {
                return ['code' => 0, 'message' => '检测到系统已存在该邮箱, 同一个邮箱只允许申请一次!'];
            }*/
            if ($link) {
                if(!preg_match("/^http(s)?:\\/\\/.+/", $link)) {
                    // 补上协议头
                    $link = $postData['link'] = 'http://'.$link;
                }
            }

            $validator = Validator::make($postData, [
                'name' => 'required|min:2|max:20',
                'email' => 'required|email',
                'link' => 'required|url|max:50',
                'message' => 'required|min:2|max:300',
                'captcha' => 'required|captcha'
            ], [
                'link.required' => '网站地址不能为空',
                'link.url' => '网站地址格式不正确',
                'link.max' => '网站地址过长, 最大 50 个字符',
                'message.required' => '博主寄语不能为空',
                'message.min' => '博主寄语字符必须大于 2',
                'message.max' => '博主寄语字符必须小于 300',
                'captcha.required' => '验证码不能为空',
                'captcha.captcha' => '验证码错误',
            ]);
            if ($validator->fails()) {
                return ['code' => 0, 'message' => $validator->errors()->first()];
            }
            $parse = parse_url($link);
            if ($parse && isset($parse['host'])) {
                $host = $parse['host'];
                if (Blog::where('link', 'like', "%{$host}%")
                    ->where('email', $email)
                    ->where('status', '<>', 2)
                    ->count()
                ) {
                    return ['code' => 0, 'message' => "您申请的博客 {$host} 已存在，请勿重复申请！"];
                }
            }
            $data = $validator->validated();
            $data['status'] = 0;
            $data['message'] = htmlspecialchars($data['message'],ENT_QUOTES);;
            if (!Blog::create($data)) {
                return ['code' => 0, 'message' => '申请失败, 请稍后重试!'];
            }
            return ['code' => 1, 'message' => '申请成功, 请耐心等待审核, 结果会以邮件形式通知到您的邮箱!'];
        }

        return view('layouts.blogs.join', compact('closeApply'));
    }

    private function getBlogYears(): array
    {
        return Blog::query()->orderBy('created_at')->pluck('created_at')->transform(function ($at) {
            if ($at instanceof Carbon) {
                return $at->year;
            }
        })->unique()->toArray();
    }
}
