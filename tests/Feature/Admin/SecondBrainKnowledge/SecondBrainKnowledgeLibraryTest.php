<?php

namespace Tests\Feature\Admin\SecondBrainKnowledge;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\SecondBrainKnowledgeItem;
use App\Models\SecondBrainKnowledgeMedia;
use App\Models\SecondBrainKnowledgeSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecondBrainKnowledgeLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_second_brain_knowledge(): void
    {
        $this->get(route('admin.second_brain_knowledge.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_non_super_admin_cannot_access_second_brain_knowledge(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.second_brain_knowledge.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_personal_knowledge_subject(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.second_brain_knowledge.subjects.store'), [
                'name' => 'Supply & Demand',
                'description' => 'Founder methodology for supply and demand.',
                'market_scope' => 'COMMON',
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.second_brain_knowledge.subjects'));

        $subject = SecondBrainKnowledgeSubject::query()->first();

        $this->assertNotNull($subject);
        $this->assertSame('Supply & Demand', $subject->name);
        $this->assertSame('COMMON', $subject->market_scope);
        $this->assertSame($admin->id, $subject->created_by_admin_id);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'SECOND_BRAIN_SUBJECT_CREATED',
            'module' => 'SECOND_BRAIN_KNOWLEDGE',
            'target_id' => $subject->id,
        ]);
    }

    public function test_super_admin_can_save_draft_knowledge_with_private_source_media(): void
    {
        Storage::fake('private');

        $admin = $this->superAdmin();
        $subject = $this->subject($admin);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.second_brain_knowledge.library.store'), [
                'subject_id' => $subject->id,
                'heading' => 'Valid Demand Zone',
                'title' => 'Fresh H4 demand with M15 confirmation',
                'notes' => 'This is founder-authored methodology source material.',
                'market' => 'FOREX',
                'knowledge_type' => 'RULE',
                'timeframes' => ['H4', 'M15'],
                'tags' => 'demand, confirmation, forex',
                'images' => [
                    UploadedFile::fake()->createWithContent(
                        'valid-demand.png',
                        base64_decode(
                            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
                        )
                    ),
                ],
                'pdfs' => [
                    UploadedFile::fake()->create(
                        'demand-rules.pdf',
                        100,
                        'application/pdf'
                    ),
                ],
                'videos' => [
                    UploadedFile::fake()->create(
                        'demand-explanation.mp4',
                        1024,
                        'video/mp4'
                    ),
                ],
            ]);

        $item = SecondBrainKnowledgeItem::query()->first();

        $this->assertNotNull($item);

        $response->assertRedirect(
            route('admin.second_brain_knowledge.library.show', $item)
        );

        $this->assertSame(
            SecondBrainKnowledgeItem::STATUS_DRAFT,
            $item->status
        );
        $this->assertNull($item->founder_approved_at);
        $this->assertSame(['H4', 'M15'], $item->timeframes);
        $this->assertSame(
            ['demand', 'confirmation', 'forex'],
            $item->tags
        );

        $media = SecondBrainKnowledgeMedia::query()
            ->where('knowledge_item_id', $item->id)
            ->get();

        $this->assertCount(3, $media);
        $this->assertSame(
            ['IMAGE', 'PDF', 'VIDEO'],
            $media->pluck('media_type')->sort()->values()->all()
        );

        foreach ($media as $source) {
            $this->assertSame('private', $source->disk);
            $this->assertSame(64, strlen($source->sha256));
            Storage::disk('private')->assertExists($source->path);
        }

        $audit = AuditLog::query()
            ->where('action', 'SECOND_BRAIN_KNOWLEDGE_CREATED')
            ->where('target_id', $item->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('DRAFT', $audit->new_values['status']);
        $this->assertSame(3, $audit->new_values['media_count']);
    }

    public function test_empty_knowledge_without_notes_or_media_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $subject = $this->subject($admin);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.second_brain_knowledge.library.create'))
            ->post(route('admin.second_brain_knowledge.library.store'), [
                'subject_id' => $subject->id,
                'title' => 'Empty source',
                'market' => 'COMMON',
                'knowledge_type' => 'NOTE',
            ])
            ->assertRedirect(route('admin.second_brain_knowledge.library.create'))
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseCount('second_brain_knowledge_items', 0);
    }

    private function subject(AdminUser $admin): SecondBrainKnowledgeSubject
    {
        return SecondBrainKnowledgeSubject::query()->create([
            'name' => 'Liquidity',
            'description' => 'Founder liquidity methodology.',
            'market_scope' => 'COMMON',
            'sort_order' => 1,
            'is_active' => true,
            'created_by_admin_id' => $admin->id,
        ]);
    }

    private function superAdmin(): AdminUser
    {
        $admin = $this->admin();

        $role = AdminRole::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            [
                'name' => 'Super Admin',
                'description' => 'Second Brain feature test super admin.',
                'is_system' => true,
                'is_active' => true,
            ]
        );

        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin->fresh('roles');
    }

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Second Brain Test Admin',
            'email' => 'second-brain-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
    }
}
