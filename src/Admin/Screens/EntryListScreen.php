<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\AdminShell;
use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\Icon;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\SchemaNotices;
use BsFabric\Admin\Components\StatusBadge;
use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRepository;
use BsFabric\Fields\FieldSettings;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateRepository;

final class EntryListScreen
{
    public const SLUG_PREFIX = 'bs-fabric-entries-';

    /** @var list<string> */
    private const CORE_SORT_COLUMNS = ['title', 'status', 'created_at'];

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
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
        SchemaNotices::enqueueToastScript();

        $search = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '';
        $listColumns = $this->getListColumns($template->getId());
        $orderby = $this->resolveOrderBy($listColumns);
        $order = $this->resolveOrder();
        $allEntries = $this->entryRepository->getByTemplate($template->getId())->all();
        $entries = $this->filterEntries($allEntries, $search, $status);
        $entries = $this->sortEntries($entries, $orderby, $order, $listColumns);

        AdminShell::open([
            ['label' => $template->label],
        ]);

        PageHead::render(
            $template->label,
            __('Einträge dieses Templates verwalten.', 'bs-fabric'),
            Button::render(__('Neuer Eintrag', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'plus',
                'href' => $this->editUrl($template->getId()),
            ])
        );

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head">';
        echo '<form method="get" class="bsf-entry-filters-form">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::slugForTemplate($template->name)) . '" />';
        echo '<input type="hidden" name="orderby" value="' . esc_attr($orderby) . '" />';
        echo '<input type="hidden" name="order" value="' . esc_attr($order) . '" />';
        echo '<div class="bsf-search">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('search', 16);
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Suchen…', 'bs-fabric') . '" />';
        echo '</div>';
        echo '<div class="bsf-select-wrap" style="width:160px">';
        echo '<select class="bsf-select" name="status" id="bs-fabric-entry-status">';
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
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render('chevd', 15);
        echo '</div>';
        echo Button::render(__('Filtern', 'bs-fabric'), ['variant' => 'ghost', 'size' => 'sm', 'type' => 'submit']);
        echo '</form></div>';

        if ($entries === []) {
            echo '<div class="bsf-card-body">';

            if ($allEntries === [] && $search === '' && $status === '') {
                echo '<div class="bsf-empty">';
                echo '<h4>' . esc_html__('Noch keine Einträge', 'bs-fabric') . '</h4>';
                echo '<p class="bsf-muted">' . esc_html__('Lege den ersten Datensatz für dieses Template an.', 'bs-fabric') . '</p>';
                echo Button::render(__('Ersten Eintrag anlegen', 'bs-fabric'), [
                    'variant' => 'primary',
                    'icon' => 'plus',
                    'href' => $this->editUrl($template->getId()),
                ]);
                echo '</div>';
            } else {
                echo '<p class="bsf-muted">' . esc_html__('Keine Einträge gefunden.', 'bs-fabric') . '</p>';
            }

            echo '</div></div>';
            AdminShell::close();

            return;
        }

        echo '<table class="bsf-tbl">';
        echo '<thead><tr>';
        $this->renderSortableHeader($template, 'title', __('Titel', 'bs-fabric'), $orderby, $order, $search, $status);

        foreach ($listColumns as $field) {
            $this->renderSortableHeader(
                $template,
                (string) $field['name'],
                (string) $field['label'],
                $orderby,
                $order,
                $search,
                $status
            );
        }

        if ($listColumns !== []) {
            $this->renderSortableHeader($template, 'status', __('Status', 'bs-fabric'), $orderby, $order, $search, $status);
            $this->renderSortableHeader($template, 'created_at', __('Datum', 'bs-fabric'), $orderby, $order, $search, $status);
        }

        echo '<th style="width:120px"></th>';
        echo '</tr></thead><tbody>';

        foreach ($entries as $entry) {
            $title = $entry->title !== null && $entry->title !== ''
                ? (string) $entry->title
                : sprintf('#%d', $entry->getId());
            $editUrl = $this->editUrl($template->getId(), $entry->getId());

            echo '<tr class="bsf-row-link" data-href="' . esc_url($editUrl) . '">';
            echo '<td class="bsf-cell-strong">' . esc_html($title) . '</td>';

            foreach ($listColumns as $field) {
                echo '<td class="bsf-cell-mute">' . esc_html($this->formatListValue($entry, $field)) . '</td>';
            }

            if ($listColumns !== []) {
                echo '<td>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo StatusBadge::render((string) $entry->status);
                echo '</td>';
                echo '<td class="bsf-cell-mute">' . esc_html($this->formatDate((string) $entry->created_at)) . '</td>';
            }

            echo '<td><div class="bsf-row-actions" data-no-row-nav="1">';
            echo Button::render(__('Bearbeiten', 'bs-fabric'), [
                'variant' => 'subtle',
                'size' => 'sm',
                'icon' => 'edit',
                'href' => $editUrl,
            ]);
            echo '<button type="button" class="bsf-btn bsf-btn-subtle bsf-btn-sm bs-fabric-delete-entry" data-entry-id="' . esc_attr((string) $entry->getId()) . '" title="' . esc_attr__('Löschen', 'bs-fabric') . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo Icon::render('trash', 15);
            echo '</button>';
            echo '</div></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';

        wp_add_inline_script(
            'bs-fabric-bsf-ui',
            'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".bsf-row-link[data-href]").forEach(function(row){row.addEventListener("click",function(e){if(e.target.closest("[data-no-row-nav]"))return;window.location.href=row.getAttribute("data-href");});});});'
        );

        AdminShell::close();
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }>
     */
    private function getListColumns(int $templateId): array
    {
        $columns = [];

        foreach ($this->templateRepository->getFields($templateId) as $assignment) {
            $field = $this->fieldRepository->getById($assignment['field_id']);

            if ($field === null || ! FieldSettings::showInList($field['settings'])) {
                continue;
            }

            $columns[] = $field;
        }

        return $columns;
    }

