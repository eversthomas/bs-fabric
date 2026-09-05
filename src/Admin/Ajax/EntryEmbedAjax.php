<?php

declare(strict_types=1);

namespace BsFabric\Admin\Ajax;

use BsFabric\Core\Capabilities;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\TemplateRepository;

/**
 * Liefert die Auswahl-Daten für den Gutenberg-Block „Fabric-Inhalt" im Editor
 * (Template-Dropdown + Entry-Checkboxen). Nur für eingeloggte Redakteure —
 * unabhängig von der öffentlichen REST-Sichtbarkeit einzelner Templates.
 */
final class EntryEmbedAjax
{
    public const NONCE_ACTION = 'bs_fabric_entry_embed_ajax';

    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly EntryRepository $entryRepository,
    ) {
    }

    public function register(): void
    {
        add_action('wp_ajax_bs_fabric_embed_templates', [$this, 'templates']);
        add_action('wp_ajax_bs_fabric_embed_entries', [$this, 'entries']);
    }

    public function templates(): void
    {
        $this->authorize();

        $templates = array_map(
            static fn ($template): array => [
                'name' => $template->name,
                'label' => $template->label,
            ],
            $this->templateRepository->getAll()
        );

        wp_send_json_success(['templates' => array_values($templates)]);
    }

    public function entries(): void
    {
        $this->authorize();

        $templateName = isset($_POST['template']) ? sanitize_key((string) wp_unslash($_POST['template'])) : '';
        $template = $templateName !== '' ? $this->templateRepository->getByName($templateName) : null;

        if ($template === null) {
            wp_send_json_success(['entries' => []]);
        }

        $entries = [];

        foreach ($this->entryRepository->getByTemplate($template->getId()) as $entry) {
            $entries[] = [
                'id' => $entry->getId(),
                'title' => (string) $entry->title,
                'status' => (string) $entry->status,
            ];
        }

        wp_send_json_success(['entries' => $entries]);
    }

    private function authorize(): void
    {
        if (! current_user_can(Capabilities::MANAGE_ENTRIES)) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'bs-fabric')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }
}
