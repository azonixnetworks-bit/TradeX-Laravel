<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorClientDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradingFloorClientDocumentController extends Controller
{
    public function store(Request $request, TradingFloorClient $client): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.clients.manage');

        $data = $request->validate([
            'document_type' => ['required', Rule::in([
                TradingFloorClientDocument::TYPE_NIC,
                TradingFloorClientDocument::TYPE_PASSPORT,
                TradingFloorClientDocument::TYPE_AGREEMENT,
                TradingFloorClientDocument::TYPE_ACCESS,
                TradingFloorClientDocument::TYPE_OTHER,
            ])],
            'title' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $request->file('document');
        abort_unless($file !== null, 422);

        $path = $file->store('trading-floor/documents/'.$client->uuid, 'local');

        try {
            $client->documents()->create([
                'document_type' => strtoupper((string) $data['document_type']),
                'title' => trim((string) $data['title']),
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                'file_size' => (int) $file->getSize(),
                'status' => TradingFloorClientDocument::STATUS_ACTIVE,
                'uploaded_by_admin_id' => $admin->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Customer document uploaded to private storage.');
    }

    public function download(
        TradingFloorClient $client,
        TradingFloorClientDocument $document
    ): StreamedResponse {
        $admin = $this->admin();
        $this->requireViewPermission($admin);
        abort_unless($document->trading_floor_client_id === $client->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    }

    public function archive(
        Request $request,
        TradingFloorClient $client,
        TradingFloorClientDocument $document
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.clients.manage');
        abort_unless($document->trading_floor_client_id === $client->id, 404);

        $data = $request->validate([
            'archived_reason' => ['required', 'string', 'max:2000'],
        ]);

        if ($document->status !== TradingFloorClientDocument::STATUS_ARCHIVED) {
            $document->update([
                'status' => TradingFloorClientDocument::STATUS_ARCHIVED,
                'archived_at' => now(),
                'archived_reason' => trim((string) $data['archived_reason']),
                'archived_by_admin_id' => $admin->id,
            ]);
        }

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Customer document archived. The private file remains preserved for audit history.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requireViewPermission(AdminUser $admin): void
    {
        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission('trading_floor.view')
            || $admin->hasPermission('trading_floor.clients.manage'),
            403
        );
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->isSuperAdmin() || $admin->hasPermission($permission), 403);
    }
}
