<?php

namespace Tests\Feature\Api\Profile\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class ProfilePhotoSecurityTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tradex_account_settings_api.rate_limits.photo_uploads_per_minute', 20);
        config()->set('tradex_account_settings_api.rate_limits.photo_uploads_per_hour', 20);
    }

    public function test_authenticated_customer_can_upload_and_read_only_their_private_photo(): void
    {
        Storage::fake('private');
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $upload = $this->withToken($token)->post('/api/v1/profile/settings/photo', [
            'photo' => $this->png('profile.png'),
        ]);

        $upload
            ->assertOk()
            ->assertJsonPath('data.profile_photo.available', true)
            ->assertJsonPath('data.profile_photo.version', 1)
            ->assertJsonPath(
                'data.profile_photo.endpoint',
                '/api/v1/profile/settings/photo'
            );

        $profile = $user->profile()->firstOrFail();
        $this->assertStringStartsWith(
            'profile-photos/'.$user->uuid.'/',
            $profile->profile_photo_path
        );
        $this->assertSame('image/png', $profile->profile_photo_mime_type);
        $this->assertSame(64, strlen($profile->profile_photo_sha256));
        Storage::disk('private')->assertExists($profile->profile_photo_path);

        $settings = $this->withToken($token)
            ->getJson('/api/v1/profile/settings')
            ->assertOk()
            ->assertJsonPath('data.profile.photo.available', true)
            ->assertJsonPath('data.profile.photo.version', 1);
        $this->assertStringNotContainsString(
            $profile->profile_photo_path,
            $settings->getContent()
        );

        $photo = $this->withToken($token)
            ->get('/api/v1/profile/settings/photo')
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertSame($this->pngBytes(), $photo->streamedContent());

        $other = $this->createApiUser();
        $otherToken = $this->issueBoundToken($other);
        $this->withToken($otherToken)
            ->get('/api/v1/profile/settings/photo')
            ->assertNotFound();
    }

    public function test_replacing_and_removing_photo_deletes_old_private_objects(): void
    {
        Storage::fake('private');
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)->post('/api/v1/profile/settings/photo', [
            'photo' => $this->png('first.png'),
        ])->assertOk();
        $firstPath = $user->profile()->firstOrFail()->profile_photo_path;

        $this->withToken($token)->post('/api/v1/profile/settings/photo', [
            'photo' => $this->png('second.png'),
        ])->assertOk()->assertJsonPath('data.profile_photo.version', 2);
        $secondPath = $user->profile()->firstOrFail()->profile_photo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('private')->assertMissing($firstPath);
        Storage::disk('private')->assertExists($secondPath);

        $this->withToken($token)
            ->deleteJson('/api/v1/profile/settings/photo')
            ->assertOk()
            ->assertJsonPath('data.profile_photo.available', false)
            ->assertJsonPath('data.profile_photo.version', 3);

        Storage::disk('private')->assertMissing($secondPath);
        $this->assertNull($user->profile()->firstOrFail()->profile_photo_path);
    }

    public function test_upload_rejects_executable_content_and_requires_authentication(): void
    {
        Storage::fake('private');
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->postJson('/api/v1/profile/settings/photo', [
            'photo' => $this->png('profile.png'),
        ])->assertUnauthorized();

        $this->withToken($token)->post('/api/v1/profile/settings/photo', [
            'photo' => UploadedFile::fake()->createWithContent(
                'avatar.jpg',
                '<?php system($_GET["command"]);'
            ),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertNull($user->profile()->first());
        Storage::disk('private')->assertDirectoryEmpty('profile-photos');
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes());
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlB8AAAAASUVORK5CYII=',
            true
        );
    }
}
