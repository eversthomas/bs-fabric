<?php

declare(strict_types=1);

/**
 * Card view template.
 *
 * @var \BsFabric\Entries\Entry $entry
 * @var \BsFabric\Frontend\Renderer $renderer
 * @var string $view
 */

defined('ABSPATH') || exit;

$title = $renderer->getDisplayTitle($entry);
$text = $renderer->getFirstTextValue($entry);
$image = $renderer->getFirstImageData($entry);
$link = $renderer->getEntryLink($entry, $view);
?>
<article class="bs-fabric-card bs-fabric-view-card">
    <?php if ($image !== null) : ?>
        <a class="bs-fabric-card__image" href="<?php echo esc_url($link); ?>">
            <img
                src="<?php echo esc_url($image['url']); ?>"
                alt="<?php echo esc_attr($image['alt']); ?>"
                class="bs-fabric-card__img"
                loading="lazy"
            />
        </a>
    <?php endif; ?>

    <div class="bs-fabric-card__body">
        <h3 class="bs-fabric-card__title">
            <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a>
        </h3>

        <?php if ($text !== '') : ?>
            <p class="bs-fabric-card__text"><?php echo esc_html($text); ?></p>
        <?php endif; ?>

        <a class="bs-fabric-card__link" href="<?php echo esc_url($link); ?>">
            <?php esc_html_e('Mehr erfahren', 'bs-fabric'); ?>
        </a>
    </div>
</article>
