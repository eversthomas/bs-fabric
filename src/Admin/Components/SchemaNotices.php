<?php

declare(strict_types=1);

namespace BsFabric\Admin\Components;

final class SchemaNotices
{
    public static function enqueueToastScript(): void
    {
        if (! isset($_GET['bs_fabric_notice'], $_GET['bs_fabric_message'])) {
            return;
        }

        $status = sanitize_key((string) wp_unslash($_GET['bs_fabric_notice']));
        $message = sanitize_text_field(rawurldecode((string) wp_unslash($_GET['bs_fabric_message'])));

        if ($message === '') {
            return;
        }

        wp_add_inline_script(
            'bs-fabric-bsf-ui',
            sprintf(
                'document.addEventListener("DOMContentLoaded",function(){if(window.bsFabricUi){window.bsFabricUi.toast(%s);}});',
                wp_json_encode($message)
            )
        );
    }
}
