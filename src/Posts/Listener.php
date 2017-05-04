<?php
/**
 * Sunny
 *
 * Automatically purge CloudFlare cache, including cache everything rules.
 *
 * @package   Sunny
 *
 * @author    Typist Tech <sunny@typist.tech>
 * @copyright 2017 Typist Tech
 * @license   GPL-2.0+
 *
 * @see       https://www.typist.tech/projects/sunny
 * @see       https://wordpress.org/plugins/sunny/
 */

declare(strict_types=1);

namespace TypistTech\Sunny\Posts;

use TypistTech\Sunny\Caches\PurgeCommandFactory;
use TypistTech\Sunny\Caches\Purger;
use TypistTech\Sunny\LoadableInterface;
use TypistTech\Sunny\Vendor\TypistTech\WPContainedHook\Action;
use WP_Post;

/**
 * Final class Listener
 */
final class Listener implements LoadableInterface
{
    /**
     * PurgeCommandFactory
     *
     * @var PurgeCommandFactory
     */
    private $purgeCommandFactory;

    /**
     * Purger
     *
     * @var Purger
     */
    private $purger;

    /**
     * Listener constructor.
     *
     * @param PurgeCommandFactory $purgeCommandFactory Purge command factory.
     * @param Purger              $purger              Purger.
     */
    public function __construct(PurgeCommandFactory $purgeCommandFactory, Purger $purger)
    {
        $this->purgeCommandFactory = $purgeCommandFactory;
        $this->purger = $purger;
    }

    /**
     * {@inheritdoc}
     */
    public static function getHooks(): array
    {
        return [
            new Action('transition_post_status', __CLASS__, 'handlePostStatusTransited', 0, 3),
            new Action('edit_post', __CLASS__, 'handlePostEdited', 0, 2),
        ];
    }

    /**
     * Trigger a purge when post edited.
     *
     * @param int     $_id  Post ID.
     * @param WP_Post $post Post object.
     *
     * @return void
     */
    public function handlePostEdited(int $_id, WP_Post $post)
    {
        // Translators: %1$s is the post id; %2$s is the old status; %3$s is the new status.
        $reasonFormat = __('Post (ID: %1$s) is being edited', 'sunny');
        $reason = sprintf($reasonFormat, $post->ID);

        $command = $this->purgeCommandFactory->buildForPost($post, $reason);
        $this->purger->execute($command);
    }

    /**
     * Trigger a purge when post status transited
     *
     * @param string  $newStatus New post status.
     * @param string  $oldStatus Old post status.
     * @param WP_Post $post      Post object.
     *
     * @return void
     */
    public function handlePostStatusTransited(string $newStatus, string $oldStatus, WP_Post $post)
    {
        if ($newStatus === $oldStatus) {
            return;
        }

        $targetStatues = apply_filters(
            'sunny_post_listener_target_statues',
            [ 'publish' ],
            $post,
            $newStatus,
            $oldStatus
        );

        // When both $newStatus and $oldStatus are not targeted.
        if (! in_array($newStatus, $targetStatues, true) && ! in_array($oldStatus, $targetStatues, true)) {
            return;
        }

        // Translators: %1$s is the post id; %2$s is the old status; %3$s is the new status.
        $reasonFormat = __('Post (ID: %1$s) changed from %2$s to %3$s', 'sunny');
        $reason = sprintf($reasonFormat, $post->ID, $oldStatus, $newStatus);

        $command = $this->purgeCommandFactory->buildForPost($post, $reason);
        $this->purger->execute($command);
    }
}
