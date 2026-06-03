<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class DataLifecycleHints
{
    public static function renderDashboardCard(): void
    {
        echo '<div class="bsf-card bsf-info-card" style="margin-bottom:var(--gap)">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Deine Inhalte — kurz erklärt', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';
        echo '<ul class="bsf-hint-list">';

        foreach (self::editorBulletPoints() as $point) {
            echo '<li>' . esc_html($point) . '</li>';
        }

        echo '</ul>';
        echo '<p class="bsf-muted" style="margin:14px 0 0">';
        echo esc_html__(
            'Tipp: Vor dem Löschen mehrerer Einträge kannst du in jeder Template-Liste über „Excel exportieren“ ein Backup anlegen.',
            'bs-fabric'
        );
        echo '</p>';
        echo '</div></div>';
    }

    public static function renderAdvancedSection(): void
    {
        echo '<div class="bsf-card" style="margin-top:var(--gap)">';
        echo '<div class="bsf-card-head"><h3>' . esc_html__('Daten-Lebenszyklus', 'bs-fabric') . '</h3></div>';
        echo '<div class="bsf-card-body">';
        echo '<p class="bsf-muted">' . esc_html__(
            'Fabric speichert Einträge ausschließlich in eigenen Tabellen — nicht in WordPress-Beiträgen. Standard-Exporte von WordPress enthalten diese Daten nicht.',
            'bs-fabric'
        ) . '</p>';

        echo '<table class="bsf-tbl" style="margin-top:16px">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Aktion', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Einträge / Listen', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Admin-UI', 'bs-fabric') . '</th>';
        echo '<th>' . esc_html__('Theme bs_fabric()', 'bs-fabric') . '</th>';
        echo '</tr></thead><tbody>';

        foreach (self::lifecycleRows() as $row) {
            echo '<tr>';
            echo '<td class="bsf-cell-strong">' . esc_html($row['action']) . '</td>';
            echo '<td>' . esc_html($row['entries']) . '</td>';
            echo '<td>' . esc_html($row['admin']) . '</td>';
            echo '<td>' . esc_html($row['theme']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p class="bsf-muted" style="margin-top:16px">';
        echo esc_html__(
            'Backup heute: JSON-Schema-Export (nur Struktur) unter API & Export; Einträge pro Template-Liste als Excel-CSV oder mysqldump der sechs bs_fabric_*-Tabellen.',
            'bs-fabric'
        );
        echo '</p>';
        echo '</div></div>';
    }

    /**
     * @return list<string>
     */
    private static function editorBulletPoints(): array
    {
        return [
            __(
                'Deine Einträge liegen in Fabric — nicht in normalen WordPress-Beiträgen oder Seiten.',
                'bs-fabric'
            ),
            __(
                'Gelöschte Einträge sind unwiderruflich weg. Prüfe vor dem Löschen, ob du die Daten noch brauchst.',
                'bs-fabric'
            ),
            __(
                'Wird das Fabric-Plugin deaktiviert, bleiben die Daten in der Datenbank — du erreichst sie im Admin aber nicht mehr; Themes mit bs_fabric() funktionieren dann nicht.',
                'bs-fabric'
            ),
            __(
                'Nach Reaktivieren des Plugins sind alle Einträge wieder wie zuvor verfügbar.',
                'bs-fabric'
            ),
        ];
    }

    /**
     * @return list<array{action: string, entries: string, admin: string, theme: string}>
     */
    private static function lifecycleRows(): array
    {
        return [
            [
                'action' => __('Deaktivieren', 'bs-fabric'),
                'entries' => __('Bleiben in der DB', 'bs-fabric'),
                'admin' => __('Nicht erreichbar', 'bs-fabric'),
                'theme' => __('Funktioniert nicht', 'bs-fabric'),
            ],
            [
                'action' => __('Reaktivieren', 'bs-fabric'),
                'entries' => __('Unverändert', 'bs-fabric'),
                'admin' => __('Wieder verfügbar', 'bs-fabric'),
                'theme' => __('Wieder aktiv', 'bs-fabric'),
            ],
            [
                'action' => __('Plugin löschen (Daten behalten)', 'bs-fabric'),
                'entries' => __('Tabellen bleiben', 'bs-fabric'),
                'admin' => __('Weg', 'bs-fabric'),
                'theme' => __('Weg', 'bs-fabric'),
            ],
            [
                'action' => __('Plugin löschen (Daten mitlöschen)', 'bs-fabric'),
                'entries' => __('Alle Tabellen DROP', 'bs-fabric'),
                'admin' => __('Weg', 'bs-fabric'),
                'theme' => __('Weg', 'bs-fabric'),
            ],
        ];
    }
}
