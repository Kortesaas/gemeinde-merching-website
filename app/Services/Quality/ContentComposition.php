<?php

namespace App\Services\Quality;

use App\Contracts\QualityCheck;
use App\Enums\AccessibilityStatus;
use App\Enums\OnlineServiceMode;
use App\Enums\QualitySeverity as S;
use App\Models;
use App\Support\Content\QualityIssue;
use Illuminate\Database\Eloquent\Model;

class ContentComposition implements QualityCheck
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function inspect(Model $model): array
    {
        $issues = [];
        if (method_exists($model, 'blocks')) {
            $level = 1;
            foreach ($model->blocks()->get() as $block) {
                if ($block->type === 'heading') {
                    if ($block->heading_level > $level + 1) {
                        $issues[] = new QualityIssue('blocks.heading', S::Error, 'Die Inhaltsbausteine überspringen eine Überschriftenebene.', 'blocks');
                    }
                    $level = $block->heading_level;
                }
                $target = $block->referenced();
                if (in_array($block->type, ['text', 'heading', 'callout', 'accordion'], true)) {
                    if (preg_match('/\[(hier|mehr|weiter|klicken|link)\]\(/iu', (string) $block->text)) {
                        $issues[] = new QualityIssue('blocks.link_label', S::Warning, 'Bitte aussagekräftige Linktexte in Inhaltsbausteinen verwenden.', 'blocks');
                    }

                    continue;
                }
                if ($target === null || ! is_callable([$target, 'isPubliclyReachable']) || ! call_user_func([$target, 'isPubliclyReachable'])) {
                    $issues[] = new QualityIssue('blocks.reference', S::Error, 'Ein Baustein verweist auf einen gelöschten, inaktiven oder nicht öffentlichen Eintrag.', 'blocks');
                } elseif ($target instanceof Models\Gallery) {
                    $issues = [...$issues, ...$this->inspect($target)];
                } elseif ($target instanceof Models\Media && $target->isImage() && ! $target->hasAccessibleAlternative()) {
                    $issues[] = new QualityIssue('blocks.alt', S::Error, 'Das Bild im Baustein benötigt Alternativtext.', 'blocks');
                } elseif ($target instanceof Models\Document && $target->accessibility_status !== AccessibilityStatus::Accessible) {
                    $issues[] = new QualityIssue('blocks.document_accessibility', S::Warning, 'Die Barrierefreiheit eines Dokuments im Baustein ist nicht bestätigt.', 'blocks');
                }
                if ($target instanceof Models\ExternalResource && $target->getAttribute('privacy_note')) {
                    $issues[] = new QualityIssue('blocks.privacy', S::Warning, 'Datenschutzhinweis zum externen Dienst beachten; er wird nur verlinkt.', 'blocks');
                }
            }
        }
        if ($model instanceof Models\Gallery) {
            if ($model->items()->count() === 0) {
                $issues[] = new QualityIssue('gallery.empty', S::Error, 'Eine öffentliche Galerie benötigt mindestens ein Bild.', 'items');
            }
            foreach ($model->items()->with('media')->get() as $item) {
                $media = $item->media;
                if ($media === null || ! $media->isImage() || ! $media->isPubliclyReachable()) {
                    $issues[] = new QualityIssue('gallery.media', S::Error, 'Ein Galeriebild ist nicht öffentlich verfügbar.', 'items');
                } elseif (! $media->is_decorative && trim($item->alternative($media)) === '') {
                    $issues[] = new QualityIssue('gallery.alt', S::Error, 'Ein Galeriebild benötigt einen Alternativtext.', 'items');
                }
            }
        }
        if ($model instanceof Models\Service && in_array($model->online_service_mode, [OnlineServiceMode::Application, OnlineServiceMode::Appointment, OnlineServiceMode::Information], true)) {
            if ($model->onlineService === null || ! $model->onlineService->isPubliclyReachable()) {
                $issues[] = new QualityIssue('service.online', S::Error, 'Für diesen Online-Modus ist ein veröffentlichter externer Dienst erforderlich.', 'online_service_resource_id');
            }
        }
        if ($model instanceof Models\CouncilTerm) {
            if ($model->getAttribute('starts_on') && $model->getAttribute('ends_on') && $model->getAttribute('ends_on')->lessThan($model->getAttribute('starts_on'))) {
                $issues[] = new QualityIssue('council.dates', S::Error, 'Die Wahlperiode endet vor ihrem Beginn.', 'ends_on');
            }
            foreach ($model->memberships()->with('member')->get() as $membership) {
                if ($membership->member === null || ! $membership->member->isPubliclyReachable()) {
                    $issues[] = new QualityIssue('council.member', S::Error, 'Ein Ratsmitglied der Wahlperiode ist nicht veröffentlicht.', 'memberships');
                }
            }
        }
        if ($model instanceof Models\Committee) {
            $memberIds = $model->term?->memberships()->pluck('council_member_id')->all() ?? [];
            foreach ($model->committeeMemberships()->with('member')->get() as $membership) {
                if (! in_array($membership->getAttribute('council_member_id'), $memberIds, false) || $membership->member === null || ! $membership->member->isPubliclyReachable()) {
                    $issues[] = new QualityIssue('committee.member', S::Error, 'Ausschussmitglieder müssen veröffentlichte Mitglieder derselben Wahlperiode sein.', 'committeeMemberships');
                }
            }
        }

        return $issues;
    }
}
