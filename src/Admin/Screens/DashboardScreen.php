<?php

declare(strict_types=1);

namespace BsFabric\Admin\Screens;

use BsFabric\Admin\Components\AdminShell;
use BsFabric\Admin\Components\Button;
use BsFabric\Admin\Components\DataLifecycleHints;
use BsFabric\Admin\Components\Icon;
use BsFabric\Admin\Components\PageHead;
use BsFabric\Admin\Components\StatusBadge;
use BsFabric\Core\Capabilities;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRepository;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateRepository;

final class DashboardScreen
{
    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly FieldRepository $fieldRepository,
        private readonly EntryRepository $entryRepository,
    ) {
    }

    public function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Seite.', 'bs-fabric'));
        }

        $user = wp_get_current_user();
        $displayName = $user instanceof \WP_User && $user->display_name !== ''
            ? $user->display_name
            : __('Redakteur', 'bs-fabric');

        $templates = $this->templateRepository->getAll();
        $fieldCount = count($this->fieldRepository->getAll());
        $entryCount = $this->entryRepository->countAll();
        $draftCount = $this->entryRepository->countByStatus('draft');
        $recentEntries = $this->entryRepository->getRecentlyUpdated(5);

        AdminShell::open([
            ['label' => __('Übersicht', 'bs-fabric')],
        ]);

        $actions = '';

        if (current_user_can(Capabilities::MANAGE_SCHEMA)) {
            $actions .= Button::render(__('Neues Feld', 'bs-fabric'), [
                'variant' => 'ghost',
                'icon' => 'threads',
                'href' => SchemaScreen::url(SchemaScreen::TAB_FIELDS, ['action' => 'add']),
            ]);
            $actions .= Button::render(__('Neues Template', 'bs-fabric'), [
                'variant' => 'primary',
                'icon' => 'plus',
                'href' => SchemaScreen::url(SchemaScreen::TAB_TEMPLATES, ['action' => 'add']),
            ]);
        }

        PageHead::render(
            __('Übersicht', 'bs-fabric'),
            sprintf(
                /* translators: %s: display name of the current user */
                __('Willkommen zurück, %s. Hier laufen alle Fäden deines strukturierten Contents zusammen.', 'bs-fabric'),
                $displayName
            ),
            $actions
        );

        $this->renderStats(count($templates), $fieldCount, $entryCount, $draftCount);
        DataLifecycleHints::renderDashboardCard();
        $this->renderColumns($templates, $recentEntries);

        AdminShell::close();
    }

    private function renderStats(int $templateCount, int $fieldCount, int $entryCount, int $draftCount): void
    {
        echo '<div class="bsf-stat-grid" style="margin-bottom:var(--gap)">';

        $this->renderStatCard('layers', (string) $templateCount, __('Templates', 'bs-fabric'), 'accent');
        $this->renderStatCard('threads', (string) $fieldCount, __('Felder in der Bibliothek', 'bs-fabric'), 'thread');
        $this->renderStatCard('rows', (string) $entryCount, __('Einträge gesamt', 'bs-fabric'), 'accent');
        $this->renderStatCard('edit', (string) $draftCount, __('Offene Entwürfe', 'bs-fabric'), 'thread');

        echo '</div>';
    }

    private function renderStatCard(string $icon, string $value, string $label, string $tone): void
    {
        $toneClass = $tone === 'thread' ? 'bsf-stat-icon-thread' : 'bsf-stat-icon-accent';

        echo '<div class="bsf-card bsf-stat-card">';
        echo '<div class="bsf-stat-icon ' . esc_attr($toneClass) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Icon::render($icon, 18);
        echo '</div>';
        echo '<div class="bsf-stat-value">' . esc_html($value) . '</div>';
        echo '<div class="bsf-stat-label">' . esc_html($label) . '</div>';
        echo '</div>';
    }

    /**
     * @param list<Template> $templates
     * @param list<array{
     *     id: int,
     *     title: string,
     *     template_id: int,
     *     template_label: string,
     *     status: string,
     *     updated_at: string,
     *     editor_name: string
     * }> $recentEntries
     */
    private function renderColumns(array $templates, array $recentEntries): void
    {
        echo '<div class="bsf-dash-grid">';

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head">';
        echo '<h3>' . esc_html__('Templates', 'bs-fabric') . '</h3>';
        echo '<div class="bsf-grow"></div>';

        if (current_user_can(Capabilities::MANAGE_SCHEMA)) {
            echo Button::render(__('Alle', 'bs-fabric'), [
                'variant' => 'subtle',
                'size' => 'sm',
                'icon' => 'chevr',
                'href' => SchemaScreen::url(SchemaScreen::TAB_TEMPLATES),
            ]);
        }

        echo '</div>';
        echo '<div class="bsf-card-list">';

        if ($templates === []) {
            echo '<div class="bsf-empty">';
            echo '<h4>' . esc_html__('Noch keine Templates', 'bs-fabric') . '</h4>';
            echo '<p class="bsf-muted">' . esc_html__('Lege dein erstes Webmuster in den Einstellungen an.', 'bs-fabric') . '</p>';
            echo '</div>';
        } else {
            foreach ($templates as $index => $template) {
                $toneClass = $index % 2 === 0 ? 'bsf-template-icon-accent' : 'bsf-template-icon-thread';
                $fieldCount = count($this->templateRepository->getFields($template->getId()));
                $entryCount = $this->entryRepository->getByTemplate($template->getId())->count();
                $editUrl = SchemaScreen::url(SchemaScreen::TAB_TEMPLATES, [
                    'action' => 'edit',
                    'template_id' => $template->getId(),
                ]);

                echo '<a class="bsf-list-btn" href="' . esc_url($editUrl) . '">';
                echo '<span class="bsf-template-icon ' . esc_attr($toneClass) . '">';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo Icon::render('box', 18);
                echo '</span>';
                echo '<span class="bsf-col bsf-grow">';
                echo '<span class="bsf-list-btn-title">' . esc_html($template->label) . '</span>';
                echo '<span class="bsf-list-btn-meta">';
                printf(
                    esc_html__('%1$d Felder · %2$d Einträge', 'bs-fabric'),
                    $fieldCount,
                    $entryCount
                );
                echo '</span>';
                echo '</span>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo Icon::render('chevr', 15);
                echo '</a>';
            }
        }

        echo '</div></div>';

        echo '<div class="bsf-card">';
        echo '<div class="bsf-card-head">';
        echo '<h3>' . esc_html__('Zuletzt bearbeitet', 'bs-fabric') . '</h3>';
        echo '<div class="bsf-grow"></div>';
        echo Button::render(__('Explorer', 'bs-fabric'), [
            'variant' => 'subtle',
            'size' => 'sm',
            'icon' => 'chevr',
            'href' => admin_url('admin.php?page=' . ExplorerScreen::SLUG),
        ]);
        echo '</div>';
        echo '<div class="bsf-card-list">';

        if ($recentEntries === []) {
            echo '<div class="bsf-empty">';
            echo '<h4>' . esc_html__('Noch keine Einträge', 'bs-fabric') . '</h4>';
            echo '<p class="bsf-muted">' . esc_html__('Sobald Redakteure Inhalte pflegen, erscheinen sie hier.', 'bs-fabric') . '</p>';
            echo '</div>';
        } else {
            foreach ($recentEntries as $entry) {
                $editUrl = EntryEditScreen::url($entry['template_id'], $entry['id']);

                echo '<a class="bsf-list-btn" href="' . esc_url($editUrl) . '">';
                echo '<span class="bsf-status-dot ' . esc_attr(StatusBadge::dotClass($entry['status'])) . '"></span>';
                echo '<span class="bsf-col bsf-grow">';
                echo '<span class="bsf-list-btn-title bsf-truncate">' . esc_html($entry['title']) . '</span>';
                echo '<span class="bsf-list-btn-meta">';
                echo esc_html($this->formatRecentMeta($entry));
                echo '</span>';
                echo '</span>';
                echo '</a>';
            }
        }

        echo '</div></div>';
        echo '</div>';
    }

    /**
     * @param array{editor_name: string, updated_at: string, template_label: string} $entry
     */
    private function formatRecentMeta(array $entry): string
    {
        $parts = array_filter([
            $entry['editor_name'],
            $this->formatRelativeTime($entry['updated_at']),
        ]);

        return implode(' · ', $parts);
    }

    private function formatRelativeTime(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }

        $timestamp = strtotime($datetime);

        if ($timestamp === false) {
            return $datetime;
        }

        return sprintf(
            /* translators: %s: human-readable time difference */
            __('vor %s', 'bs-fabric'),
            human_time_diff($timestamp, time())
        );
    }
}
