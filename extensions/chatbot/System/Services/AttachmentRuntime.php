<?php

namespace App\Extensions\Chatbot\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentRuntime
{
    public function store(UploadedFile $file, array $context = []): ChatbotAttachment
    {
        $this->validateUpload($file);

        $disk = (string) config('chatbot.attachments.disk', 'local');
        $directory = trim((string) config('chatbot.attachments.directory', 'chatbot/attachments'), '/');
        $hash = hash_file('sha256', $file->getRealPath());

        $existing = ChatbotAttachment::query()
            ->where('sha256', $hash)
            ->where('size', $file->getSize())
            ->where('conversation_id', $context['conversation_id'] ?? null)
            ->where('status', 'ready')
            ->first();

        if ($existing && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        $path = $file->store($directory, $disk);
        if (! $path) {
            throw ValidationException::withMessages(['file' => 'The attachment could not be stored.']);
        }

        return ChatbotAttachment::query()->create(array_merge($context, [
            'disk' => $disk,
            'path' => $path,
            'name' => $this->safeName($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'sha256' => $hash,
            'status' => 'ready',
        ]));
    }

    public function download(ChatbotAttachment $attachment): StreamedResponse
    {
        if ($attachment->status !== 'ready' || ! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    public function quarantine(ChatbotAttachment $attachment, string $reason): ChatbotAttachment
    {
        $attachment->forceFill([
            'status' => 'quarantined',
            'quarantined_at' => now(),
            'metadata' => array_replace($attachment->metadata ?? [], ['quarantine_reason' => $reason]),
        ])->save();

        return $attachment->refresh();
    }

    public function delete(ChatbotAttachment $attachment): void
    {
        if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $attachment->delete();
    }

    private function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The attachment upload is invalid.']);
        }

        $maximum = max(1, (int) config('chatbot.attachments.max_size_kb', 20480)) * 1024;
        if ((int) $file->getSize() > $maximum) {
            throw ValidationException::withMessages(['file' => 'The attachment exceeds the configured size limit.']);
        }

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $allowed = array_map('strtolower', (array) config('chatbot.attachments.allowed_mime_types', []));
        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'This attachment type is not allowed.']);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $blocked = array_map('strtolower', (array) config('chatbot.attachments.blocked_extensions', []));
        if ($extension !== '' && in_array($extension, $blocked, true)) {
            throw ValidationException::withMessages(['file' => 'This attachment extension is blocked.']);
        }
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\\\/]+/u', '-', $name) ?: 'attachment';

        return mb_substr(trim($name), 0, 255) ?: 'attachment';
    }
}
