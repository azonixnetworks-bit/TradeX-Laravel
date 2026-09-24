<?php

namespace App\Services\Admin\SecondBrainKnowledge;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\SecondBrainKnowledgeItem;
use App\Models\SecondBrainKnowledgeMedia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SecondBrainKnowledgeService
{
    public function createItem(
        AdminUser $admin,
        array $data,
        Request $request
    ): SecondBrainKnowledgeItem {
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $admin,
                $data,
                $request,
                &$storedPaths
            ): SecondBrainKnowledgeItem {
                $item = SecondBrainKnowledgeItem::query()->create([
                    'subject_id' => $data['subject_id'],
                    'heading' => filled($data['heading'] ?? null) ? $data['heading'] : null,
                    'title' => $data['title'],
                    'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
                    'market' => $data['market'],
                    'timeframes' => $data['timeframes'] ?? [],
                    'knowledge_type' => $data['knowledge_type'],
                    'tags' => $data['tags'] ?? [],
                    'status' => SecondBrainKnowledgeItem::STATUS_DRAFT,
                    'version' => 1,
                    'created_by_admin_id' => $admin->id,
                    'updated_by_admin_id' => $admin->id,
                ]);

                $this->storeUploads(
                    $item,
                    $admin,
                    $request,
                    $storedPaths
                );

                AuditLog::record(
                    'ADMIN',
                    $admin->id,
                    'SECOND_BRAIN_KNOWLEDGE_CREATED',
                    'SECOND_BRAIN_KNOWLEDGE',
                    'second_brain_knowledge_item',
                    $item->id,
                    null,
                    [
                        'uuid' => $item->uuid,
                        'subject_id' => $item->subject_id,
                        'market' => $item->market,
                        'knowledge_type' => $item->knowledge_type,
                        'status' => $item->status,
                        'media_count' => $item->media()->count(),
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return $item->load(['subject', 'media']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('private')->delete($storedPath);
            }

            throw $exception;
        }
    }

    private function storeUploads(
        SecondBrainKnowledgeItem $item,
        AdminUser $admin,
        Request $request,
        array &$storedPaths
    ): void {
        $groups = [
            'images' => SecondBrainKnowledgeMedia::TYPE_IMAGE,
            'pdfs' => SecondBrainKnowledgeMedia::TYPE_PDF,
            'videos' => SecondBrainKnowledgeMedia::TYPE_VIDEO,
        ];

        foreach ($groups as $field => $type) {
            foreach ((array) $request->file($field, []) as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $this->storeFile(
                    $item,
                    $admin,
                    $file,
                    $type,
                    $storedPaths
                );
            }
        }
    }

    private function storeFile(
        SecondBrainKnowledgeItem $item,
        AdminUser $admin,
        UploadedFile $file,
        string $type,
        array &$storedPaths
    ): void {
        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '') {
            throw new RuntimeException('Unable to read uploaded knowledge file.');
        }

        $sha256 = hash_file('sha256', $realPath);

        if (! is_string($sha256)) {
            throw new RuntimeException('Unable to fingerprint uploaded knowledge file.');
        }

        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: $file->extension()
            ?: 'bin'
        );

        $filename = (string) Str::uuid().'.'.$extension;
        $directory = sprintf(
            'second-brain-knowledge/%s/%s',
            $item->uuid,
            strtolower($type)
        );

        $path = Storage::disk('private')->putFileAs(
            $directory,
            $file,
            $filename
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store uploaded knowledge file securely.');
        }

        $storedPaths[] = $path;

        SecondBrainKnowledgeMedia::query()->create([
            'knowledge_item_id' => $item->id,
            'media_type' => $type,
            'disk' => 'private',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'sha256' => $sha256,
            'metadata' => null,
            'uploaded_by_admin_id' => $admin->id,
        ]);
    }
}
