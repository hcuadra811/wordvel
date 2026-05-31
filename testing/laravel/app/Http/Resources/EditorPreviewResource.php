<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Wordvel\Http\Resources\EditorPreviewResource as BaseEditorPreviewResource;

/**
 * Local thin wrapper for WordVel's EditorPreviewResource.
 * The "Resource" suffix allows the generator to auto-create *Response schemas.
 */
class EditorPreviewResource extends BaseEditorPreviewResource
{
}
