<?php

declare(strict_types=1);

/**
 * Öffentliche Detailseite für einen Fabric-Entry (/template/slug).
 *
 * Theme-Override: `/bs-fabric/single.php` oder `/bs-fabric/default/single.php`
 *
 * @var \BsFabric\Entries\Entry|null $entry wird über EntryPermalink bereitgestellt
 */

defined('ABSPATH') || exit;

use BsFabric\Core\Plugin;
use BsFabric\Frontend\EntryPermalink;
use BsFabric\Frontend\Renderer;

/** @var EntryPermalink $permalink */
$permalink = Plugin::instance()->container()->get(EntryPermalink::SERVICE_ID);
$entry = $permalink->getResolvedEntry();

if ($entry === null) {
    status_header(404);
    nocache_headers();

    if (is_readable(get_404_template())) {
        include get_404_template();
    }

    return;
}

/** @var Renderer $renderer */
$renderer = Plugin::instance()->container()->get(Renderer::SERVICE_ID);

get_header();
?>
<main class="bs-fabric-single" id="bs-fabric-entry-<?php echo esc_attr((string) $entry->getId()); ?>">
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped field output.
    echo $renderer->render($entry, 'detail');
    ?>
</main>
<?php
get_footer();
