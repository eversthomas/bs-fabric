<?php

declare(strict_types=1);

/**
 * Detail view template.
 *
 * Theme-Override: `/bs-fabric/detail.php` oder `/bs-fabric/default/detail.php`
 *
 * Address-Felder liefern über `$entry->feldname` ein Array mit u. a.
 * `formatted`, `lat`, `lng`, `maps_url`. Die Standard-Ausgabe inkl. Karte
 * kommt über `$renderer->renderFieldHtml()`. Für Custom-Layouts:
 *
 *   $address = $entry->standort;
 *   if (is_array($address)) {
 *       echo esc_html($renderer->getAddressFormatted($address));
 *       echo $renderer->renderAddressMap($address); // enqueued Leaflet + OSM
 *   }
 *
 * @var \BsFabric\Entries\Entry $entry
 * @var \BsFabric\Frontend\Renderer $renderer
 * @var string $view
 */

defined('ABSPATH') || exit;

$title = $renderer->getDisplayTitle($entry);
?>
<article class="bs-fabric-detail bs-fabric-view-detail">
    <header class="bs-fabric-detail__header">
        <h1 class="bs-fabric-detail__title"><?php echo esc_html($title); ?></h1>
    </header>

    <dl class="bs-fabric-detail__fields">
        <?php foreach ($entry->getFields() as $fieldName => $field) : ?>
            <?php
            $valueHtml = $renderer->renderFieldHtml($entry, $fieldName);

            if ($valueHtml === '') {
                continue;
            }
            ?>
            <div class="bs-fabric-detail__field bs-fabric-detail__field-<?php echo esc_attr($field['type']); ?>">
                <dt class="bs-fabric-detail__label"><?php echo esc_html($field['label']); ?></dt>
                <dd class="bs-fabric-detail__value">
                    <?php
                    // Renderer escaped bereits; wp_kses_post würde data-Attribute der Karte entfernen.
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $valueHtml;
                    ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
</article>
