<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\AdminShell;
use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\Icon;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\SchemaNotices;
use BsFabric\Admin\Components\StatusBadge;
use BsFabric\Admin\EntryListExporter;
use BsFabric\Admin\EntryListFormatter;
use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRepository;
use BsFabric\Fields\FieldSettings;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateRepository;

final class EntryListScreen
{
    public const SLUG_PREFIX = 'bs-fabric-entries-';

    public const NONCE_ACTION = 'bs_fabric_entry_list';

    /** @var list<string> */
    private const CORE_SORT_COLUMNS = ['title', 'status', 'created_at'];

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
        private readonly EntryListExporter $entryListExporter,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleExport']);
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

    public function handleExport(): void
    {
        if (! isset($_GET['bs_fabric_export']) || sanitize_key((string) wp_unslash($_GET['bs_fabric_export'])) !== 'entries') {
            return;
        }

        $page = isset($_GET['page']) ? (string) wp_unslash($_GET['page']) : '';
        $templateName = self::resolveTemplateNameFromPage($page);

        if ($templateName === null) {
            return;
        }

        if (! current_user_can('bs_fabric_manage_entries')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Aktion.', 'bs-fabric'));
        }

        if (! $this->verifyExportNonce()) {
            wp_die(esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'bs-fabric'));
        }

        $template = $this->templateRepository->getByName($templateName);

        if ($template === null) {
            wp_die(esc_html__('Template nicht gefunden.', 'bs-fabric'));
        }

        $search = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '';
        $listColumns = $this->getListColumns($template->getId());
        $allEntries = $this->entryRepository->getByTemplate($template->getId())->all();
        $entries = $this->filterEntries($allEntries, $search, $status);
        $orderby = $this->resolveOrderBy($listColumns);
        $order = $this->resolveOrder();
        $entries = $this->sortEntries($entries, $orderby, $order, $listColumns);

        $csv = $this->entryListExporter->toCsv($entries, $listColumns);
        $filename = sprintf(
            'bs-fabric-%s-%s.csv',
            sanitize_file_name($template->name),
            gmdate('Y-m-d')
        );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download
        echo $csv;
        exit;
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

        $actions = Button::render(__('Excel exportieren', 'bs-fabric'), [
            'variant' => 'ghost',
            'icon' => 'box',
            'href' => $this->exportUrl($template, $search, $status, $orderby, $order),
        ]);
        $actions .= Button::render(__('Neuer Eintrag', 'bs-fabric'), [
            'variant' => 'primary',
            'icon' => 'plus',
            'href' => $this->editUrl($template->getId()),
        ]);

        PageHead::render(
            $template->label,
            __('Einträge dieses Templates verwalten.', 'bs-fabric'),
            $actions
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
            $title = EntryListFormatter::title($entry);
            $editUrl = $this->editUrl($template->getId(), $entry->getId());

            echo '<tr class="bsf-row-link" data-href="' . esc_url($editUrl) . '">';
            echo '<td class="bsf-cell-strong">' . esc_html($title) . '</td>';

            foreach ($listColumns as $field) {
                echo '<td class="bsf-cell-mute">' . esc_html(EntryListFormatter::fieldValue($entry, $field)) . '</td>';
            }

            if ($listColumns !== []) {
                echo '<td>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo StatusBadge::render((string) $entry->status);
                echo '</td>';
                echo '<td class="bsf-cell-mute">' . esc_html(EntryListFormatter::date((string) $entry->created_at)) . '</td>';
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

    private function exportUrl(
        Template $template,
        string $search,
        string $status,
        string $orderby,
        string $order,
    ): string {
        return wp_nonce_url(
            add_query_arg(
                array_filter([
                    'page' => self::slugForTemplate($template->name),
                    'bs_fabric_export' => 'entries',
                    's' => $search !== '' ? $search : null,
                    'status' => $status !== '' ? $status : null,
                    'orderby' => $orderby,
                    'order' => $order,
                ]),
                admin_url('admin.php')
            ),
            self::NONCE_ACTION . '_export'
        );
    }

    private function verifyExportNonce(): bool
    {
        $nonce = isset($_GET['_wpnonce']) ? (string) wp_unslash($_GET['_wpnonce']) : '';

        return wp_verify_nonce($nonce, self::NONCE_ACTION . '_export') !== false;
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
                        EntryListFormatter::title($left),
                        EntryListFormatter::title($right)
                    ),
                    'status' => strcasecmp((string) $left->status, (string) $right->status),
                    'created_at' => strcmp((string) $left->created_at, (string) $right->created_at),
                    default => strcasecmp(
                        EntryListFormatter::fieldValue($left, ['name' => $orderby], false),
                        EntryListFormatter::fieldValue($right, ['name' => $orderby], false)
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
}
