<?php

namespace App\Support\Authorization;

/**
 * Every manageable type of record in the CMS, with its permission prefix and
 * the abilities that exist for it. Single source of truth for permission
 * names (e.g. "article.edit"); roles are defined in Role.
 */
enum ContentType: string
{
    case Gallery = 'gallery';
    case CouncilTerm = 'wahlperioden';
    case CouncilMember = 'ratsmitglieder';
    case Committee = 'ausschuesse';
    case Media = 'media';
    case SearchSynonym = 'search-synonym';
    case SiteSettings = 'site-settings';
    case Article = 'article';
    case Event = 'event';
    case Document = 'document';
    case BudgetPlan = 'budget-plan';
    case ExternalResource = 'external-resource';
    case PublicNotice = 'notice';
    case Service = 'service';
    case LifeSituation = 'life-situation';
    case Page = 'page';
    case SiteAlert = 'site-alert';
    case Person = 'person';
    case Department = 'department';
    case Organization = 'organization';
    case Location = 'location';
    case ContactRoute = 'contact-route';
    case Taxonomy = 'taxonomy';
    case Navigation = 'navigation';
    case Redirect = 'redirect';
    case User = 'user';

    /**
     * Types with the editorial publication lifecycle (draft → published → archived).
     */
    public function isPublishable(): bool
    {
        return in_array($this, [
            self::Gallery, self::CouncilTerm, self::CouncilMember, self::Committee, self::Media, self::Article, self::Event, self::Document, self::BudgetPlan, self::ExternalResource, self::PublicNotice,
            self::Service, self::LifeSituation, self::Page, self::SiteAlert,
        ], true);
    }

    /**
     * Types whose records are moved to a recycle bin before permanent deletion.
     */
    public function hasRecycleBin(): bool
    {
        return $this->isPublishable() || in_array($this, [
            self::Person, self::Department, self::Organization, self::Location, self::ContactRoute,
        ], true);
    }

    /**
     * @return list<Ability>
     */
    public function abilities(): array
    {
        if ($this === self::User) {
            return [Ability::View, Ability::Create, Ability::Edit];
        }

        $abilities = [Ability::View, Ability::Create, Ability::Edit];

        if ($this->isPublishable()) {
            $abilities[] = Ability::Publish;
            $abilities[] = Ability::Archive;
        }

        $abilities[] = Ability::Delete;

        if ($this->hasRecycleBin()) {
            $abilities[] = Ability::ForceDelete;
        }

        return $abilities;
    }

    public function permission(Ability $ability): string
    {
        return $this->value.'.'.$ability->value;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_map(fn (Ability $a) => $this->permission($a), $this->abilities());
    }

    public function label(): string
    {
        return match ($this) {
            self::Gallery => 'Galerie',
            self::CouncilTerm => 'Wahlperiode',
            self::CouncilMember => 'Ratsmitglied',
            self::Committee => 'Ausschuss',
            self::Media => 'Medien',
            self::SearchSynonym => 'Suchbegriffe',
            self::SiteSettings => 'Website-Einstellungen',
            self::Article => 'Artikel (Aktuelles)',
            self::Event => 'Veranstaltungen',
            self::Document => 'Dokumente',
            self::BudgetPlan => 'Haushaltspläne',
            self::ExternalResource => 'Externe Links & Online-Dienste',
            self::PublicNotice => 'Bekanntmachungen',
            self::Service => 'Bürgerservice-Leistungen',
            self::LifeSituation => 'Lebenslagen',
            self::Page => 'Seiten',
            self::SiteAlert => 'Hinweis-Banner',
            self::Person => 'Personen',
            self::Department => 'Ämter & Sachgebiete',
            self::Organization => 'Vereine, Gewerbe & Gastronomie',
            self::Location => 'Orte & Einrichtungen',
            self::ContactRoute => 'Kontaktformular-Themen',
            self::Taxonomy => 'Kategorien & Schlagwörter',
            self::Navigation => 'Navigation',
            self::Redirect => 'Weiterleitungen',
            self::User => 'Benutzerkonten',
        };
    }
}
