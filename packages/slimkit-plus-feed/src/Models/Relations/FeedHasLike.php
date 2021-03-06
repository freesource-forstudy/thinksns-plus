<?php

namespace Zhiyi\Component\ZhiyiPlus\PlusComponentFeed\Models\Relations;

use Zhiyi\Plus\Models\Like;
use Zhiyi\Plus\Models\User;
use Illuminate\Support\Facades\Cache;

trait FeedHasLike
{
    /**
     * Has likes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Check user like.
     *
     * @param mixed $user
     * @return bool
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function liked($user): bool
    {
        if ($user instanceof User) {
            $user = $user->id;
        }

        $cacheKey = sprintf('feed-like:%s,%s', $this->id, $user);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $status = $this->likes()
            ->where('user_id', $user)
            ->first() !== null;

        Cache::forever($cacheKey, $status);

        return $status;
    }

    /**
     * Like feed.
     *
     * @param mixed $user
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function like($user)
    {
        if ($user instanceof User) {
            $user_name = $user->name;
            $user = $user->id;
        }
        $this->forgetLike($user);

        return $this->getConnection()->transaction(function () use ($user, $user_name) {
            $this->increment('like_count', 1);

            // 增加用户点赞数
            $this->user->extra()->firstOrCreate([])->increment('likes_count', 1);

            return $this->likes()->firstOrCreate([
                'user_id' => $user,
                'target_user' => $this->user_id,
            ]);
        });
    }

    /**
     * Unlike feed.
     *
     * @param mixed $user
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function unlike($user)
    {
        if ($user instanceof User) {
            $user = $user->id;
        }

        $like = $this->likes()->where('user_id', $user)->first();
        $this->forgetLike($user);

        return $like && $this->getConnection()->transaction(function () use ($like) {
            $this->decrement('like_count', 1);
            $this->user->extra()->decrement('likes_count', 1);
            $like->delete();

            return true;
        });
    }

    /**
     * Forget like cache.
     *
     * @param mixed $user
     * @return void
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function forgetLike($user)
    {
        if ($user instanceof User) {
            $user = $user->id;
        }

        $cacheKey = sprintf('feed-like:%s,%s', $this->id, $user);
        Cache::forget($cacheKey);
    }
}
