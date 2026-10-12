<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:publish-scheduled')]
#[Description('Publish scheduled posts whose publish time has passed')]
class PublishScheduledPosts extends Command
{
    /**
     * Publish every due scheduled post through Post::publish(), so its slug freezes
     * and it keeps its scheduled time as its publish date. Trashed posts are skipped.
     */
    public function handle(): int
    {
        $published = 0;

        Post::where('status', PostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->lazyById()
            ->each(function (Post $post) use (&$published): void {
                $post->publish();
                $post->save();
                $published++;
            });

        $this->info("Published {$published} scheduled ".str('post')->plural($published).'.');

        return self::SUCCESS;
    }
}
