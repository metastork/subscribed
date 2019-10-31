<?php


namespace FoF\Subscribed\Jobs;

use Flarum\Discussion\Discussion;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use FoF\Subscribed\Blueprints\DiscussionCreatedBlueprint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Expression;
use Illuminate\Queue\SerializesModels;

class SendNotificationWhenDiscussionIsStarted implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @var Discussion
     */
    protected $discussion;

    public function __construct(Discussion $discussion)
    {
        $this->discussion = $discussion;
    }

    public function handle(NotificationSyncer $notifications)
    {
        $discussion = $this->discussion;

        $notify = User::query()
            ->where('users.id', '!=', $discussion->start_user_id)
            ->where('preferences', 'regexp', new Expression('\'"notify_discussionCreated_[a-z]+":true\''))
            ->get();

        $notify = $notify->filter(function (User $recipient) use ($discussion) {
            return $recipient->can('subscribeDiscussionCreated') && $discussion->newQuery()->whereVisibleTo($recipient)->find($discussion->id) && !$discussion->stateFor($recipient)->last_read_post_number;
        });

        $notifications->sync(
            new DiscussionCreatedBlueprint($discussion),
            $notify->all()
        );
    }
}