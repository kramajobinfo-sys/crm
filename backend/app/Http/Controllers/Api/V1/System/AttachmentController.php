<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ChatMessageAttachment;
use App\Models\EmailAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const MAP = [
        'att'   => Attachment::class,            // lead / deal / project-task files
        'chat'  => ChatMessageAttachment::class, // chat inbox media
        'email' => EmailAttachment::class,       // email attachments
    ];

    /**
     * Stream a private attachment.
     *
     * Reached ONLY through a valid relative signed URL (ValidateSignature:relative) that the
     * API mints exclusively for rows the requesting user was already authorized to see — so
     * the (unforgeable, expiring) signature IS the tenant authorization. The files live on the
     * private `local` disk and are never served by nginx. This replaces the previous public-disk
     * `->url()` accessors, which exposed every lead/deal/chat/email file at /storage with no auth.
     */
    public function show(Request $request, string $type, int $id): StreamedResponse
    {
        $model = self::MAP[$type] ?? abort(404);

        // A signed request carries no auth() user; the signature already gates access. Bypass
        // the company global scope so lookup is deterministic rather than relying on it silently
        // no-op'ing under a null user.
        $row = $model::withoutGlobalScopes()->find($id) ?? abort(404);

        $field = $request->query('field') === 'thumb' ? 'thumbnail_path' : 'path';
        $path  = $row->{$field} ?? null;
        abort_unless($path, 404);

        $disk = Storage::disk($row->disk ?: 'local');
        abort_unless($disk->exists($path), 404);

        // Inline so <img>/<video> render; browsers save types they can't display.
        return $disk->response($path, $row->name ?? basename($path), [
            'Content-Type'  => $row->mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