    /**
     * @param list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }> $listColumns
     */
    private function resolveOrderBy(array $listColumns): string
    {
        $requested = isset($_GET['orderby']) ? sanitize_key((string) wp_unslash($_GET['orderby'])) : 'title';
        $allowed = ['title'];

        foreach ($listColumns as $field) {
            $allowed[] = (string) $field['name'];
        }

        if ($listColumns !== []) {
            $allowed = array_merge($allowed, self::CORE_SORT_COLUMNS);
        }

        return in_array($requested, $allowed, true) ? $requested : 'title';
    }

    private function resolveOrder(): string
    {
        $order = isset($_GET['order']) ? strtolower(sanitize_key((string) wp_unslash($_GET['order']))) : 'asc';

        return $order === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param list<Entry> $entries
     * @param list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     type: string,
     *     settings: array<string, mixed>
     * }> $listColumns
     *
     * @return list<Entry>
     */
    private function sortEntries(array $entries, string $orderby, string $order, array $listColumns): array
    {
        usort(
            $entries,
            function (Entry $left, Entry $right) use ($orderby, $order): int {
                $comparison = match ($orderby) {
                    'title' => strcasecmp(
                        $this->entrySortTitle($left),
                        $this->entrySortTitle($right)
                    ),
                    'status' => strcasecmp((string) $left->status, (string) $right->status),
                    'created_at' => strcmp((string) $left->created_at, (string) $right->created_at),
                    default => strcasecmp(
                        $this->entryFieldSortValue($left, $orderby),
                        $this->entryFieldSortValue($right, $orderby)
                    ),
                };

                if ($comparison === 0) {
                    $comparison = $left->getId() <=> $right->getId();
                }

                return $order === 'desc' ? -$comparison : $comparison;
            }
        );

        return $entries;
    }

    private function entrySortTitle(Entry $entry): string
    {
        if ($entry->title !== null && $entry->title !== '') {
            return (string) $entry->title;
        }

        return sprintf('#%d', $entry->getId());
    }

    private function entryFieldSortValue(Entry $entry, string $fieldName): string
    {
        return $this->formatListValue($entry, ['name' => $fieldName, 'type' => 'text']);
    }

    /**
     * @param array{name?: string, type?: string} $field
     */
    private function formatListValue(Entry $entry, array $field): string
    {
        $fieldName = (string) ($field['name'] ?? '');

        if ($fieldName === '') {
            return '—';
        }

        $value = $entry->{$fieldName};

        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('Ja', 'bs-fabric') : __('Nein', 'bs-fabric');
        }

        if (is_array($value)) {
            if (isset($value['url']) && is_string($value['url'])) {
                return $value['url'];
            }

            if (isset($value['label']) && is_string($value['label'])) {
                return $value['label'];
            }

            return '…';
        }

        $stringValue = is_scalar($value) ? (string) $value : '';

        if ($stringValue === '') {
            return '—';
        }

        if (strlen($stringValue) > 80) {
            return substr($stringValue, 0, 77) . '…';
        }

        return $stringValue;
    }

    private function renderSortableHeader(
        Template $template,
        string $column,
        string $label,
        string $currentOrderBy,
        string $currentOrder,
        string $search,
        string $status,
    ): void {
        $isActive = $currentOrderBy === $column;
        $nextOrder = $isActive && $currentOrder === 'asc' ? 'desc' : 'asc';
        $sortIndicator = '';

        if ($isActive) {
            $sortIndicator = '<span class="bsf-sort-ind">' . ($currentOrder === 'asc' ? '↑' : '↓') . '</span>';
        }

        $url = add_query_arg(
            [
                'page' => self::slugForTemplate($template->name),
                'orderby' => $column,
                'order' => $nextOrder,
                's' => $search !== '' ? $search : null,
                'status' => $status !== '' ? $status : null,
            ],
            admin_url('admin.php')
        );

        echo '<th class="bsf-sortable">';
        echo '<a href="' . esc_url($url) . '">';
        echo esc_html($label);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $sortIndicator;
        echo '</a></th>';
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

    private function editUrl(int $templateId, int $entryId = 0): string
    {
        return EntryEditScreen::url($templateId, $entryId);
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);

        return $timestamp !== false ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : $date;
    }
}
