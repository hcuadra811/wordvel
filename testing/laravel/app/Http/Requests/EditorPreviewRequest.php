<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Wordvel\Http\Requests\EditorPreviewRequest as BaseEditorPreviewRequest;

/**
 * Local thin wrapper for WordVel's EditorPreviewRequest.
 * Allows the openapi-docs-generator to discover it when scanning the local app.
 */
class EditorPreviewRequest extends BaseEditorPreviewRequest
{
}
