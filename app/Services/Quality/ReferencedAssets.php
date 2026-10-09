<?php

namespace App\Services\Quality;

use App\Contracts\QualityCheck;
use App\Enums\AccessibilityStatus;
use App\Enums\QualitySeverity as S;
use App\Models\Document;
use App\Models\Media;
use App\Rules\SafeUrl;
use App\Support\Content\QualityIssue;
use Illuminate\Database\Eloquent\Model;

class ReferencedAssets implements QualityCheck
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function inspect(Model $model): array
    {
        $issues = [];
        $images = $model instanceof Media ? [$model] : (method_exists($model, 'media') ? $model->media()->withTrashed()->get()->all() : []);
        foreach ($images as $media) {
            if ($media->trashed()) {
                $issues[] = new QualityIssue('media.deleted', S::Error, 'Ein verwendetes Medium liegt im Papierkorb.', 'media');
            } elseif ($model !== $media && ! $media->isPubliclyReachable()) {
                $issues[] = new QualityIssue('media.private', S::Error, 'Ein verwendetes Medium ist nicht veröffentlicht.', 'media');
            }
            if ($media->isImage() && ! $media->hasAccessibleAlternative()) {
                $issues[] = new QualityIssue('media.alt', S::Error, 'Ein bedeutungstragendes Bild benötigt Alternativtext oder muss ausdrücklich als dekorativ markiert sein.', $model instanceof Media ? 'alt_text' : 'media');
            }
        }
        $documents = $model instanceof Document ? [$model] : (method_exists($model, 'documents') ? $model->documents()->withTrashed()->get()->all() : []);
        foreach ($documents as $document) {
            if ($document->trashed() || ($model !== $document && ! $document->isPubliclyReachable())) {
                $issues[] = new QualityIssue('document.unresolved', S::Warning, 'Ein zugeordnetes Dokument ist nicht öffentlich erreichbar.', 'documents');
            }
            if ($document->accessibility_status !== AccessibilityStatus::Accessible) {
                $issues[] = new QualityIssue('document.accessibility', S::Warning, 'Dokument „'.$document->title.'“: '.$document->accessibility_status->label().'. Eine manuelle Prüfung ist erforderlich.', 'documents');
            }
        }
        if (method_exists($model, 'externalResources')) {
            foreach ($model->externalResources()->withTrashed()->get() as $link) {
                if (! SafeUrl::isSafe($link->url) || ! $link->isPubliclyReachable()) {
                    $issues[] = new QualityIssue('external.unresolved', S::Warning, 'Ein zugeordneter externer Link ist ungültig oder nicht veröffentlicht.', 'externalResources');
                }
                if ($link->privacy_note) {
                    $issues[] = new QualityIssue('external.privacy', S::Warning, 'Datenschutzhinweis zu „'.$link->title.'“ beachten.', 'externalResources');
                }
            }
        }

        return $issues;
    }
}
