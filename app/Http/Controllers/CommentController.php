<?php

namespace App\Http\Controllers;

use App\Article;
use App\Blog;
use App\Comment;
use App\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * 评论文章 单页 公告
     *
     * @param Request $request
     * @return array
     */
    public function article(Request $request)
    {
        $replyId = $request->input('reply_id');
        $parentId = $request->input('parent_id');
        try {
            $data = $this->validated();
            if (!$article = Article::find($data['foreign_id'])) {
                throw new \Exception('不存在的资源');
            }
            if (!$article->is_comment) {
                throw new \Exception('管理员已关闭评论');
            }
            $data['type'] = 'article';
            $content = $data['content'];
            if (!$created = Comment::create($data)) {
                throw new \Exception('评论失败');
            }
            if ($article->email !== $data['email']) { // 邮件邮箱相同的不接收通知
                if ($parentId == 0 && $replyId == 0) {
                    // 给被回复对象发邮件
                    $types = Article::TYPES;
                    $subject = "【十年之约】{$types[$article->type]} <{$article->title}> 有了新的评论";
                    // 邮箱相同的不接收通知
                    Comment::sendCommentEmail(
                        $created,
                        $article->email,
                        $subject,
                        $article->name,
                        $article->title,
                        $types[$article->type],
                        $content
                    );
                } else {
                    // 被回复对象原评论
                    $comment = null;
                    if ($replyId != 0 && !$comment = Comment::find($replyId)) {
                        throw new \Exception('回复对象不存在');
                    }
                    // 给被回复对象发邮件
                    Comment::sendReplyEmail(
                        $comment,
                        $created,
                        $comment->article->title,
                        $comment->email,
                        "【十年之约】你的评论有了新的回复",
                        $content
                    );
                }
            }
        } catch (\Exception $e) {
            return ['code' => 0, 'line' => $e->getLine(), 'message' => $e->getMessage()];
        }
        return ['code' => 1, 'message' => '评论成功, 审核通过后显示'];
    }

    /**
     * 评论博客
     *
     * @param Request $request
     * @return array
     */
    public function blog(Request $request)
    {
        $replyId = $request->input('reply_id');
        $parentId = $request->input('parent_id');
        try {
            $data = $this->validated();

            if (!$blog = Blog::find($data['foreign_id'])) {
                throw new \Exception('不存在的资源');
            }

            if (!$blog->is_comment) {
                throw new \Exception('博主已关闭评论');
            }

            $data['type'] = 'blog';
            $content = $data['content'];
            if (!$created = Comment::create($data)) {
                throw new \Exception('评论失败');
            }
            if ($blog->email !== $data['email']) { // 邮件邮箱相同的不接收通知
                if ($parentId == 0 && $replyId == 0) {
                    // 给被回复对象发邮件
                    $subject = "【十年之约】评论提醒";
                    Comment::sendCommentEmail(
                        $created,
                        $blog->email,
                        $subject,
                        $blog->name,
                        $blog->link,
                        '博客',
                        $content
                    );
                } else {
                    // 被回复对象原评论
                    $comment = null;
                    if ($replyId != 0 && !$comment = Comment::find($replyId)) {
                        throw new \Exception('回复对象不存在');
                    }
                    // 给被回复对象发邮件
                    Comment::sendReplyEmail(
                        $comment,
                        $created,
                        $comment->blog->name,
                        $comment->email,
                        "【十年之约】你的评论有了新的回复",
                        $content
                    );
                }
            }
        } catch (\Exception $e) {
            return ['code' => 0, 'line' => $e->getLine(), 'message' => $e->getMessage()];
        }
        return ['code' => 1, 'message' => '评论成功, 审核通过后显示'];
    }

    /**
     * 验证数据
     *
     * @return array
     * @throws \Exception
     */
    private function validated()
    {
        $all = \request()->all();
        if (empty($all['link'])) {
            unset($all['link']);
        }
        $validator = Validator::make($all, [
            'parent_id' => 'required|numeric',
            'reply_id' => 'numeric',
            'foreign_id' => 'required|numeric',
            'name' => 'required|min:2|max:20',
            'email' => 'required|email',
            'link' => 'url|max:50',
            'content' => 'required|min:2|max:999'
        ], [
            'link.url' => '网站地址不正确',
            'link.max' => '网站地址过长, 最多 50 个字符',
        ]);
        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }
        $data = $validator->validated();

        $isReview = Config::where('key', 'review_comment')->first();
        $data['is_admin'] = \Encore\Admin\Facades\Admin::user() ? 1 : 0;
        $data['status'] = $isReview->value == 1 ? 2 : 1;
        $data['ip'] = \request()->getClientIp();
        $data['content'] = htmlspecialchars($data['content'],ENT_QUOTES);
        return $data;
    }
}
