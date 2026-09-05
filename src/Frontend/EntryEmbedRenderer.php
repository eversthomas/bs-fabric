<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

use BsFabric\Api\EntryResolver;
use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;

/**
 * Gemeinsame Rendering-Logik für den `[bs_fabric]`-Shortcode und den
 * Gutenberg-Block „Fabric-Inhalt" — beide bleiben dadurch in Sync und
 * das Plugin funktioniert unabhängig davon, ob der Block-Editor aktiv ist.
 */
final class EntryEmbedRenderer
{
    public const SERVICE_ID = 'entry_embed.renderer';

    /** @var list<string> */
    private const ALLOWED_VIEWS = ['card', 'detail', 'list'];

    public function __construct(
        private readonly EntryResolver $entryResolver,
    ) {
    }

    /**
     * @param list<int> $entryIds
     */
    public function render(string $templateName, array $entryIds, string $view): string
    {
        $templateName = sanitize_key($templateName);
        $view = in_array($view, self::ALLOWED_VIEWS, true) ? $view : 'card';

        if ($templateName === '' || $entryIds === []) {
            return '';
        }

        $entries = [];

        foreach ($entryIds as $entryId) {
            $entry = $this->entryResolver->resolve($templateName, $entryId);

            // Nur veröffentlichte Einträge — ein Redakteur kann einen Entwurf zum
            // Testen auswählen, ohne dass er versehentlich live für alle sichtbar wird.
            if ($entry instanceof Entry && $entry->status === 'published') {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return '';
        }

        $collection = new EntryCollection($entries);

        return $collection->render($view, '<div class="bs-fabric-embed bs-fabric-embed-' . $view . '">', '</div>');
    }
}
