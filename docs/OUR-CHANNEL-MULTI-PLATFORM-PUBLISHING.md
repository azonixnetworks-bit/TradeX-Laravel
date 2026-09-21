# TradeX Our Channel — Multi-Platform Video Publishing

## Scope

TradeX stores one protected master video and tracks publication independently for:

- YouTube
- Instagram Reels
- Facebook Reels
- Telegram Channel
- TikTok
- WhatsApp Channel

YouTube, Instagram, Facebook and Telegram have queue-backed provider adapters. TikTok is intentionally held at **ACTION_REQUIRED** until the creator completes TikTok's required interactive creator/privacy/consent flow. WhatsApp Channel is intentionally held at **ACTION_REQUIRED** until TradeX can use an officially supported Channel publishing API.

All provider integrations are disabled by default.

## Deploy

1. Pull the feature branch and install the existing PHP dependencies.
2. Configure the production `.env` values described in `.env.example`.
3. Run:

```bash
php artisan migrate
php artisan config:cache
php artisan queue:work --queue=channel-publishing,default --tries=1
```

The channel publishing worker is intentionally configured for one automatic attempt. Social publication APIs can return ambiguous network failures after accepting a post, so uncontrolled automatic retry can create duplicate posts. Failed rows can be retried explicitly from the Our Channel admin screen after checking the provider.

## Required application URL

Instagram and Facebook hosted-video publishing fetch the protected TradeX master through a short-lived signed URL.

Production requirements:

- `APP_URL` must be the public HTTPS origin of the TradeX deployment.
- The public web server must be reachable by Meta's servers.
- The signed media route must not be placed behind an admin login, VPN, or IP restriction that blocks provider fetches.
- The source object itself stays on the approved `private` or `s3-private` disk.

The signed delivery URL expires automatically and is rate limited.

## Master video

The upload form accepts MP4 and MOV and stores the original on protected storage.

For the most reliable cross-platform result, upload a social-compatible MP4 (H.264 video + AAC audio). Platform-specific duration, aspect-ratio, codec and size rules still apply. This phase does **not** silently crop, trim or transcode the master video for each social network. If a provider rejects the media, that platform is marked failed while the other platform jobs remain independent.

## YouTube

Environment keys start with `TRADEX_CHANNEL_YOUTUBE_`.

Required operational setup:

- Google Cloud project with YouTube Data API enabled.
- OAuth client approved for the account/channel.
- Refresh token with video upload permission.
- Enable `TRADEX_CHANNEL_YOUTUBE_ENABLED=true` only after credentials are installed.

TradeX uses resumable upload. The default privacy is `private`; change it only after the project's YouTube API publishing/audit requirements are satisfied.

## Instagram Reels

Environment keys start with `TRADEX_CHANNEL_INSTAGRAM_` plus `TRADEX_CHANNEL_META_GRAPH_VERSION`.

Required operational setup:

- Professional Instagram account supported by the Meta publishing API.
- Approved Meta app permissions.
- Instagram user ID and valid access token.
- Current supported Graph API version configured explicitly.
- Public HTTPS `APP_URL`.

TradeX creates a Reel media container, waits for processing to finish, then publishes it.

## Facebook Reels

Environment keys start with `TRADEX_CHANNEL_FACEBOOK_` plus `TRADEX_CHANNEL_META_GRAPH_VERSION`.

Required operational setup:

- Facebook Page and approved app permissions.
- Valid Page access token.
- Current supported Graph API version configured explicitly.
- Public HTTPS `APP_URL`.

TradeX starts a Reel upload session, supplies the signed master-video URL, then finishes publication.

## Telegram Channel

Environment keys start with `TRADEX_CHANNEL_TELEGRAM_`.

Required operational setup:

- Create a Telegram bot.
- Add the bot as an administrator of the target Channel with permission to post.
- Configure the bot token and Channel chat ID/username.
- Enable `TRADEX_CHANNEL_TELEGRAM_ENABLED=true`.

This implementation uses Telegram Bot API multipart `sendVideo`, so the current direct-upload guard is 50 MB. Larger masters are marked failed for Telegram instead of breaking other destinations.

## TikTok

TradeX currently records TikTok as **ACTION_REQUIRED**.

Before adding Direct Post, the UI must implement TikTok's creator-info and posting-consent flow, including the creator's current account identity, available privacy options, interaction options and explicit post consent. Do not replace this with hidden/background posting.

Unaudited TikTok Content Posting API clients can also be restricted to private visibility. Production Direct Post should be enabled only after the TikTok app and `video.publish` scope are approved and the integration passes the required review/audit.

## WhatsApp Channel

TradeX currently records WhatsApp Channel as **ACTION_REQUIRED / manual**.

Do not use WhatsApp Web browser automation, session scraping or unofficial endpoints. The existing WhatsApp Cloud API integration in TradeX is for supported business messaging workflows and is not treated as a WhatsApp Channel publishing API.

When Meta exposes and documents a supported Channel publishing endpoint for the required account type, it can be added behind the existing platform-adapter boundary without changing the publication table or admin workflow.

## Admin workflow

1. Open **Admin → Our Channel → Upload Video**.
2. Upload the master video.
3. Enter title, description/caption, hashtags and language.
4. Select destinations.
5. Choose **Save Draft** or **Upload & Publish Enabled Platforms**.
6. Review every platform independently on the Our Channel list.
7. For a failed automatic provider, fix credentials/media/provider state first, then use **Retry / Recheck Setup**.

Unpublishing from TradeX does not delete already-published posts from external platforms. This is intentional to avoid destructive cross-platform actions without explicit provider-specific confirmation.

## Security

- Never commit provider access tokens, refresh tokens or client secrets.
- Keep credentials only in the deployment secret store / environment.
- Provider errors stored in TradeX are sanitized so raw credentials are not intentionally persisted.
- Master video paths are server-generated and restricted to protected filesystem disks.
- Existing RBAC permission `our_channel.manage` gates mutation actions.
- External publishing status is retained per platform for auditability.
