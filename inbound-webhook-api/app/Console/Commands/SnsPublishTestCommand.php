<?php

namespace App\Console\Commands;

use App\Services\Sns\SnsPublisher;
use Illuminate\Console\Command;
use Throwable;

/**
 * Publish a test message to the configured SNS topic.
 *
 *   php artisan sns:publish-test
 *   php artisan sns:publish-test --topic="orders/create" --shop="mrdaisy.myshopify.com"
 *
 * Use this to confirm credentials + topic ARN are wired correctly, and to see a
 * message land in the subscribed SQS queue(s).
 */
class SnsPublishTestCommand extends Command
{
    protected $signature = 'sns:publish-test
        {--topic=orders/create : Value for the "topic" message attribute}
        {--shop=demo.myshopify.com : Value for the "shop_domain" message attribute}';

    protected $description = 'Publish a sample message to the SNS topic';

    public function handle(SnsPublisher $publisher): int
    {
        $this->info('Publishing to: ' . config('sns.topic_arn'));

        try {
            $messageId = $publisher->publish(
                payload: [
                    'source' => 'shopify',
                    'test' => true,
                    'body' => ['id' => 123456, 'note' => 'sns:publish-test'],
                ],
                attributes: [
                    'source' => 'shopify',
                    'topic' => (string) $this->option('topic'),
                    'shop_domain' => (string) $this->option('shop'),
                ],
            );
        } catch (Throwable $e) {
            $this->error('Publish failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Published. MessageId: {$messageId}");

        return self::SUCCESS;
    }
}
