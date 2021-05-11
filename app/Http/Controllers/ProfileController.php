<?php

namespace App\Http\Controllers;

use App\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $blog = $request->blog;
        return view('layouts.profile.index', compact('blog'));
    }

    /**
     * 博客设置
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function blogChange(Request $request)
    {
        exit;
        /** @var Blog $blog */
        $blog = $request->blog;
        switch ($request->field) {
            case 'is_comment':
                if (!in_array($request->value, [1, 0])) {
                    return $this->error('数据异常');
                }
                break;
        }
        $field = $request->field;
        $blog->$field = trim($request->value);
        $blog->save();
        return $this->success('设置成功');
    }

    /**
     * 提交大事记
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function submitDateline(Request $request)
    {
        /** @var Blog $blog */
        $blog = $request->blog;
        if ($blog->datelines()->where('status', 0)->count()) {
            return $this->error('您的博客存在正在审核的大事记，请等待该审核处理后继续提交。');
        }
        $content = $request->input('content');
        if (!$time = strtotime($request->date)) {
            return $this->error('时间格式不正确');
        }
        if ($time > time()) {
            return $this->error('不能大于当前时间');
        }
        if (!$content) {
            return $this->error('请输入大事记内容');
        }
        if (mb_strlen($content) > 2000) {
            return $this->error('内容字符过长');
        }
        $blog->datelines()->create([
            'date' => date('Y-m-d H:i:s', $time),
            'content' => htmlspecialchars($content,ENT_QUOTES),
            'status' => 0,
        ]);
        return $this->success('提交成功，审核通过后将会展示在博客详情页。');
    }

    /**
     * 发送登录验证码
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendCodeMail(Request $request)
    {
        if (!$request->email) {
            return $this->error('请输入邮箱');
        }
        if (!Blog::query()
            ->where('email', $request->email)
            ->where('status', 1)
            ->count()
        ) {
            return $this->error('邮箱不存在');
        }
        $code = strtoupper(Str::random(6));
        $cacheKey = $this->getCacheKey($request->email);
        Cache::put($cacheKey, $code, 120);
        Mail::send('emails.code', [
            'content' => "您本次登录的验证码是 <b>{$code}</b>, 有效期 2 分钟，请不要泄漏给其他人，如果不是您本人操作请忽略。",
        ], function ($mail) use ($request) {
            $mail->to($request->email);
            $mail->subject("登录验证码");
        });
        return $this->success('验证码发送成功');
    }

    public function login(Request $request)
    {
        if ($request->method() === 'POST') {
            $cacheKey = $this->getCacheKey($request->email);
            if (!$code = Cache::get($cacheKey)) {
                return $this->error('登录失败');
            }
            if ($code !== $request->code) {
                return $this->error('验证码不正确');
            }
            $request->session()->put('email', $request->email);
            return $this->success('登录成功');
        }
        return view('layouts.profile.login');
    }

    private function getCacheKey($email)
    {
        return md5("email_login_code_{$email}");
    }
}
