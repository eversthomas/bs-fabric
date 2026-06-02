<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\Template;

final class EntryListScreen
{
    public const SLUG_PREFIX = 'bs-fabric-entries-';

    public function __construct(
        private readonly EntryRepository $entryRepository,
    ) {
    }

    public function renderForTemplate(Template $template): void
    {
        if (! current_user_can('bs_fabric_manage_entries')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $this->renderList($template);
    }

    public static function slugForTemplate(string $templateName): string
    {
        return self::SLUG_PREFIX . sanitize_key($templateName);
    }

    public static function resolveTemplateNameFromPage(string $page): ?string
    {
        if (! str_starts_with($page, self::SLUG_PREFIX)) {
            return null;
        }

        $name = substr($page, strlen(self::SLUG_PREFIX));

        return $name !== '' ? $name : null;
    }

    private function renderList(Template $template): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '';
        $entries = $this->filterEntries(
            $this->entryRepository->getByTemplate($template->getId())->all(),
            $search,
            $status
        );

        echo '<div class="wrap bs-fabric-wrap bs-fabric-entry-list">';
        echo '<h1 class="wp-heading-inline">' . esc_html($template->label) . '</h1>';
        echo '<a href="' . esc_url($this->editUrl($template->getId())) . '" class="page-title-action">';
        echo esc_html__('Neuer Entry', 'bs-fabric') . '</a>';
        echo '<hr class="wp-header-end">';

        $this->renderAdminNotices();
        $this->renderFilters($template, $search, $status);

        if ($entries === []) {
            echo '<p>' . esc_html__('Keine Entries gefunden.', 'bs-fabric') . '</p>';
            echo '</div>';

            return;
        }

        echo '<table class="wp-list-table widefat fixed striped bs-fabric-entries-table">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Titel', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Status', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Datum', 'bs-fabric') . '</th>';
        echo '<th scope="col">' . esc_html__('Aktionen', 'bs-fabric') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $title = $entry->title !== null && $entry->title !== ''
                ? (string) $entry->title
                : sprintf('#%d', $entry->getId());

            echo '<tr>';
            echo '<td><strong>' . esc_html($title) . '</strong></td>';
            echo '<td>' . esc_html($this->formatStatus((string) $entry->status)) . '</td>';
            echo '<td>' . esc_html($this->formatDate((string) $entry->created_at)) . '</td>';
            echo '<td class="bs-fabric-row-actions">';
            echo '<a href="' . esc_url($this->editUrl($template->getId(), $entry->getId())) . '">';
            echo esc_html__('Bearbeiten', 'bs-fabric') . '</a> | ';
            echo '<button type="button" class="button-link bs-fabric-delete-entry" data-entry-id="' . esc_attr((string) $entry->getId()) . '">';
            echo esc_html__('Löschen', 'bs-fabric') . '</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<Entry>
     */
    private function filterEntries(array $entries, string $search, string $status): array
    {
        return array_values(array_filter(
            $entries,
            static function (Entry $entry) use ($search, $status): bool {
                if ($status !== '' && (string) $entry->status !== $status) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = strtolower(trim((string) ($entry->title ?? '') . ' ' . $entry->getId()));

                return str_contains($haystack, strtolower($search));
            }
        ));
    }

    private function renderFilters(Template $template, string $search, string $status): void
    {
        echo '<form method="get" class="bs-fabric-entry-filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::slugForTemplate($template->name)) . '" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="bs-fabric-entry-search">' . esc_html__('Suchen', 'bs-fabric') . '</label>';
        echo '<input type="search" id="bs-fabric-entry-search" name="s" value="' . esc_attr($search) . '" />';
        submit_button(__('Suchen', 'bs-fabric'), '', '', false);
        echo '</p>';
        echo '<div class="alignleft actions">';
        echo '<label for="bs-fabric-entry-status" class="screen-reader-text">' . esc_html__('Status', 'bs-fabric') . '</label>';
        echo '<select name="status" id="bs-fabric-entry-status">';
        echo '<option value="">' . esc_html__('Alle Status', 'bs-fabric') . '</option>';

        foreach (['draft' => __('Entwurf', 'bs-fabric'), 'published' => __('Veröffentlicht', 'bs-fabric'), 'archived' => __('Archiviert', 'bs-fabric')] as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($status, $value, false),
                esc_html($label)
            );
        }

        echo '</select>';
        submit_button(__('Filtern', 'bs-fabric'), '', 'filter', false);
        echo '</div></form>';
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'published' => __('Veröffentlicht', 'bs-fabric'),
            'archived' => __('Archiviert', 'bs-fabric'),
            default => __('Entwurf', 'bs-fabric'),
        };
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);

        return $timestamp !== false ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : $date;
    }

    private function editUrl(int $templateId, int $entryId = 0): string
    {
        $args = [
            'page' => EntryEditScreen::SLUG,
            'template_id' => $templateId,
        ];

        if ($entryId > 0) {
            $args['entry_id'] = $entryId;
        } else {
            $args['action'] = 'add';
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function renderAdminNotices(): void
    {
        if (! isset($_GET['bs_fabric_notice'], $_GET['bs_fabric_message'])) {
            return;
        }

        $status = sanitize_key((string) wp_unslash($_GET['bs_fabric_notice']));
        $message = sanitize_text_field(rawurldecode((string) wp_unslash($_GET['bs_fabric_message'])));
        $class = $status === 'success' ? 'notice-success' : 'notice-error';

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
    }
}
