<?php

declare(strict_types=1);

/**
 * List view template.
 *
 * @var \BsFabric\Entries\EntryCollection $collection
 * @var \BsFabric\Frontend\Renderer $renderer
 * @var string $view
 */

defined('ABSPATH') || exit;
?>
<ul class="bs-fabric-list bs-fabric-view-list">
    <?php foreach ($collection as $entry) : ?>
        <?php
        $title = $renderer->getDisplayTitle($entry);
        $description = $renderer->getFirstDescriptionValue($entry);
        $link = $renderer->getEntryLink($entry, $view);
        ?>
        <li class="bs-fabric-list__item">
            <a class="bs-fabric-list__title" href="<?php echo esc_url($link); ?>">
                <?php echo esc_html($title); ?>
            </a>
            <?php if ($description !== '') : ?>
                <span class="bs-fabric-list__description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
