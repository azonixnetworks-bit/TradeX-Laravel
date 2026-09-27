# TradeX Redis Performance Deployment

This document prepares TradeX to move cache, sessions, and queues away from MySQL
without changing those production drivers until Redis availability is verified.

## Scope

This performance deployment does not change Second Brain code or data.

Recommended production targets after Redis is confirmed healthy:

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default
QUEUE_CONNECTION=redis
```

Keep the current database drivers until the checks below pass.

## Pre-flight checks

1. Confirm the PHP Redis extension is installed for the same PHP version used by
   the TradeX web application and CLI workers.
2. Confirm the Redis server is reachable from the application account.
3. Confirm Redis authentication settings when the server requires a password.
4. Confirm enough Redis memory is available and an appropriate eviction policy is
   configured by the hosting provider.
5. Confirm queue workers can run continuously under Supervisor, systemd, or the
   hosting provider's supported process manager.

## Laravel checks

After deployment environment variables are updated:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then verify Redis before starting normal traffic:

```bash
php artisan tinker
```

Inside Tinker, perform a temporary cache write/read and remove it immediately:

```php
cache()->put('tradex:redis:health', 'ok', 30);
cache()->get('tradex:redis:health');
cache()->forget('tradex:redis:health');
```

The read must return `ok`.

## Queue workers

Use separate queue workers so CPU-heavy work cannot block customer-facing jobs.

Suggested queue separation:

- default
- notifications
- channel-publishing
- video-processing

Video processing must remain isolated because FFmpeg can consume significant CPU
and memory.

## Safe rollout order

1. Redis cache
2. Redis sessions
3. Redis queues
4. Dedicated queue workers
5. Observe error rate, queue latency, CPU, memory, and API response times
6. Roll back only the affected driver if a production check fails

## Important

Do not switch TradeX production to Redis merely because Redis configuration exists
in Laravel. The live hosting environment must be verified first.

Do not change Second Brain storage, ingestion, RAG, or vector infrastructure as part
of this customer-response performance rollout.
