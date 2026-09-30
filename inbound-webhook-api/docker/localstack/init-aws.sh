#!/bin/bash
# Runs inside LocalStack once it is ready. Builds the SNS -> SQS chain:
#   SNS topic "inbound-webhooks"  --subscribes-->  SQS queue "inbound-webhooks-consumer"
set -e

REGION=us-east-1
TOPIC_NAME=inbound-webhooks
QUEUE_NAME=inbound-webhooks-consumer

echo "[init] creating SNS topic + SQS queue + subscription..."

TOPIC_ARN=$(awslocal sns create-topic --name "$TOPIC_NAME" --region "$REGION" --output text --query 'TopicArn')
QUEUE_URL=$(awslocal sqs create-queue --queue-name "$QUEUE_NAME" --region "$REGION" --output text --query 'QueueUrl')
QUEUE_ARN=$(awslocal sqs get-queue-attributes --queue-url "$QUEUE_URL" --attribute-names QueueArn --region "$REGION" --output text --query 'Attributes.QueueArn')

# Subscribe the queue to the topic. RawMessageDelivery=true so consumers get the
# published body directly (not wrapped in the SNS envelope).
awslocal sns subscribe \
  --topic-arn "$TOPIC_ARN" \
  --protocol sqs \
  --notification-endpoint "$QUEUE_ARN" \
  --attributes '{"RawMessageDelivery":"true"}' \
  --region "$REGION"

echo "[init] SNS topic ARN: $TOPIC_ARN"
echo "[init] SQS queue URL: $QUEUE_URL"
echo "[init] chain ready."
