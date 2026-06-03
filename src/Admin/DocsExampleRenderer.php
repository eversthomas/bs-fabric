<?php

declare(strict_types=1);

namespace BsFabric\Admin;

use BsFabric\Admin\Components\Button;

final class DocsExampleRenderer
{
    /**
     * @param list<array{label: string, intro: string, code: string}> $examples
     */
    public static function renderSection(string $title, string $description, array $examples): void
    {
        echo '<section class="bs-fabric-docs-section">';
        echo '<h2>' . esc_html($title) . '</h2>';
        echo '<p class="bs-fabric-docs-section-intro">' . esc_html($description) . '</p>';
        self::renderExamples($examples);
        echo '</section>';
    }

    /**
     * @param list<array{label: string, intro: string, code: string}> $examples
     */
    public static function renderExamples(array $examples): void
    {
        foreach ($examples as $example) {
            self::renderExample($example);
        }
    }

    /**
     * @param array{label: string, intro: string, code: string} $example
     */
    public static function renderExample(array $example): void
    {
        echo '<div class="bs-fabric-docs-example">';
        echo '<div class="bs-fabric-docs-example-header">';
        echo '<h3>' . esc_html($example['label']) . '</h3>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo Button::render(__('Kopieren', 'bs-fabric'), [
            'variant' => 'ghost',
            'size' => 'sm',
            'class' => 'bs-fabric-docs-copy',
        ]);
        echo '</div>';
        echo '<p class="bs-fabric-docs-example-intro">' . esc_html($example['intro']) . '</p>';
        echo '<pre class="bs-fabric-docs-code"><code class="language-php">';
        echo esc_html(trim($example['code']));
        echo '</code></pre>';
        echo '</div>';
    }

    public static function enqueueAssets(bool $withSyntaxHighlight = true): void
    {
        if ($withSyntaxHighlight) {
            wp_enqueue_style(
                'bs-fabric-prism',
                'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css',
                [],
                '1.29.0'
            );

            wp_enqueue_script(
                'bs-fabric-prism-core',
                'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js',
                [],
                '1.29.0',
                true
            );

            wp_enqueue_script(
                'bs-fabric-prism-php',
                'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js',
                ['bs-fabric-prism-core'],
                '1.29.0',
                true
            );
        }

        wp_enqueue_script(
            'bs-fabric-docs-admin',
            BS_FABRIC_URL . 'assets/js/docs-admin.js',
            $withSyntaxHighlight ? ['bs-fabric-prism-php'] : [],
            BS_FABRIC_VERSION,
            true
        );

        wp_enqueue_style(
            'bs-fabric-docs-admin',
            BS_FABRIC_URL . 'assets/css/docs-admin.css',
            $withSyntaxHighlight ? ['bs-fabric-prism'] : [],
            BS_FABRIC_VERSION
        );
    }
}
