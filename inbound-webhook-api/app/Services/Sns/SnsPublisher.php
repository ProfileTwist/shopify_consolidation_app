<?php

namespace App\Services\Sns;

use Aws\Sns\SnsClient;
use RuntimeException;

/**
 * Publishes messages to an SNS topic.
 *
 * === How to write to the SNS topic ===
 *
 *   $publisher = app(SnsPublisher::class);
 *   $messageId = $publisher->publish(
 *       payload:    ['hello' => 'world'],          // becomes the JSON message body
 *       attributes: ['source' => 'shopify',        // SNS message attributes:
 *                    'topic'  => 'orders/create'], // used for SQS filter policies / routing
 *   );
 *
 * SNS then fans the message out to every SQS queue subscribed to the topic
 * (the "SNS -> SQS chain"). Downstream workers read from those queues.
 *
 * Message attributes matter: a subscribed SQS queue can carry a *filter policy*
 * (e.g. only source=shopify, topic=orders/*) so each consumer receives just the
 * events it cares about, without the publisher knowing who is listening.
 */
class SnsPublisher
{
    private ?SnsClient $client = null;

    /**
     * Publish a payload to the configured SNS topic.
     *
     * @param  array<string, mixed>  $payload     JSON-encoded into the message body.
     * @param  array<string, string> $attributes  SNS message attributes (String type).
     * @return string  The SNS MessageId.
     */
    public function publish(array $payload, array $attributes = []): string
    {
        $topicArn = config('sns.topic_arn');

        if (empty($topicArn)) {
            throw new RuntimeException('SNS_TOPIC_ARN is not configured.');
        }

        $result = $this->client()->publish([
            'TopicArn' => $topicArn,
            'Message' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'MessageAttributes' => $this->formatAttributes($attributes),
        ]);

        return (string) $result->get('MessageId');
    }

    /**
     * Lazily build the SNS client from config. `endpoint` is set only for
     * LocalStack; in real AWS it stays null and the SDK picks the region.
     */
    private function client(): SnsClient
    {
        if ($this->client) {
            return $this->client;
        }

        $config = [
            'version' => 'latest',
            'region' => config('sns.region'),
        ];

        if ($endpoint = config('sns.endpoint')) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = true;
        }

        // Only pass explicit credentials when provided; otherwise the SDK's
        // default provider chain (env, IAM role, profile) is used.
        if (config('sns.credentials.key') && config('sns.credentials.secret')) {
            $config['credentials'] = array_filter([
                'key' => config('sns.credentials.key'),
                'secret' => config('sns.credentials.secret'),
                'token' => config('sns.credentials.token'),
            ]);
        }

        return $this->client = new SnsClient($config);
    }

    /**
     * Format a flat key => value map into SNS MessageAttributes (all String).
     *
     * @param  array<string, string>  $attributes
     * @return array<string, array<string, string>>
     */
    private function formatAttributes(array $attributes): array
    {
        $formatted = [];

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $formatted[$key] = [
                'DataType' => 'String',
                'StringValue' => (string) $value,
            ];
        }

        return $formatted;
    }
}
