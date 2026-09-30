# Inbound Webhook API

A small, generic, **dockerized PHP/Laravel** service that receives inbound
webhooks (e.g. Shopify), verifies their signature, and **publishes them to an
AWS SNS topic**. SNS fans each message out to one or more SQS queues (the
**SNS → SQS chain**) that downstream services consume.

```
  Shopify ──POST /api/webhooks/shopify──▶  Inbound Webhook API
                                              │  (verify HMAC, then publish)
                                              ▼
                                          SNS topic  ──▶  SQS queue(s)  ──▶  consumers
```

It does **no heavy work** on the request: verify → publish → return `200` fast,
because Shopify drops a webhook subscription if it doesn't get a 200 within ~5s.

## Endpoints

| Method | Path                      | Notes                                        |
|--------|---------------------------|----------------------------------------------|
| POST   | `/api/webhooks/{source}`  | `{source}` = `shopify`. Auth = HMAC signature |
| GET    | `/up`                     | Health check                                 |

## How to write to the SNS topic

The reusable publisher lives in [`app/Services/Sns/SnsPublisher.php`](app/Services/Sns/SnsPublisher.php):

```php
use App\Services\Sns\SnsPublisher;

$messageId = app(SnsPublisher::class)->publish(
    payload:    ['order_id' => 123, 'total' => '42.00'],   // → JSON message body
    attributes: ['source' => 'shopify', 'topic' => 'orders/create'], // → SNS message attributes
);
```

- **`payload`** is JSON-encoded into the SNS message body.
- **`attributes`** become SNS *message attributes*. A subscribed SQS queue can use
  a **filter policy** on these (e.g. `source=shopify`, `topic=orders/*`) so each
  consumer only receives the events it wants — the publisher never needs to know
  who is listening.

Configuration is in [`config/sns.php`](config/sns.php), driven by env:

| Env var | Purpose |
|---------|---------|
| `SNS_TOPIC_ARN` | The topic to publish to |
| `AWS_DEFAULT_REGION` | AWS region |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | Credentials (omit to use an IAM role) |
| `AWS_ENDPOINT_URL` | Only for LocalStack (e.g. `http://localstack:4566`); leave unset in real AWS |
| `SHOPIFY_WEBHOOK_SECRET` | App client secret used to verify Shopify HMAC |

Quick check from the CLI:

```bash
php artisan sns:publish-test --topic="orders/create" --shop="mrdaisy.myshopify.com"
```

## Run locally (with LocalStack — no real AWS needed)

Requires Docker. `docker compose up --build` starts:
- **localstack** — local SNS + SQS; an init script auto-creates the topic
  `inbound-webhooks`, the queue `inbound-webhooks-consumer`, and the subscription.
- **app** — the webhook API (php-fpm), pointed at LocalStack.
- **web** — nginx on **http://localhost:8090**.

```bash
docker compose up --build
```

Send a test webhook (HMAC verification is skipped locally via `SHOPIFY_WEBHOOK_SKIP_VERIFY=true`):

```bash
curl -X POST http://localhost:8090/api/webhooks/shopify \
  -H "X-Shopify-Topic: orders/create" \
  -H "X-Shopify-Shop-Domain: mrdaisy.myshopify.com" \
  -H "Content-Type: application/json" \
  -d '{"id":123456,"total_price":"42.00"}'
```

Confirm it landed in the SQS queue:

```bash
docker compose exec localstack \
  awslocal sqs receive-message \
  --queue-url http://localhost:4566/000000000000/inbound-webhooks-consumer
```

## Going to real AWS

1. Create an SNS topic; set `SNS_TOPIC_ARN` + `AWS_DEFAULT_REGION`, and unset `AWS_ENDPOINT_URL`.
2. Subscribe your SQS queue(s) to the topic (enable *Raw message delivery* if consumers want the body unwrapped); add filter policies as needed.
3. Give the service an **IAM role** with `sns:Publish` on the topic (preferred over static keys).
4. Set `SHOPIFY_WEBHOOK_SECRET` to the app client secret and leave `SHOPIFY_WEBHOOK_SKIP_VERIFY` unset.
5. Register the webhooks in Shopify (Admin API / app config) pointing at
   `https://<this-service>/api/webhooks/shopify` for topics like
   `orders/create`, `orders/updated`, `orders/cancelled`, `orders/fulfilled`.

## Security notes

- Signature verification uses the **raw request body** and a **constant-time
  compare** (`hash_equals`). Never verify against re-encoded JSON.
- `SHOPIFY_WEBHOOK_SKIP_VERIFY=true` is for local testing only.
- The endpoint has no auth middleware by design — authenticity is the HMAC.
