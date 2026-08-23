TradeX Laravel — API-10 Notifications Mobile API
================================================

What this adds
--------------
A secure in-app Notification Center foundation and mobile API.

New database tables:
- notification_messages
- user_notification_receipts

New endpoints:
- list notifications
- unread/total summary
- notification detail
- mark one read
- mark all visible notifications read

Security
--------
- Sanctum + tradex.mobile
- recipient-scoped UUID lookup
- server-side FREE/PREMIUM audience filtering
- future/inactive/expired messages hidden
- no numeric DB IDs
- no arbitrary external URL actions
- no mobile delete endpoint
- no fake push-delivery claims

Install
-------
1. Make sure the current branch is:
   feature/api-10-notifications-mobile

2. Extract this ZIP into the Laravel project root.

3. Run:
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --name=api.v1.notifications
   php artisan test --filter=Notification
   php artisan test
   git diff --check
   git status

Expected routes
---------------
GET    api/v1/notifications
GET    api/v1/notifications/summary
GET    api/v1/notifications/{notification}
PATCH  api/v1/notifications/{notification}/read
PATCH  api/v1/notifications/read-all

Important
---------
API-10 is in-app notifications only.

FCM/APNs device push token registration, push dispatch, retry, provider receipts
and delivery analytics are intentionally not claimed by this module.
