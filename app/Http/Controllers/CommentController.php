<?php

namespace App\Http\Controllers;

use App\Article;
use App\Comment;
use App\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function article(Request $request)
    {
        $replyId = $request->input('reply_id');
        $parentId = $request->input('parent_id');
        try {
            $data = $this->validated();

            if (!$article = Article::find($data['foreign_id'])) {
                throw new \Exception('不存在的资源');
            }

            $isReview = Config::where('key', 'review_comment')->first();
            $data['is_admin'] = 0;
            $data['status'] = $isReview->value == 1 ? 2 : 1;
            $data['ip'] = $request->getClientIp();
            $data['type'] = $article->type;
            $content = $data['content'];
            if ($created = Comment::create($data)) {
                if ($parentId == 0 && $replyId == 0) {
                    // 给被回复对象发邮件
                    $types = Article::TYPES;
                    $subject = "【十年之约】{$types[$article->type]} <{$article->title}> 有了新的评论";
                    $this->sendCommentEmail(
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
                    $this->sendReplyEmail(
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
     * 发送评论邮件
     *
     * @param Comment $comment  新插入的评论
     * @param string $email     收件人邮箱
     * @param string $subject   邮件主题
     * @param string $name      收件人名称
     * @param string $title     发布内容标题
     * @param string $type      发布内容类型
     * @param string $content   评论内容
     */
    private function sendCommentEmail(
        Comment $comment,
        string $email,
        string $subject,
        string $name,
        string $title,
        string $type,
        string $content
    ) {
        Mail::send('emails.comment', [
            'title' => $title,
            'name' => $name,
            'comment' => $comment,
            'type' => $type,
            'content' => str_replace(PHP_EOL, '<br />', $content),
        ], function ($mail) use ($email, $subject) {
            $mail->to($email);
            $mail->subject($subject);
        });
    }

    /**
     * 发送回复邮件
     *
     * @param Comment $row      原评论
     * @param Comment $comment  新插入的评论
     * @param string $title     发布内容标题
     * @param string $email     收件人邮箱
     * @param string $subject   邮件主题
     * @param string $content   邮件内容
     */
    private function sendReplyEmail(
        Comment $row,
        Comment $comment,
        string $title,
        string $email,
        string $subject,
        string $content
    ) {
        Mail::send('emails.reply', [
            'title' => $title,
            'row' => $row,
            'comment' => $comment,
            'content' => str_replace(PHP_EOL, '<br />', $content)
        ], function ($mail) use ($email, $subject) {
            $mail->to($email);
            $mail->subject($subject);
        });
    }

    /**
     * 验证数据
     *
     * @return array
     * @throws \Exception
     */
    private function validated()
    {
        $validator = Validator::make(\request()->all(), [
            'parent_id' => 'required|numeric',
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

        return $validator->validated();
    }
}
