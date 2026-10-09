<?php

namespace App\Services\Quality;

use App\Contracts\QualityCheck;
use App\Contracts\Routable;
use App\Enums\QualitySeverity as S;
use App\Support\Content\QualityIssue;
use App\Support\Routing\PublicPath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ContentBasics implements QualityCheck
{
    public function supports(Model $model): bool
    {
        return method_exists($model, 'displayTitle');
    }

    public function inspect(Model $model): array
    {
        $issues = [];
        $a = $model->getAttributes();
        if (is_callable([$model, 'displayTitle']) && trim((string) call_user_func([$model, 'displayTitle'])) === '') {
            $issues[] = new QualityIssue('title.missing', S::Error, 'Ein Titel ist erforderlich.', 'title');
        }
        if ($model instanceof Routable) {
            $route = $model->canonicalRoute()->where('is_active', true)->first();
            if ($route === null && $model::createsRouteAutomatically()) {
                $issues[] = new QualityIssue('route.missing', S::Warning, 'Eine öffentliche Adresse ist erforderlich.', 'public_path');
            }
            if ($route !== null) {
                try {
                    $normalized = PublicPath::normalize($route->path);
                    $valid = $normalized['path'] === $route->path && ! PublicPath::isReserved($normalized['key']);
                } catch (InvalidArgumentException) {
                    $valid = false;
                }
                if (! $valid) {
                    $issues[] = new QualityIssue('route.invalid', S::Error, 'Die öffentliche Adresse ist ungültig.', 'public_path');
                }
            }
            if (empty($a['meta_description'])) {
                $issues[] = new QualityIssue('seo.description', S::Recommendation, 'Eine Meta-Beschreibung hilft bei der Orientierung.', 'meta_description');
            }
        }
        $start = array_key_exists('publish_at', $a) ? $model->getAttribute('publish_at') : null;
        $end = array_key_exists('expires_at', $a) ? $model->getAttribute('expires_at') : null;
        if ($start instanceof CarbonImmutable && $end instanceof CarbonImmutable && $end->lessThanOrEqualTo($start)) {
            $issues[] = new QualityIssue('publication.window', S::Error, 'Das Veröffentlichungsende muss nach dem Beginn liegen.', 'expires_at');
        }
        $body = (string) ($a['body'] ?? $a['description'] ?? '');
        $level = 1;
        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            if (preg_match('/^(#{1,6})\s/u', $line, $m)) {
                $next = max(2, strlen($m[1]));
                if ($next > $level + 1) {
                    $issues[] = new QualityIssue('headings.skipped', S::Warning, 'Eine Überschrift überspringt eine Ebene.', 'body');
                }
                $level = $next;
            }
        }
        if (preg_match('/\[(hier|mehr|weiter|klicken|link)\]\(/iu', $body)) {
            $issues[] = new QualityIssue('links.label', S::Warning, 'Bitte aussagekräftige Linktexte verwenden.', 'body');
        }
        if (preg_match('/<(iframe|embed|script)\b|!\[[^\]]*\]\(https?:/iu', $body)) {
            $issues[] = new QualityIssue('privacy.embed', S::Warning, 'Externe Einbettungen/Bilder werden nicht ausgegeben und benötigen eine Datenschutzprüfung.', 'body');
        }

        return $issues;
    }
}
