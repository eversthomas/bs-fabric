<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Templates\TemplateSettings;

/**
 * Öffentliche Detail-URLs /{template}/{slug} für Templates mit public_detail.
 */
final class EntryPermalink implements EntryUrlResolver
{
    public const SERVICE_ID = 'entry.permalink';

    public const QUERY_TEMPLATE = 'bs_fabric_template';

    public const QUERY_SLUG = 'bs_fabric_slug';

    public const FLUSH_OPTION = 'bs_fabric_rewrite_flush';

    private ?Entry $resolvedEntry = null;

    private bool $resolutionAttempted = false;

    public function __construct(
        private readonly TemplateLookup $templateRepository,
        private readonly EntryQueryExecutor $entryRepository,
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('wp', [$this, 'onWp']);
        add_filter('template_include', [$this, 'maybeIncludeTemplate']);
        add_filter('document_title_parts', [$this, 'filterDocumentTitle']);
    }

    /**
     * @param list<string> $vars
     *
     * @return list<string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_TEMPLATE;
        $vars[] = self::QUERY_SLUG;

        return $vars;
    }

    public function registerRewriteRules(): void
    {
        foreach ($this->publicTemplateNames() as $templateName) {
            add_rewrite_rule(
                '^' . preg_quote($templateName, '/') . '/([^/]+)/?$',
                'index.php?' . self::QUERY_TEMPLATE . '=' . $templateName . '&' . self::QUERY_SLUG . '=$matches[1]',
                'top'
            );
        }
    }

    public function maybeFlushRewriteRules(): void
    {
        if (! get_option(self::FLUSH_OPTION)) {
            return;
        }

        flush_rewrite_rules(false);
        delete_option(self::FLUSH_OPTION);
    }

    public static function requestRewriteFlush(): void
    {
        update_option(self::FLUSH_OPTION, 1, false);
    }

    public function onWp(): void
    {
        $entry = $this->resolveFromRequest();

        if ($entry !== null) {
            global $wp_query;

            if ($wp_query instanceof \WP_Query) {
                $wp_query->is_404 = false;
                $wp_query->is_home = false;
                $wp_query->is_page = false;
                $wp_query->is_singular = true;
            }

            status_header(200);
            nocache_headers();

            return;
        }

        if (! $this->requestHasFabricVars()) {
            return;
        }

        global $wp_query;

        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }

        status_header(404);
        nocache_headers();
    }

    public function maybeIncludeTemplate(string $template): string
    {
        $entry = $this->resolveFromRequest();

        if ($entry === null) {
            if ($this->requestHasFabricVars()) {
                $notFound = get_404_template();

                return is_string($notFound) && $notFound !== '' ? $notFound : $template;
            }

            return $template;
        }

        $path = $this->resolveSingleTemplatePath();

        return $path ?? $template;
    }

    /**
     * @param array<string, string> $parts
     *
     * @return array<string, string>
     */
    public function filterDocumentTitle(array $parts): array
    {
        $entry = $this->resolveFromRequest();

        if ($entry === null) {
            return $parts;
        }

        $title = $entry->title;
        $parts['title'] = is_string($title) && $title !== ''
            ? $title
            : sprintf('#%d', $entry->getId());

        return $parts;
    }

    public function getResolvedEntry(): ?Entry
    {
        return $this->resolveFromRequest();
    }

    /**
     * Öffentliche URL oder null, wenn das Template/Entry keine Detailseite hat.
     */
    public function urlFor(Entry $entry): ?string
    {
        if ($entry->status !== 'published') {
            return null;
        }

        $slug = $entry->slug;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $template = $this->templateRepository->getById((int) $entry->template_id);

        if ($template === null || ! TemplateSettings::publicDetail($template->settings)) {
            return null;
        }

        return $this->buildUrl((string) $template->name, $slug);
    }

    public function buildUrl(string $templateName, string $slug): string
    {
        $templateName = sanitize_key($templateName);
        $slug = sanitize_title($slug);

        return home_url('/' . $templateName . '/' . $slug . '/');
    }

    /**
     * @return list<string>
     */
    private function publicTemplateNames(): array
    {
        $names = [];

        foreach ($this->templateRepository->getAll() as $template) {
            if (! TemplateSettings::publicDetail($template->settings)) {
                continue;
            }

            $name = sanitize_key((string) $template->name);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function requestHasFabricVars(): bool
    {
        $template = sanitize_key((string) get_query_var(self::QUERY_TEMPLATE));
        $slug = sanitize_title((string) get_query_var(self::QUERY_SLUG));

        return $template !== '' && $slug !== '';
    }

    private function resolveFromRequest(): ?Entry
    {
        if ($this->resolutionAttempted) {
            return $this->resolvedEntry;
        }

        $this->resolutionAttempted = true;

        $templateName = sanitize_key((string) get_query_var(self::QUERY_TEMPLATE));
        $slug = sanitize_title((string) get_query_var(self::QUERY_SLUG));

        if ($templateName === '' || $slug === '') {
            return null;
        }

        $template = $this->templateRepository->getByName($templateName);

        if ($template === null || ! TemplateSettings::publicDetail($template->settings)) {
            return null;
        }

        $this->resolvedEntry = $this->entryRepository->findPublishedByTemplateSlug(
            $template->getId(),
            $slug
        );

        return $this->resolvedEntry;
    }

    private function resolveSingleTemplatePath(): ?string
    {
        $themeDirectory = get_stylesheet_directory();
        $candidates = [
            $themeDirectory . '/bs-fabric/single.php',
            $themeDirectory . '/bs-fabric/default/single.php',
            BS_FABRIC_PATH . 'templates/frontend/single.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
