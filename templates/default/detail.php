<?php

declare(strict_types=1);

/**
 * Detail view template.
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
                <dd class="bs-fabric-detail__value"><?php echo wp_kses_post($valueHtml); ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</article>
